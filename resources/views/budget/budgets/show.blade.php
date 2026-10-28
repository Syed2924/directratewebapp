@extends('layouts.app')
@section('title', 'Budget ' . $budget->reference_no)

@section('content')
    <div class="page-head">
        <div>
            <h1>Directorate Budget {{ $budget->financialYear->label }}</h1>
            <p>{{ $budget->reference_no }} · {{ config("budget.funding_sources.{$budget->funding_source}") }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('budget.budgets.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Budgets</a>
            @can('update', $budget)<a href="{{ route('budget.budgets.edit', $budget) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="summary-strip">
            <div><div class="k">Original Approved Budget</div><div class="v">@pkr($budget->original_amount)</div></div>
            <div><div class="k">Authorized Revisions</div><div class="v">{{ ($revised - $budget->original_amount) >= 0 ? '+' : '−' }} PKR {{ number_format(abs($revised - $budget->original_amount)) }}</div></div>
            <div><div class="k">Revised Budget</div><div class="v">@pkr($revised)</div></div>
            <div><div class="k">Allocated to Offices</div><div class="v">@pkr($allocated)</div></div>
            <div><div class="k">Not Yet Allocated</div><div class="v">@pkr($revised - $allocated)</div></div>
            <div><div class="k">Status</div><div class="v">@include('budget.partials.status', ['status' => $budget->status])</div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            @include('budget.partials.workflow', ['record' => $budget, 'type' => 'budget'])

            <div class="card mb-3">
                <div class="card-header">Budget Details</div>
                <div class="card-body">
                    <dl class="row kv mb-0">
                        <dt class="col-sm-4">Financial Year</dt><dd class="col-sm-8">{{ $budget->financialYear->label }} ({{ $budget->financialYear->starts_on->format('d M Y') }} – {{ $budget->financialYear->ends_on->format('d M Y') }})</dd>
                        <dt class="col-sm-4">Budget Head</dt><dd class="col-sm-8">{{ config("budget.budget_heads.{$budget->budget_head}") }}</dd>
                        <dt class="col-sm-4">Approving Authority</dt><dd class="col-sm-8">{{ $budget->approving_authority ?? '—' }}</dd>
                        <dt class="col-sm-4">Date Approved / Effective</dt><dd class="col-sm-8">{{ $budget->approved_on?->format('d M Y') ?? '—' }} / {{ $budget->effective_on?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-sm-4">Remarks</dt><dd class="col-sm-8 mb-0">{{ $budget->remarks ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Budget Revisions</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Reference</th><th>Effective</th><th>Change</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($budget->revisions as $r)
                            <tr>
                                <td class="fw-medium text-nowrap">{{ $r->reference_no }}</td>
                                <td class="small text-nowrap">{{ $r->effective_on->format('d M Y') }}</td>
                                <td class="text-nowrap {{ $r->direction === 'increase' ? 'text-primary' : 'text-danger' }}">{{ $r->direction === 'increase' ? '+' : '−' }} {{ number_format($r->amount) }}</td>
                                <td class="small">{{ $r->reason }}<div class="small-2 text-muted-2">by {{ $r->submitter->name ?? '—' }}</div></td>
                                <td>@include('budget.partials.status', ['status' => $r->status])</td>
                                <td class="text-end">
                                    @php($rd = Gate::inspect('decide', $r))
                                    @if ($rd->allowed())
                                        <form method="POST" action="{{ route('budget.workflow.decide', ['revision', $r->id]) }}" class="d-flex gap-1 justify-content-end" data-reject-prompt>
                                            @csrf
                                            <input type="hidden" name="remarks">
                                            <button name="decision" value="approve" class="btn btn-sm btn-primary">Approve</button>
                                            <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                                        </form>
                                    @elseif ($r->awaitingDecision() && $rd->message())
                                        <span class="small-2 text-muted-2">{{ $rd->message() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted-2 py-4">No revisions. The original budget is never edited; increases and decreases are recorded here.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($budget->isApproved() && auth()->user()->can('create', App\Models\Budget::class) && $budget->isWithinScopeOf(auth()->user()))
                    <form method="POST" action="{{ route('budget.budgets.revisions.store', $budget) }}" enctype="multipart/form-data" class="card-body border-top row g-2 align-items-end" novalidate>
                        @csrf
                        <div class="col-12 small fw-semibold">Record an authorized revision</div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small" for="direction">Type</label>
                            <select id="direction" name="direction" class="form-select"><option value="increase">Increase</option><option value="decrease">Decrease</option></select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small" for="rv-amount">Amount (PKR)</label>
                            <input type="number" min="1" id="rv-amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small" for="rv-eff">Effective Date</label>
                            <input type="date" id="rv-eff" name="effective_on" class="form-control" value="{{ old('effective_on', now()->toDateString()) }}">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small" for="rv-doc">Approval Document</label>
                            <input type="file" id="rv-doc" name="document" class="form-control">
                        </div>
                        <div class="col-md-10">
                            <label class="form-label small" for="rv-reason">Reason / Authority</label>
                            <input id="rv-reason" name="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" maxlength="1000" placeholder="e.g. Supplementary grant notified vide letter No. …">
                        </div>
                        <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Record</button></div>
                    </form>
                @endif
            </div>

            @include('budget.partials.documents', ['record' => $budget, 'type' => 'budget', 'defaultKind' => 'budget'])
        </div>
        <div class="col-xl-4">
            @include('budget.partials.history', ['approvals' => $budget->approvals])
        </div>
    </div>
    @include('budget.partials.reject-prompt')
@endsection
