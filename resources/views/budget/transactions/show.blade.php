@extends('layouts.app')
@section('title', 'Expense ' . $transaction->reference_no)

@php($t = $transaction)
@section('content')
    <div class="page-head">
        <div>
            <h1>Expense {{ $t->reference_no }}</h1>
            <p>{{ $t->description }}</p>
        </div>
        <a href="{{ route('budget.transactions.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Expense Register</a>
    </div>

    <div class="card mb-3">
        <div class="summary-strip">
            <div><div class="k">Amount</div><div class="v">@pkr($t->amount)</div></div>
            <div><div class="k">Date</div><div class="v">{{ $t->txn_date->format('d M Y') }}</div></div>
            <div><div class="k">Office</div><div class="v">{{ $t->allocation->office->name }}</div></div>
            <div><div class="k">Allocation</div><div class="v"><a href="{{ route('budget.allocations.show', $t->allocation) }}">{{ $t->allocation->reference_no }}</a></div></div>
            <div><div class="k">Commitment</div><div class="v">@if ($t->commitment)<a href="{{ route('budget.commitments.show', $t->commitment) }}">{{ $t->commitment->reference_no }}</a>@else — @endif</div></div>
            <div><div class="k">Status</div><div class="v">@include('budget.partials.status', ['status' => $t->status])</div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            @include('budget.partials.workflow', ['record' => $t, 'type' => 'transaction'])
            <div class="card mb-3">
                <div class="card-header">Details</div>
                <div class="card-body">
                    <dl class="row kv mb-0">
                        <dt class="col-sm-4">Category</dt><dd class="col-sm-8">{{ $t->categoryLabel() }}</dd>
                        <dt class="col-sm-4">Budget Head</dt><dd class="col-sm-8">{{ $t->allocation->headLabel() }}</dd>
                        <dt class="col-sm-4">Payee / Supplier</dt><dd class="col-sm-8">{{ $t->payee ?? '—' }}</dd>
                        <dt class="col-sm-4">Payment Reference</dt><dd class="col-sm-8">{{ $t->payment_reference ?? '—' }}</dd>
                        <dt class="col-sm-4">Activity</dt><dd class="col-sm-8">
                            @if ($t->proposal)
                                {{ $t->proposal->tracking_no }} — {{ $t->proposal->title }}
                                @if ($t->proposal->activity && auth()->user()->can('view', $t->proposal->activity))
                                    · <a href="{{ route('activity.activities.show', [$t->proposal->activity, 'tab' => 'expenses']) }}">open activity</a>
                                @endif
                            @else — @endif
                        </dd>
                        <dt class="col-sm-4">Recorded by</dt><dd class="col-sm-8 mb-0">{{ $t->submitter->name ?? '—' }} on {{ $t->created_at->format('d M Y H:i') }}</dd>
                    </dl>
                </div>
            </div>
            @include('budget.partials.documents', ['record' => $t, 'type' => 'transaction', 'defaultKind' => 'receipt'])
        </div>
        <div class="col-xl-4">
            @include('budget.partials.history', ['approvals' => $t->approvals])
        </div>
    </div>
@endsection
