@extends('layouts.app')
@section('title', 'Commitments')

@section('content')
    <div class="page-head">
        <div>
            <h1>Commitments</h1>
            <p>Funds reserved for authorized obligations that are not yet fully paid.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @include('budget.partials.fy-picker')
            @can('create', App\Models\BudgetCommitment::class)
                <a href="{{ route('budget.commitments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Commitment</a>
            @endcan
        </div>
    </div>

    <div class="status-chips mb-3">
        <a href="{{ route('budget.commitments.index', request()->except('status', 'page')) }}" class="{{ request('status') ? '' : 'active' }}">All</a>
        @foreach (['open', 'closed', 'cancelled'] as $st)
            <a href="{{ route('budget.commitments.index', ['status' => $st] + request()->except('status', 'page')) }}" class="{{ request('status') === $st ? 'active' : '' }}">{{ config("budget.statuses.$st.0") }}</a>
        @endforeach
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Reference</th><th>Date</th><th>Office</th><th>Description</th><th>Authority</th><th class="text-end">Committed</th><th class="text-end">Spent</th><th class="text-end">Outstanding</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($commitments as $c)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('budget.commitments.show', $c) }}" class="fw-medium">{{ $c->reference_no }}</a></td>
                        <td class="small text-nowrap">{{ $c->committed_on->format('d M Y') }}</td>
                        <td>{{ $c->office->name }}</td>
                        <td>{{ $c->description }}@if ($c->proposal)<div class="small-2 text-muted-2">Activity {{ $c->proposal->tracking_no }}</div>@endif</td>
                        <td class="small">{{ $c->authority_reference ?? '—' }}</td>
                        <td class="text-end">{{ number_format($c->amount) }}</td>
                        <td class="text-end">{{ number_format($c->spent()) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($c->outstanding()) }}</td>
                        <td>@include('budget.partials.status', ['status' => $c->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted-2 py-5">No commitments.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
            <span class="small text-muted-2">Showing {{ $commitments->firstItem() ?? 0 }}–{{ $commitments->lastItem() ?? 0 }} of {{ $commitments->total() }}</span>
            {{ $commitments->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
