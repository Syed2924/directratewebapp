@extends('layouts.app')
@section('title', 'Plan ' . $plan->reference_no)

@section('content')
    <div class="page-head">
        <div>
            <h1>Budget Plan {{ $plan->reference_no }}</h1>
            <p>{{ $plan->office->name }} · {{ $plan->quarterLabel() }} · {{ $plan->financialYear->label }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('budget.plans.index', ['fy' => $plan->financial_year_id]) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Plans</a>
            @can('update', $plan)<a href="{{ route('budget.plans.edit', $plan) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            @include('budget.partials.workflow', ['record' => $plan, 'type' => 'plan'])
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">Plan Details @include('budget.partials.status', ['status' => $plan->status])</div>
                <div class="card-body">
                    <dl class="row kv mb-0">
                        <dt class="col-sm-4">Planned Amount</dt><dd class="col-sm-8 fw-semibold">@pkr($plan->planned_amount)</dd>
                        <dt class="col-sm-4">Program / Category</dt><dd class="col-sm-8">{{ $plan->categoryLabel() }}</dd>
                        <dt class="col-sm-4">Budget Head</dt><dd class="col-sm-8">{{ $plan->headLabel() }}</dd>
                        <dt class="col-sm-4">Office</dt><dd class="col-sm-8">{{ $plan->office->name }} ({{ $plan->office->district->name }})</dd>
                        <dt class="col-sm-4">Expected Expenditure</dt><dd class="col-sm-8">{{ $plan->expected_on?->format('d M Y') ?? '—' }}</dd>
                        <dt class="col-sm-4">Linked Proposal</dt><dd class="col-sm-8">
                            @if ($plan->proposal)
                                @can('view', $plan->proposal)
                                    <a href="{{ route('activity.proposals.show', $plan->proposal) }}">{{ $plan->proposal->tracking_no }}</a>
                                @else
                                    {{ $plan->proposal->tracking_no }}
                                @endcan
                                — {{ $plan->proposal->title }}
                            @else — @endif
                        </dd>
                        <dt class="col-sm-4">Justification</dt><dd class="col-sm-8">{{ $plan->justification ?? '—' }}</dd>
                        <dt class="col-sm-4">Approval Reference</dt><dd class="col-sm-8 mb-0">{{ $plan->approval_reference ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
            @include('budget.partials.documents', ['record' => $plan, 'type' => 'plan'])
        </div>
        <div class="col-xl-4">
            @include('budget.partials.history', ['approvals' => $plan->approvals])
        </div>
    </div>
@endsection
