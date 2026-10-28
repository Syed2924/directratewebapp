@extends('layouts.app')
@section('title', 'Jawan Markaz Reports')

@section('content')
    <div class="page-head">
        <div>
            <h1>Reports</h1>
            <p>Center-wise financial summaries and detailed expense reports.</p>
        </div>
    </div>

    <ul class="nav nav-tabs mb-0">
        <li class="nav-item"><a class="nav-link {{ $type === 'summary' ? 'active' : '' }}" href="{{ route('jawan-markaz.reports.index', ['type' => 'summary'] + request()->except('type')) }}">Center Report</a></li>
        <li class="nav-item"><a class="nav-link {{ $type === 'expenses' ? 'active' : '' }}" href="{{ route('jawan-markaz.reports.index', ['type' => 'expenses'] + request()->except('type')) }}">Expense Report</a></li>
    </ul>
    <div class="card mb-3 border-top-0 rounded-top-0">
        <form class="card-body row g-2 align-items-end" method="GET">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="fy">Financial Year</label>
                <select id="fy" name="fy" class="form-select">@foreach ($years as $y)<option value="{{ $y->id }}" @selected($fy && $fy->id === $y->id)>{{ $y->label }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="district">District</label>
                <select id="district" name="district" class="form-select"><option value="">All</option>@foreach ($districts as $d)<option value="{{ $d->id }}" @selected(request('district') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="center">Center</label>
                <select id="center" name="center" class="form-select"><option value="">All Centers</option>@foreach ($centers as $c)<option value="{{ $c->id }}" @selected(request('center') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            @if ($type === 'expenses')
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="category">Category</label>
                    <select id="category" name="category" class="form-select"><option value="">All</option>@foreach (config('jawan_markaz.expense_categories') as $k => $l)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="status">Approval Status</label>
                    <select id="status" name="status" class="form-select"><option value="">All</option>@foreach (config('jawan_markaz.expense_statuses') as $k => [$l])<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="payment">Payment Status</label>
                    <select id="payment" name="payment" class="form-select"><option value="">All</option>
                        @foreach (['unpaid' => 'Approved, Unpaid', 'partially_paid' => 'Partially Paid', 'paid' => 'Paid'] as $k => $l)<option value="{{ $k }}" @selected(request('payment') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="from">Submitted from</label><input type="date" id="from" name="from" value="{{ request('from') }}" class="form-control"></div>
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="to">to</label><input type="date" id="to" name="to" value="{{ request('to') }}" class="form-control"></div>
            @endif
            <div class="col-md-4 d-flex gap-2 ms-auto">
                <button class="btn btn-primary flex-grow-1">Generate Report</button>
                @can('jawan_markaz.export')<a href="{{ route('jawan-markaz.reports.export', request()->query() + ['type' => $type, 'fy' => $fy?->id]) }}" class="btn btn-outline-primary" title="Export to Excel (CSV)"><i class="bi bi-file-earmark-excel"></i></a>@endcan
                <button type="button" class="btn btn-light" onclick="window.print()" title="Print / Save as PDF"><i class="bi bi-printer"></i></button>
            </div>
        </form>
    </div>

    @if ($summary)
        @php($t = $summary['totals'])
        <div class="row g-3 mb-3">
            @foreach ([['Total Allocated', $t['allocated'], 'tint-green'], ['Total Paid', $t['paid'], 'tint-amber'], ['Approved but Unpaid', $t['unpaid'], 'tint-blue'], ['Remaining Budget', $t['available'], 'tint-purple']] as [$label, $v, $tint])
                <div class="col-6 col-xl-3"><div class="card stat-card"><div class="stat-icon {{ $tint }}"><i class="bi bi-cash-stack"></i></div><div><div class="label">{{ $label }}</div><div class="value fs-5">@pkr($v)</div></div></div></div>
            @endforeach
        </div>
        <div class="card">
            <div class="card-header">Center-wise Summary {{ $fy ? '— ' . $fy->label : '' }}</div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Center</th><th>District</th><th class="text-end">Allocated</th><th class="text-end">Pending Review</th><th class="text-end">Approved</th><th class="text-end">Rejected</th><th class="text-end">Paid</th><th class="text-end">Unpaid</th><th class="text-end">Remaining</th><th class="text-end">% Utilization</th></tr></thead>
                    <tbody>
                    @foreach ($summary['rows'] as $r)
                        @php($util = $r['allocated'] > 0 ? $r['approved'] / $r['allocated'] * 100 : 0)
                        <tr>
                            <td>{{ $r['center']->name }}</td><td>{{ $r['center']->district->name }}</td>
                            <td class="text-end">{{ number_format($r['allocated']) }}</td><td class="text-end">{{ number_format($r['pending']) }}</td>
                            <td class="text-end">{{ number_format($r['approved']) }}</td><td class="text-end">{{ number_format($r['rejected']) }}</td>
                            <td class="text-end">{{ number_format($r['paid']) }}</td><td class="text-end">{{ number_format($r['unpaid']) }}</td>
                            <td class="text-end fw-semibold">{{ number_format($r['available']) }}</td>
                            <td class="text-end" style="min-width:130px" data-tip="Approved {{ round($util, 1) }}% of allocated">
                                <div class="d-flex align-items-center gap-2 justify-content-end"><div class="meter flex-grow-1" style="max-width:70px"><span style="width: {{ min(100, $util) }}%"></span></div><span class="small">{{ round($util) }}%</span></div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr class="fw-semibold"><td colspan="2">Total</td>
                        @foreach (['allocated', 'pending', 'approved', 'rejected', 'paid', 'unpaid', 'available'] as $m)<td class="text-end">{{ number_format($t[$m]) }}</td>@endforeach<td></td></tr></tfoot>
                </table>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-header">Expense Report {{ $fy ? '— ' . $fy->label : '' }} <span class="small fw-normal text-muted-2">({{ $expenses->count() }} expenses)</span></div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Expense ID</th><th>Center</th><th>Date</th><th>Category</th><th>Supplier / Invoice</th><th class="text-end">Requested</th><th class="text-end">Approved</th><th class="text-end">Paid</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($expenses as $e)
                        <tr>
                            <td><a href="{{ route('jawan-markaz.expenses.show', $e) }}">{{ $e->expense_number }}</a></td><td>{{ $e->center->name }}</td>
                            <td class="small text-nowrap">{{ $e->expense_date->format('d M Y') }}</td><td class="small">{{ $e->categoryLabel() }}</td>
                            <td class="small">{{ $e->supplier_name }}<div class="text-muted-2">{{ $e->invoice_number }}</div></td>
                            <td class="text-end">{{ number_format($e->requested_amount) }}</td><td class="text-end">{{ $e->approved_amount ? number_format($e->approved_amount) : '—' }}</td>
                            <td class="text-end">{{ number_format($e->payments->sum('amount')) }}</td>
                            <td>@include('jawan-markaz.partials.status', ['status' => $e->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted-2 py-5">No expenses match these filters.</td></tr>
                    @endforelse
                    </tbody>
                    @if ($expenses->isNotEmpty())
                        <tfoot><tr class="fw-semibold"><td colspan="5">Total</td><td class="text-end">{{ number_format($expenses->sum('requested_amount')) }}</td>
                            <td class="text-end">{{ number_format($expenses->sum('approved_amount')) }}</td><td class="text-end">{{ number_format($expenses->sum(fn ($e) => $e->payments->sum('amount'))) }}</td><td></td></tr></tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif
    @include('budget.partials.tooltip')
@endsection
