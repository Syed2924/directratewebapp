@extends('layouts.app')
@section('title', 'Budget Planning')

@section('content')
    <div class="page-head">
        <div>
            <h1>Budget Planning</h1>
            <p>How offices intend to spend over the year — by quarter, program and budget head.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @include('budget.partials.fy-picker')
            @can('create', App\Models\BudgetPlan::class)
                <a href="{{ route('budget.plans.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Plan</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach (config('budget.quarters') as $q => $label)
            <div class="col-6 col-xl-3">
                <div class="card stat-card">
                    <div class="stat-icon tint-blue"><i class="bi bi-calendar3"></i></div>
                    <div><div class="label">{{ $label }}</div><div class="value fs-5">@pkr($byQuarter[$q] ?? 0)</div><div class="delta text-muted-2">planned</div></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <form class="card-body border-bottom row g-2" method="GET">
            @if ($fy)<input type="hidden" name="fy" value="{{ $fy->id }}">@endif
            <div class="col-md-4">
                <select name="office" class="form-select" aria-label="Office" onchange="this.form.submit()">
                    <option value="">All Offices</option>
                    @foreach ($offices as $o)<option value="{{ $o->id }}" @selected(request('office') == $o->id)>{{ $o->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="quarter" class="form-select" aria-label="Quarter" onchange="this.form.submit()">
                    <option value="">All Quarters</option>
                    @foreach (config('budget.quarters') as $q => $l)<option value="{{ $q }}" @selected(request('quarter') == $q)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select" aria-label="Status" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    @foreach (['draft', 'submitted', 'approved', 'returned', 'rejected'] as $st)<option value="{{ $st }}" @selected(request('status') === $st)>{{ config("budget.statuses.$st.0") }}</option>@endforeach
                </select>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Reference</th><th>Quarter</th><th>Office</th><th>Program / Category</th><th>Budget Head</th><th>Expected</th><th>Linked Proposal</th><th class="text-end">Planned (PKR)</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($plans as $p)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('budget.plans.show', $p) }}" class="fw-medium">{{ $p->reference_no }}</a></td>
                        <td class="small">{{ $p->quarterLabel() }}</td>
                        <td>{{ $p->office->name }}</td>
                        <td>{{ $p->categoryLabel() }}</td>
                        <td class="small">{{ $p->headLabel() }}</td>
                        <td class="small text-nowrap">{{ $p->expected_on?->format('d M Y') ?? '—' }}</td>
                        <td class="small">{{ $p->proposal->tracking_no ?? '—' }}</td>
                        <td class="text-end">{{ number_format($p->planned_amount) }}</td>
                        <td>@include('budget.partials.status', ['status' => $p->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted-2 py-5">No budget plans for these filters.</td></tr>
                @endforelse
                </tbody>
                @if ($plans->isNotEmpty())
                    <tfoot><tr class="fw-semibold"><td colspan="7">Total (excluding rejected)</td><td class="text-end">{{ number_format($byQuarter->sum()) }}</td><td></td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>
    <p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Planning shows what is intended; it does not reserve funds. Funds are reserved by commitments and assigned by allocations.</p>
@endsection
