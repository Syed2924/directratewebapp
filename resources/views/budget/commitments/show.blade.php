@extends('layouts.app')
@section('title', 'Commitment ' . $commitment->reference_no)

@php($c = $commitment)
@section('content')
    <div class="page-head">
        <div>
            <h1>Commitment {{ $c->reference_no }}</h1>
            <p>{{ $c->description }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('budget.commitments.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Commitments</a>
            @if ($c->status === 'open' && auth()->user()->can('budget.record_payment'))
                <a href="{{ route('budget.transactions.create', ['commitment' => $c->id]) }}" class="btn btn-primary"><i class="bi bi-receipt me-1"></i>Record Expense</a>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="summary-strip">
            <div><div class="k">Allocation</div><div class="v"><a href="{{ route('budget.allocations.show', $c->allocation) }}">{{ $c->allocation->reference_no }}</a></div></div>
            <div><div class="k">Office</div><div class="v">{{ $c->allocation->office->name }}</div></div>
            <div><div class="k">Committed</div><div class="v">@pkr($c->amount)</div></div>
            <div><div class="k">Spent</div><div class="v">@pkr($c->spent())</div></div>
            <div><div class="k">Outstanding</div><div class="v">@pkr($c->outstanding())</div></div>
            <div><div class="k">Status</div><div class="v">@include('budget.partials.status', ['status' => $c->status])</div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            @if ($c->proposal)
                <div class="alert alert-light border d-flex justify-content-between flex-wrap gap-2 align-items-center">
                    <div><i class="bi bi-link-45deg me-1"></i>Commitment for activity <strong>{{ $c->proposal->tracking_no }}</strong> — {{ $c->proposal->title }}.
                        It is closed automatically when the activity's completion is verified.</div>
                    @if ($c->proposal->activity)
                        @can('view', $c->proposal->activity)<a href="{{ route('activity.activities.show', [$c->proposal->activity, 'tab' => 'expenses']) }}" class="btn btn-sm btn-outline-primary">Open activity</a>@endcan
                    @endif
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-header">Expenditure against this commitment</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Reference</th><th>Date</th><th>Description</th><th>Recorded by</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($c->transactions as $t)
                            <tr>
                                <td class="text-nowrap"><a href="{{ route('budget.transactions.show', $t) }}" class="fw-medium">{{ $t->reference_no }}</a></td>
                                <td class="small text-nowrap">{{ $t->txn_date->format('d M Y') }}</td>
                                <td>{{ $t->description }}</td>
                                <td class="small">{{ $t->submitter->name ?? '—' }}</td>
                                <td class="text-end">{{ number_format($t->amount) }}</td>
                                <td>@include('budget.partials.status', ['status' => $t->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted-2 py-4">Nothing spent yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @can('close', $c)
                <div class="card mb-3">
                    <div class="card-header">Close or Cancel</div>
                    <form method="POST" action="{{ route('budget.commitments.close', $c) }}" class="card-body row g-2 align-items-end" novalidate>
                        @csrf
                        <div class="col-md-3">
                            <select name="action" class="form-select" aria-label="Action">
                                <option value="closed">Close — release unspent @pkr($c->outstanding())</option>
                                <option value="cancelled" @disabled($c->spent() > 0)>Cancel — obligation withdrawn</option>
                            </select>
                        </div>
                        <div class="col-md-7"><input name="remarks" class="form-control @error('remarks') is-invalid @enderror" placeholder="Reason (required)" maxlength="1000" aria-label="Reason"></div>
                        <div class="col-md-2 d-grid"><button class="btn btn-outline-danger">Confirm</button></div>
                        @error('action')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
                    </form>
                </div>
            @endcan

            @include('budget.partials.documents', ['record' => $c, 'type' => 'commitment', 'defaultKind' => 'approval'])
        </div>
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-body small">
                    <div><span class="text-muted-2">Committed on:</span> {{ $c->committed_on->format('d M Y') }}</div>
                    <div><span class="text-muted-2">Authority:</span> {{ $c->authority_reference ?? '—' }}</div>
                    @if ($c->closed_at)<div><span class="text-muted-2">Closed:</span> {{ $c->closed_at->format('d M Y') }}</div>@endif
                </div>
            </div>
            @include('budget.partials.history', ['approvals' => $c->approvals])
        </div>
    </div>
@endsection
