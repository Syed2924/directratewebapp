@extends('layouts.app')
@section('title', 'Expense Register')

@section('content')
    <div class="page-head">
        <div>
            <h1>Expense Register</h1>
            <p>Actual expenditure with references, receipts and verification status.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @include('budget.partials.fy-picker')
            @can('export', App\Models\FinancialTransaction::class)
                <a href="{{ route('budget.transactions.export', request()->query()) }}" class="btn btn-outline-primary"><i class="bi bi-download me-1"></i>CSV</a>
            @endcan
            @can('budget.record_payment')
                <a href="{{ route('budget.transactions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Record Expense</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([['pending', 'Pending Verification', 'tint-amber', 'bi-hourglass-split'], ['verified', 'Verified', 'tint-green', 'bi-clipboard-check'], ['rejected', 'Rejected (not counted)', 'tint-rose', 'bi-x-octagon']] as [$st, $label, $tint, $icon])
            <div class="col-md-4">
                <a href="{{ route('budget.transactions.index', ['status' => $st] + request()->except('status', 'page')) }}" class="text-reset text-decoration-none">
                    <div class="card stat-card {{ request('status') === $st ? 'border-success' : '' }}">
                        <div class="stat-icon {{ $tint }}"><i class="bi {{ $icon }}"></i></div>
                        <div><div class="label">{{ $label }}</div><div class="value fs-5">@pkr($totals[$st]->total ?? 0)</div><div class="delta text-muted-2">{{ $totals[$st]->n ?? 0 }} entries</div></div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card">
        <form class="card-body border-bottom row g-2" method="GET">
            @if ($fy)<input type="hidden" name="fy" value="{{ $fy->id }}">@endif
            <div class="col-md-4"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Reference, description, payee…" aria-label="Search"></div>
            <div class="col-6 col-md-2">
                <select name="office" class="form-select" aria-label="Office">
                    <option value="">All Offices</option>
                    @foreach ($offices as $o)<option value="{{ $o->id }}" @selected(request('office') == $o->id)>{{ $o->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="category" class="form-select" aria-label="Category">
                    <option value="">All Categories</option>
                    @foreach (config('budget.expense_categories') as $k => $l)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select" aria-label="Status">
                    <option value="">All Status</option>
                    @foreach (['pending', 'verified', 'rejected'] as $st)<option value="{{ $st }}" @selected(request('status') === $st)>{{ config("budget.statuses.$st.0") }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 d-grid"><button class="btn btn-outline-primary">Apply</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Reference</th><th>Date</th><th>Office</th><th>Description</th><th>Category</th><th class="text-end">Amount (PKR)</th><th>Recorded by</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($transactions as $t)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('budget.transactions.show', $t) }}" class="fw-medium">{{ $t->reference_no }}</a></td>
                        <td class="small text-nowrap">{{ $t->txn_date->format('d M Y') }}</td>
                        <td>{{ $t->office->name }}</td>
                        <td>{{ $t->description }}@if ($t->proposal)<div class="small-2 text-muted-2">Activity {{ $t->proposal->tracking_no }}</div>@endif</td>
                        <td class="small">{{ $t->categoryLabel() }}</td>
                        <td class="text-end">{{ number_format($t->amount) }}</td>
                        <td class="small">{{ $t->submitter->name ?? '—' }}</td>
                        <td>@include('budget.partials.status', ['status' => $t->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted-2 py-5">No expenditure matches these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
            <span class="small text-muted-2">Showing {{ $transactions->firstItem() ?? 0 }}–{{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }}</span>
            {{ $transactions->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
