@extends('layouts.app')
@section('title', 'Expense ' . $expense->expense_number)

@php
    $e = $expense;
    $user = auth()->user();
    $decide = Gate::inspect('decide', $e);
    $pay = Gate::inspect('pay', $e);
    $paid = $e->paidAmount();
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Expense {{ $e->expense_number }} @include('jawan-markaz.partials.status', ['status' => $e->status])</h1>
            <p>{{ $e->center->name }} · {{ $e->description }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('jawan-markaz.expenses.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back to List</a>
            @can('update', $e)<a href="{{ route('jawan-markaz.expenses.edit', $e) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
        </div>
    </div>

    @if ($duplicates->isNotEmpty())
        <div class="alert alert-warning d-flex gap-2"><i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div><strong>Possible duplicate invoice.</strong> Invoice {{ $e->invoice_number }} from {{ $e->supplier_name }} also appears on:
                @foreach ($duplicates as $d)
                    @can('view', $d)<a href="{{ route('jawan-markaz.expenses.show', $d) }}">{{ $d->expense_number }}</a>@else{{ $d->expense_number }}@endcan ({{ $d->center->name }}, {{ $d->statusLabel() }}){{ $loop->last ? '.' : ',' }}
                @endforeach
                Check before approving.</div>
        </div>
    @endif

    @if (in_array($e->status, ['returned', 'rejected'], true) && ($last = $e->approvals->where('action', $e->status)->last()))
        <div class="alert {{ $e->status === 'rejected' ? 'alert-danger' : 'alert-warning' }}">
            <strong>{{ $e->statusLabel() }}</strong> by {{ $last->user->name ?? '—' }} on {{ $last->created_at->format('d M Y') }}:
            <div class="mt-1" style="white-space:pre-line">{{ $last->remarks }}</div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            @can('submit', $e)
                <div class="card action-card mb-3">
                    <form method="POST" action="{{ route('jawan-markaz.expenses.submit', $e) }}" class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                        @csrf
                        <span class="small">This expense is {{ strtolower($e->statusLabel()) }}. {{ $e->documents->contains('kind', 'invoice') ? 'Submit it to the Directorate when ready.' : 'Attach the invoice below, then submit.' }}</span>
                        <button class="btn btn-primary" @disabled(! $e->documents->contains('kind', 'invoice'))><i class="bi bi-send me-1"></i>{{ $e->status === 'returned' ? 'Resubmit' : 'Submit' }} to Directorate</button>
                    </form>
                    @error('invoice')<div class="px-3 pb-3 text-danger small">{{ $message }}</div>@enderror
                </div>
            @endcan

            {{-- ===== Review & decision ===== --}}
            @if ($e->awaitingDecision())
                @if ($decide->allowed())
                    <div class="card action-card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">Expense Approval
                            @can('startReview', $e)
                                <form method="POST" action="{{ route('jawan-markaz.expenses.review', $e) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Mark Under Review</button></form>
                            @endcan
                        </div>
                        <form method="POST" action="{{ route('jawan-markaz.expenses.decide', $e) }}" class="card-body row g-3" novalidate>
                            @csrf
                            @error('decision')<div class="col-12"><div class="alert alert-danger py-2 mb-0">{{ $message }}</div></div>@enderror
                            <div class="col-md-4">
                                <label class="form-label" for="decision">Decision <span class="text-danger">*</span></label>
                                <select id="decision" name="decision" class="form-select">
                                    <option value="approve" @selected(old('decision') === 'approve')>Approve</option>
                                    <option value="return" @selected(old('decision') === 'return')>Return for Correction</option>
                                    <option value="reject" @selected(old('decision') === 'reject')>Reject</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="approved_amount">Approved Amount (PKR)</label>
                                <input type="number" step="0.01" min="0.01" max="{{ (float) $e->requested_amount }}" id="approved_amount" name="approved_amount" class="form-control @error('approved_amount') is-invalid @enderror" value="{{ old('approved_amount', (float) $e->requested_amount) }}">
                                @error('approved_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Reviewed By</label>
                                <input class="form-control bg-light" value="{{ $user->name }} · {{ now()->format('d M Y') }}" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="remarks">Remarks</label>
                                <textarea id="remarks" name="remarks" rows="2" class="form-control @error('remarks') is-invalid @enderror" placeholder="Required when rejecting or returning — e.g. Review the invoice and confirm the items are within the budget.">{{ old('remarks') }}</textarea>
                                @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 small text-muted-2">Center budget available: <strong>@pkr($figures['available'])</strong> · Approving authorizes payment; it does not record a payment.</div>
                            <div class="col-12 text-end"><button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Decision</button></div>
                        </form>
                    </div>
                @else
                    <div class="alert alert-info d-flex gap-2"><i class="bi bi-hourglass-split"></i>
                        <div>{{ $e->status === 'under_review' ? 'Under review' : 'Submitted' }} — awaiting a Directorate decision.
                            @if ($user->hasPermission('jawan_markaz.approve') && $decide->message())<br><span class="small">{{ $decide->message() }}</span>@endif</div>
                    </div>
                @endif
            @endif

            {{-- ===== Expense information ===== --}}
            <div class="card mb-3">
                <div class="card-header">Expense Information</div>
                <div class="card-body">
                    <dl class="row kv mb-0">
                        <dt class="col-sm-3">Center</dt><dd class="col-sm-3"><a href="{{ route('jawan-markaz.centers.show', $e->center) }}">{{ $e->center->name }}</a></dd>
                        <dt class="col-sm-3">Category</dt><dd class="col-sm-3">{{ $e->categoryLabel() }}</dd>
                        <dt class="col-sm-3">Expense Date</dt><dd class="col-sm-3">{{ $e->expense_date->format('d M Y') }}</dd>
                        <dt class="col-sm-3">Requested Amount</dt><dd class="col-sm-3 fw-semibold">@pkr($e->requested_amount)</dd>
                        <dt class="col-sm-3">Supplier / Payee</dt><dd class="col-sm-3">{{ $e->supplier_name }}</dd>
                        <dt class="col-sm-3">Invoice No. / Date</dt><dd class="col-sm-3">{{ $e->invoice_number }} · {{ $e->invoice_date->format('d M Y') }}</dd>
                        <dt class="col-sm-3">Submitted By</dt><dd class="col-sm-3">{{ $e->submitter->name ?? '—' }}</dd>
                        <dt class="col-sm-3">Submitted On</dt><dd class="col-sm-3">{{ $e->submitted_at?->format('d M Y H:i') ?? 'Not submitted' }}</dd>
                        @if ($e->approved_amount)
                            <dt class="col-sm-3">Approved Amount</dt><dd class="col-sm-3 fw-semibold">@pkr($e->approved_amount)</dd>
                            <dt class="col-sm-3">Decided By</dt><dd class="col-sm-3">{{ $e->reviewer->name ?? '—' }}, {{ $e->decided_at?->format('d M Y') }}</dd>
                        @endif
                        <dt class="col-sm-3">Description</dt><dd class="col-sm-9 mb-0">{{ $e->description }}</dd>
                    </dl>
                </div>
            </div>

            {{-- ===== Invoices & documents ===== --}}
            <div class="card mb-3">
                <div class="card-header">Invoice &amp; Supporting Documents</div>
                <div class="card-body py-2">
                    @forelse ($e->documents as $doc)
                        <div class="doc-row">
                            <i class="bi {{ $doc->icon() }} doc-icon"></i>
                            <div class="flex-grow-1 min-w-0">
                                <a href="{{ route('budget.documents.show', [$doc, 'inline' => 1]) }}" target="_blank" rel="noopener" class="fw-medium text-truncate d-block">{{ $doc->original_name }}</a>
                                <div class="small-2 text-muted-2">{{ $doc->kindLabel() }} · {{ $doc->humanSize() }} · {{ $doc->uploader->name ?? '' }}</div>
                            </div>
                            <a href="{{ route('budget.documents.show', $doc) }}" class="btn btn-sm btn-light" aria-label="Download"><i class="bi bi-download"></i></a>
                            @if ($user->can('update', $e) && $doc->uploaded_by === $user->id)
                                <form method="POST" action="{{ route('jawan-markaz.expenses.documents.destroy', [$e, $doc]) }}" data-confirm="Remove {{ $doc->original_name }}?">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-light text-danger" aria-label="Remove"><i class="bi bi-trash"></i></button></form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted-2 small my-2">No documents attached.</p>
                    @endforelse
                </div>
                @can('update', $e)
                    <form method="POST" action="{{ route('jawan-markaz.expenses.documents.store', $e) }}" enctype="multipart/form-data" class="card-body border-top row g-2 align-items-end">
                        @csrf
                        <div class="col-md-3"><select name="kind" class="form-select form-select-sm" aria-label="Type"><option value="invoice">Invoice</option><option value="supporting">Supporting Document</option></select></div>
                        <div class="col-md-7"><input type="file" name="files[]" multiple class="form-control form-control-sm" aria-label="Files"></div>
                        <div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-primary">Upload</button></div>
                    </form>
                @endcan
            </div>

            {{-- ===== Payments ===== --}}
            @if (in_array($e->status, App\Models\JawanMarkazBudget::SPENDING, true))
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between">Payment Management
                        <span class="small fw-normal">Approved @pkr($e->approved_amount) · Paid @pkr($paid) · <strong>Outstanding @pkr($e->outstanding())</strong></span></div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Payment ID</th><th>Date</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th><th>Recorded By</th><th>Central Entry</th></tr></thead>
                            <tbody>
                            @forelse ($e->payments as $p)
                                <tr>
                                    <td class="fw-medium">{{ $p->reference_no }}@if ($p->documents->isNotEmpty())
                                            <a href="{{ route('budget.documents.show', [$p->documents->first(), 'inline' => 1]) }}" target="_blank" class="ms-1" title="Payment evidence"><i class="bi bi-paperclip"></i></a>@endif</td>
                                    <td class="small">{{ $p->payment_date->format('d M Y') }}</td>
                                    <td class="small">{{ $p->methodLabel() }}</td>
                                    <td class="small">{{ $p->transaction_reference }}</td>
                                    <td class="text-end">{{ number_format($p->amount, 2) }}</td>
                                    <td class="small">{{ $p->recorder->name ?? '—' }}</td>
                                    <td class="small">
                                        @if ($p->transaction && $user->can('view', $p->transaction))<a href="{{ route('budget.transactions.show', $p->transaction) }}">{{ $p->transaction->reference_no }}</a>@else{{ $p->transaction->reference_no ?? '—' }}@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted-2 py-4">No payments recorded. Approved expenses are paid separately by finance.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($pay->allowed())
                        <form method="POST" action="{{ route('jawan-markaz.expenses.payments.store', $e) }}" enctype="multipart/form-data" class="card-body border-top row g-2 align-items-end" novalidate>
                            @csrf
                            <div class="col-12 small fw-semibold">Record a payment</div>
                            <div class="col-6 col-md-2"><label class="form-label small" for="p-amount">Amount</label>
                                <input type="number" step="0.01" min="0.01" max="{{ $e->outstanding() }}" id="p-amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $e->outstanding()) }}"></div>
                            <div class="col-6 col-md-2"><label class="form-label small" for="p-date">Payment Date</label>
                                <input type="date" id="p-date" name="payment_date" max="{{ now()->toDateString() }}" class="form-control @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', now()->toDateString()) }}"></div>
                            <div class="col-6 col-md-2"><label class="form-label small" for="p-method">Method</label>
                                <select id="p-method" name="method" class="form-select">@foreach (config('jawan_markaz.payment_methods') as $k => $l)<option value="{{ $k }}" @selected(old('method') === $k)>{{ $l }}</option>@endforeach</select></div>
                            <div class="col-6 col-md-3"><label class="form-label small" for="p-ref">Transaction / Voucher Ref.</label>
                                <input id="p-ref" name="transaction_reference" class="form-control @error('transaction_reference') is-invalid @enderror" value="{{ old('transaction_reference') }}" maxlength="100"></div>
                            <div class="col-md-3"><label class="form-label small" for="p-evidence">Payment Evidence</label><input type="file" id="p-evidence" name="evidence" class="form-control"></div>
                            <div class="col-md-10"><input name="remarks" class="form-control" placeholder="Remarks (optional)" value="{{ old('remarks') }}" maxlength="1000" aria-label="Remarks"></div>
                            <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-cash-coin me-1"></i>Record</button></div>
                            @foreach (['amount', 'payment_date', 'txn_date'] as $field)@error($field)<div class="col-12 text-danger small">{{ $message }}</div>@enderror @endforeach
                        </form>
                    @elseif ($e->isPayable() && $user->hasPermission('jawan_markaz.record_payment') && $pay->message())
                        <div class="card-body border-top small text-muted-2">{{ $pay->message() }}</div>
                    @endif
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header">Budget Information — {{ $e->budget->financialYear->label }}</div>
                <ul class="list-group list-group-flush small">
                    @foreach ([['Authorized budget', $figures['allocated']], ['Approved expenses', $figures['approved']], ['Paid', $figures['paid']], ['Approved but unpaid', $figures['unpaid']], ['Pending review', $figures['pending']], ['Available', $figures['available']]] as [$label, $value])
                        <li class="list-group-item d-flex justify-content-between {{ $loop->last ? 'fw-semibold' : '' }}"><span>{{ $label }}</span><span>@pkr($value)</span></li>
                    @endforeach
                </ul>
                <div class="card-body border-top small"><a href="{{ route('jawan-markaz.budgets.show', $e->budget) }}">{{ $e->budget->reference_no }}</a> · @include('budget.partials.status', ['status' => $e->budget->status])</div>
            </div>
            @include('budget.partials.history', ['approvals' => $e->approvals])
        </div>
    </div>
@endsection
