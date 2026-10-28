@extends('layouts.app')
@section('title', 'Budget ' . $budget->reference_no)

@php($f = $figures)
@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $budget->hostel->name }} — {{ $budget->financialYear->label }}</h1>
            <p>Hostel budget {{ $budget->reference_no }} · Authorization {{ $budget->authorization_reference ?? '—' }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('hostel.budgets.index', ['fy' => $budget->financial_year_id]) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Budgets</a>
            @can('update', $budget)<a href="{{ route('hostel.budgets.edit', $budget) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="summary-strip">
            <div><div class="k">Original Approved Budget</div><div class="v">@pkr($budget->original_amount)</div></div>
            <div><div class="k">Authorized Revisions</div><div class="v">{{ $f['revisions'] >= 0 ? '+' : '−' }} PKR {{ number_format(abs($f['revisions'])) }}</div></div>
            <div><div class="k">Revised Budget</div><div class="v">{{ $budget->status === 'approved' ? 'PKR ' . number_format($f['allocated']) : '—' }}</div></div>
            <div><div class="k">Recorded Expenditure</div><div class="v">@pkr($f['paid'])</div></div>
            <div><div class="k">Outstanding Commitments</div><div class="v">@pkr($f['unpaid'])</div></div>
            <div><div class="k">Remaining Budget</div><div class="v">@pkr($f['remaining'])</div></div>
            <div><div class="k">Available to Commit</div><div class="v {{ $f['available'] < 0 ? 'text-danger' : '' }}">@pkr($f['available'])</div></div>
            <div><div class="k">Pending Approval</div><div class="v">@pkr($f['pending'])</div></div>
            <div><div class="k">Status</div><div class="v">@include('budget.partials.status', ['status' => $budget->status])</div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            @include('budget.partials.workflow', ['record' => $budget, 'type' => 'hostel_budget'])

            @if ($budget->commitment)
                <div class="alert alert-light border small d-flex gap-2"><i class="bi bi-link-45deg fs-5"></i>
                    <div>Funded centrally by commitment
                        @can('view', $budget->commitment)<a href="{{ route('budget.commitments.show', $budget->commitment) }}">{{ $budget->commitment->reference_no }}</a>@else<strong>{{ $budget->commitment->reference_no }}</strong>@endcan
                        on {{ $budget->commitment->allocation->reference_no }} ({{ $budget->commitment->allocation->office->name }}). Every payment to this hostel's suppliers is booked once in the central expense register against it.</div>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-header">Budget Revisions</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Reference</th><th>Change</th><th>Reason</th><th>By</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($budget->revisions as $r)
                            <tr>
                                <td class="fw-medium text-nowrap">{{ $r->reference_no }}</td>
                                <td class="text-nowrap {{ $r->direction === 'increase' ? 'text-primary' : 'text-danger' }}">{{ $r->direction === 'increase' ? '+' : '−' }} {{ number_format($r->amount) }}</td>
                                <td class="small">{{ $r->reason }}</td>
                                <td class="small">{{ $r->submitter->name ?? '—' }}</td>
                                <td>@include('budget.partials.status', ['status' => $r->status])</td>
                                <td class="text-end">
                                    @php($rd = Gate::inspect('decide', $r))
                                    @if ($rd->allowed())
                                        <form method="POST" action="{{ route('budget.workflow.decide', ['hostel_revision', $r->id]) }}" class="d-flex gap-1 justify-content-end" data-reject-prompt>
                                            @csrf <input type="hidden" name="remarks">
                                            <button name="decision" value="approve" class="btn btn-sm btn-primary">Approve</button>
                                            <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                                        </form>
                                    @elseif ($r->awaitingDecision() && $rd->message())
                                        <span class="small-2 text-muted-2">{{ $rd->message() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted-2 py-4">No revisions. The original budget is never edited.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($budget->status === 'approved' && auth()->user()->can('create', App\Models\JawanMarkazBudget::class))
                    <form method="POST" action="{{ route('hostel.budgets.revisions.store', $budget) }}" enctype="multipart/form-data" class="card-body border-top row g-2 align-items-end" novalidate>
                        @csrf
                        <div class="col-6 col-md-2"><label class="form-label small" for="direction">Type</label>
                            <select id="direction" name="direction" class="form-select"><option value="increase">Increase</option><option value="decrease">Decrease</option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label small" for="rv-amount">Amount</label>
                            <input type="number" min="1" id="rv-amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}"></div>
                        <div class="col-md-4"><label class="form-label small" for="rv-reason">Reason / Authority</label>
                            <input id="rv-reason" name="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" maxlength="1000"></div>
                        <div class="col-md-3"><label class="form-label small" for="rv-doc">Document</label><input type="file" id="rv-doc" name="document" class="form-control"></div>
                        <div class="col-md-1 d-grid"><button class="btn btn-outline-primary" aria-label="Record revision"><i class="bi bi-plus-lg"></i></button></div>
                    </form>
                @endif
                @error('decision')<div class="alert alert-danger m-3 py-2">{{ $message }}</div>@enderror
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">Expenses against this budget
                    <a href="{{ route('hostel.expenses.index', ['hostel' => $budget->hostel_id]) }}" class="small fw-normal">View all</a></div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Expense ID</th><th>Date</th><th>Supplier</th><th class="text-end">Amount</th><th>Payment</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($expenses as $e)
                            <tr>
                                <td><a href="{{ route('hostel.expenses.show', $e) }}" class="fw-medium">{{ $e->expense_no }}</a></td>
                                <td class="small">{{ $e->expense_date->format('d M Y') }}</td>
                                <td class="small">{{ $e->supplier_name }}</td>
                                <td class="text-end">{{ number_format($e->amount) }}</td>
                                <td class="text-end">{{ $e->paymentStatus() }}</td>
                                <td>@include('hostel.partials.status', ['status' => $e->status, 'map' => 'expense'])</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted-2 py-4">No expenses yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @include('budget.partials.documents', ['record' => $budget, 'type' => 'hostel_budget', 'defaultKind' => 'approval'])
        </div>
        <div class="col-xl-4">
            @include('budget.partials.history', ['approvals' => $budget->approvals])
        </div>
    </div>
    @include('budget.partials.reject-prompt')
@endsection
