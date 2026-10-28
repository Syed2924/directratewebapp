@extends('layouts.app')
@section('title', 'Expense ' . $expense->expense_no)

@php
    $e = $expense;
    $user = auth()->user();
    $decide = Gate::inspect('decide', $e);
    $pay = Gate::inspect('pay', $e);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Expense {{ $e->expense_no }} @include('hostel.partials.status', ['status' => $e->status, 'map' => 'expense'])</h1>
            <p>{{ $e->hostel->name }} · {{ $e->description }}</p>
        </div>
        <a href="{{ route('hostel.expenses.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Expenditure</a>
    </div>

    @if ($duplicates->isNotEmpty())
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Possible duplicate invoice:</strong> also on {{ $duplicates->pluck('expense_no')->implode(', ') }}. Check before approving.</div>
    @endif
    @if ($e->status === 'rejected' && ($rej = $e->approvals->where('action', 'rejected')->last()))
        <div class="alert alert-danger"><strong>Rejected</strong> by {{ $rej->user->name ?? '—' }}: {{ $rej->remarks }}</div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            @if ($e->awaitingDecision())
                @if ($decide->allowed())
                    <div class="card action-card mb-3">
                        <div class="card-header">Expense Approval</div>
                        <form method="POST" action="{{ route('hostel.expenses.decide', $e) }}" class="card-body row g-3" novalidate>
                            @csrf
                            @error('decision')<div class="col-12"><div class="alert alert-danger py-2 mb-0">{{ $message }}</div></div>@enderror
                            <div class="col-md-4"><label class="form-label" for="decision">Decision</label>
                                <select id="decision" name="decision" class="form-select"><option value="approve">Approve</option><option value="reject" @selected(old('decision') === 'reject')>Reject</option></select></div>
                            <div class="col-md-8"><label class="form-label" for="remarks">Remarks</label>
                                <input id="remarks" name="remarks" class="form-control @error('remarks') is-invalid @enderror" value="{{ old('remarks') }}" placeholder="Required when rejecting">
                                @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-12 d-flex justify-content-between align-items-center"><span class="small text-muted-2">Budget available to commit: <strong>@pkr($figures['available'])</strong></span>
                                <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Decision</button></div>
                        </form>
                    </div>
                @else
                    <div class="alert alert-info"><i class="bi bi-hourglass-split me-1"></i>Pending approval.
                        @if ($user->hasPermission('hostel.approve') && $decide->message())<span class="small d-block">{{ $decide->message() }}</span>@endif</div>
                @endif
            @endif

            <div class="card mb-3">
                <div class="card-header">Expense Details</div>
                <div class="card-body">
                    <dl class="row kv mb-0">
                        <dt class="col-sm-3">Hostel</dt><dd class="col-sm-3">{{ $e->hostel->name }}</dd>
                        <dt class="col-sm-3">Financial Year</dt><dd class="col-sm-3">{{ $e->budget->financialYear->label }}</dd>
                        <dt class="col-sm-3">Expense Date</dt><dd class="col-sm-3">{{ $e->expense_date->format('d M Y') }}</dd>
                        <dt class="col-sm-3">Category</dt><dd class="col-sm-3">{{ $e->categoryLabel() }}</dd>
                        <dt class="col-sm-3">Supplier / Payee</dt><dd class="col-sm-3">{{ $e->supplier_name }}</dd>
                        <dt class="col-sm-3">Invoice / Voucher</dt><dd class="col-sm-3">{{ $e->invoice_number }}</dd>
                        <dt class="col-sm-3">Amount</dt><dd class="col-sm-3 fw-semibold">@pkr($e->amount)</dd>
                        <dt class="col-sm-3">Approval / Payment</dt><dd class="col-sm-3">{{ $e->approvalStatus() }} / {{ $e->paymentStatus() }}</dd>
                        <dt class="col-sm-3">Recorded By</dt><dd class="col-sm-3">{{ $e->submitter->name ?? '—' }}</dd>
                        <dt class="col-sm-3">Decided By</dt><dd class="col-sm-3">{{ $e->reviewer->name ?? '—' }}{{ $e->decided_at ? ', ' . $e->decided_at->format('d M Y') : '' }}</dd>
                        <dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $e->description }}</dd>
                        @if ($e->remarks)<dt class="col-sm-3">Remarks</dt><dd class="col-sm-9 mb-0">{{ $e->remarks }}</dd>@endif
                    </dl>
                </div>
                <div class="card-body border-top py-2">
                    @foreach ($e->documents as $doc)
                        <div class="doc-row"><i class="bi {{ $doc->icon() }} doc-icon"></i>
                            <div class="flex-grow-1"><a href="{{ route('budget.documents.show', [$doc, 'inline' => 1]) }}" target="_blank" rel="noopener" class="fw-medium">{{ $doc->original_name }}</a><div class="small-2 text-muted-2">{{ $doc->kindLabel() }} · {{ $doc->humanSize() }}</div></div>
                            <a href="{{ route('budget.documents.show', $doc) }}" class="btn btn-sm btn-light" aria-label="Download"><i class="bi bi-download"></i></a></div>
                    @endforeach
                </div>
            </div>

            @if (in_array($e->status, App\Models\HostelBudget::SPENDING, true))
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between">Payments <span class="small fw-normal">Paid @pkr($e->paidAmount()) · <strong>Outstanding @pkr($e->outstanding())</strong></span></div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Payment ID</th><th>Date</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th><th>Recorded By</th><th>Central Entry</th></tr></thead>
                            <tbody>
                            @forelse ($e->payments as $p)
                                <tr><td class="fw-medium">{{ $p->reference_no }}</td><td class="small">{{ $p->payment_date->format('d M Y') }}</td><td class="small">{{ $p->methodLabel() }}</td>
                                    <td class="small">{{ $p->transaction_reference }}</td><td class="text-end">{{ number_format($p->amount, 2) }}</td><td class="small">{{ $p->recorder->name ?? '—' }}</td>
                                    <td class="small">{{ $p->transaction->reference_no ?? '—' }}</td></tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted-2 py-4">Not paid yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($pay->allowed())
                        <form method="POST" action="{{ route('hostel.expenses.payments.store', $e) }}" enctype="multipart/form-data" class="card-body border-top row g-2 align-items-end" novalidate>
                            @csrf
                            <div class="col-6 col-md-2"><label class="form-label small" for="p-amount">Amount</label><input type="number" step="0.01" min="0.01" id="p-amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $e->outstanding()) }}"></div>
                            <div class="col-6 col-md-2"><label class="form-label small" for="p-date">Date</label><input type="date" id="p-date" name="payment_date" max="{{ now()->toDateString() }}" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}"></div>
                            <div class="col-6 col-md-2"><label class="form-label small" for="p-method">Method</label><select id="p-method" name="method" class="form-select">@foreach (config('jawan_markaz.payment_methods') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                            <div class="col-6 col-md-3"><label class="form-label small" for="p-ref">Transaction / Voucher Ref.</label><input id="p-ref" name="transaction_reference" class="form-control @error('transaction_reference') is-invalid @enderror" value="{{ old('transaction_reference') }}" maxlength="100"></div>
                            <div class="col-md-3"><label class="form-label small" for="p-ev">Evidence</label><input type="file" id="p-ev" name="evidence" class="form-control"></div>
                            <div class="col-md-10"><input name="remarks" class="form-control" placeholder="Remarks (optional)" maxlength="1000" aria-label="Remarks"></div>
                            <div class="col-md-2 d-grid"><button class="btn btn-primary">Record Payment</button></div>
                            @foreach (['amount', 'payment_date', 'txn_date'] as $f)@error($f)<div class="col-12 text-danger small">{{ $message }}</div>@enderror @endforeach
                        </form>
                    @elseif ($e->isPayable() && $user->hasPermission('hostel.record_payment') && $pay->message())
                        <div class="card-body border-top small text-muted-2">{{ $pay->message() }}</div>
                    @endif
                </div>
            @endif
        </div>
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header">Hostel Budget — {{ $e->budget->financialYear->label }}</div>
                <ul class="list-group list-group-flush small">
                    @foreach ([['Revised budget', $figures['allocated']], ['Recorded expenditure', $figures['paid']], ['Outstanding commitments', $figures['unpaid']], ['Remaining budget', $figures['remaining']], ['Available to commit', $figures['available']]] as [$label, $v])
                        <li class="list-group-item d-flex justify-content-between {{ $loop->last ? 'fw-semibold' : '' }}"><span>{{ $label }}</span><span>@pkr($v)</span></li>
                    @endforeach
                </ul>
                <div class="card-body border-top small"><a href="{{ route('hostel.budgets.show', $e->budget) }}">{{ $e->budget->reference_no }}</a></div>
            </div>
            @include('budget.partials.history', ['approvals' => $e->approvals])
        </div>
    </div>
@endsection
