@extends('layouts.app')
@section('title', 'Allocation ' . $allocation->reference_no)

@php($a = $allocation)
@section('content')
    <div class="page-head">
        <div>
            <h1>Allocation {{ $a->reference_no }}</h1>
            <p>{{ $a->office->name }} · {{ $a->headLabel() }} · {{ $a->financialYear->label }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('budget.allocations.index', ['fy' => $a->financial_year_id]) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Allocations</a>
            @can('update', $a)<a href="{{ route('budget.allocations.edit', $a) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="summary-strip">
            <div><div class="k">Planned</div><div class="v">@pkr($a->planned_amount)</div></div>
            <div><div class="k">Approved</div><div class="v">{{ $a->status === 'approved' ? 'PKR ' . number_format($a->approved_amount) : '—' }}</div></div>
            <div><div class="k">Transfers In / Out</div><div class="v">+{{ number_format($a->transfersInTotal()) }} / −{{ number_format($a->transfersOutTotal()) }}</div></div>
            <div><div class="k">Revised Allocation</div><div class="v">@pkr($a->revised())</div></div>
            <div><div class="k">Committed / Spent</div><div class="v">{{ number_format($a->committed()) }} / {{ number_format($a->spent()) }}</div></div>
            <div><div class="k">Remaining</div><div class="v {{ $a->available() < 0 ? 'text-danger' : '' }}">@pkr($a->available())</div></div>
            <div><div class="k">Status</div><div class="v">@include('budget.partials.status', ['status' => $a->status])</div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            @include('budget.partials.workflow', ['record' => $a, 'type' => 'allocation'])

            <div class="card mb-3">
                <div class="card-header">Transfers</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Reference</th><th>Direction</th><th>Counterpart</th><th class="text-end">Amount</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($transfers as $t)
                            @php($out = $t->from_allocation_id === $a->id)
                            <tr>
                                <td class="fw-medium text-nowrap">{{ $t->reference_no }}</td>
                                <td>{!! $out ? '<span class="text-danger">Out</span>' : '<span class="text-primary">In</span>' !!}</td>
                                <td class="small">{{ $out ? $t->to->office->name . ' · ' . $t->to->reference_no : $t->from->office->name . ' · ' . $t->from->reference_no }}</td>
                                <td class="text-end">{{ number_format($t->amount) }}</td>
                                <td class="small">{{ $t->reason }}</td>
                                <td>@include('budget.partials.status', ['status' => $t->status])</td>
                                <td class="text-end">
                                    @php($td = Gate::inspect('decide', $t))
                                    @if ($td->allowed())
                                        <form method="POST" action="{{ route('budget.workflow.decide', ['transfer', $t->id]) }}" class="d-flex gap-1 justify-content-end" data-reject-prompt>
                                            @csrf <input type="hidden" name="remarks">
                                            <button name="decision" value="approve" class="btn btn-sm btn-primary">Approve</button>
                                            <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                                        </form>
                                    @elseif ($t->awaitingDecision() && $td->message())
                                        <span class="small-2 text-muted-2">{{ $td->message() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted-2 py-4">No transfers.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @error('decision')<div class="alert alert-danger m-3 py-2">{{ $message }}</div>@enderror
                @if ($a->status === 'approved' && auth()->user()->can('create', App\Models\BudgetAllocation::class) && $transferTargets->isNotEmpty())
                    <form method="POST" action="{{ route('budget.allocations.transfers.store', $a) }}" class="card-body border-top row g-2 align-items-end" novalidate>
                        @csrf
                        <div class="col-12 small fw-semibold">Transfer out of this allocation <span class="fw-normal text-muted-2">(available @pkr(max(0, $a->available())))</span></div>
                        <div class="col-md-4">
                            <label class="form-label small" for="to_allocation_id">To allocation</label>
                            <select id="to_allocation_id" name="to_allocation_id" class="form-select @error('to_allocation_id') is-invalid @enderror">
                                @foreach ($transferTargets as $tt)<option value="{{ $tt->id }}" @selected(old('to_allocation_id') == $tt->id)>{{ $tt->office->name }} · {{ $tt->headLabel() }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small" for="tr-amount">Amount</label>
                            <input type="number" min="1" id="tr-amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small" for="tr-eff">Effective</label>
                            <input type="date" id="tr-eff" name="effective_on" class="form-control" value="{{ old('effective_on', now()->toDateString()) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small" for="tr-reason">Reason / Authority</label>
                            <input id="tr-reason" name="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" maxlength="1000">
                        </div>
                        <div class="col-md-1 d-grid"><button class="btn btn-outline-primary" aria-label="Request transfer"><i class="bi bi-arrow-left-right"></i></button></div>
                    </form>
                @endif
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">Commitments
                    @can('create', App\Models\BudgetCommitment::class)<a href="{{ route('budget.commitments.create') }}" class="small fw-normal">New commitment</a>@endcan</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Reference</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Outstanding</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($a->commitments as $cm)
                            <tr>
                                <td class="text-nowrap"><a href="{{ route('budget.commitments.show', $cm) }}" class="fw-medium">{{ $cm->reference_no }}</a></td>
                                <td>{{ $cm->description }}@if ($cm->proposal)<div class="small-2 text-muted-2">Activity {{ $cm->proposal->tracking_no }}</div>@endif</td>
                                <td class="text-end">{{ number_format($cm->amount) }}</td>
                                <td class="text-end">{{ number_format($cm->outstanding()) }}</td>
                                <td>@include('budget.partials.status', ['status' => $cm->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted-2 py-4">No commitments.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">Expenditure
                    <a href="{{ route('budget.transactions.index', ['fy' => $a->financial_year_id, 'office' => $a->office_id]) }}" class="small fw-normal">Expense register</a></div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Reference</th><th>Date</th><th>Description</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($a->transactions->sortByDesc('txn_date')->take(10) as $tx)
                            <tr>
                                <td class="text-nowrap"><a href="{{ route('budget.transactions.show', $tx) }}" class="fw-medium">{{ $tx->reference_no }}</a></td>
                                <td class="small text-nowrap">{{ $tx->txn_date->format('d M Y') }}</td>
                                <td>{{ $tx->description }}</td>
                                <td class="text-end">{{ number_format($tx->amount) }}</td>
                                <td>@include('budget.partials.status', ['status' => $tx->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted-2 py-4">No expenditure recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @include('budget.partials.documents', ['record' => $a, 'type' => 'allocation', 'defaultKind' => 'approval'])
        </div>
        <div class="col-xl-4">
            @include('budget.partials.history', ['approvals' => $a->approvals])
        </div>
    </div>
    @include('budget.partials.reject-prompt')
@endsection
