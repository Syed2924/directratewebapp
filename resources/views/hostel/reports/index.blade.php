@extends('layouts.app')
@section('title', 'Hostel Reports')

@section('content')
    <div class="page-head">
        <div>
            <h1>Hostel Reports</h1>
            <p>Booking, occupancy and financial reports. Guest identity numbers are masked in reports and exports.</p>
        </div>
    </div>

    <ul class="nav nav-tabs">
        @foreach (App\Http\Controllers\Hostel\ReportController::TYPES as $k => $l)
            <li class="nav-item"><a class="nav-link {{ $type === $k ? 'active' : '' }}" href="{{ route('hostel.reports.index', ['type' => $k] + request()->except('type')) }}">{{ $l }}</a></li>
        @endforeach
    </ul>
    <div class="card mb-3 border-top-0 rounded-top-0">
        <form class="card-body row g-2 align-items-end" method="GET">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="hostel">Hostel</label>
                <select id="hostel" name="hostel" class="form-select"><option value="">All</option>@foreach ($hostels as $h)<option value="{{ $h->id }}" @selected(request('hostel') == $h->id)>{{ $h->name }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="district">District</label>
                <select id="district" name="district" class="form-select"><option value="">All</option>@foreach ($districts as $d)<option value="{{ $d->id }}" @selected(request('district') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
            @if ($type === 'financial')
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="fy">Financial Year</label>
                    <select id="fy" name="fy" class="form-select">@foreach ($years as $y)<option value="{{ $y->id }}" @selected(request('fy', App\Models\FinancialYear::current()?->id) == $y->id)>{{ $y->label }}</option>@endforeach</select></div>
            @else
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="from">From</label><input type="date" id="from" name="from" value="{{ $from->toDateString() }}" class="form-control"></div>
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="to">To</label><input type="date" id="to" name="to" value="{{ $to->toDateString() }}" class="form-control"></div>
            @endif
            @if ($type === 'bookings')
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="status">Status</label>
                    <select id="status" name="status" class="form-select"><option value="">All</option>@foreach (config('hostel.booking_statuses') as $k => [$l])<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
            @endif
            <div class="col-md-2 d-flex gap-2 ms-auto">
                <button class="btn btn-primary flex-grow-1">Generate</button>
                @can('hostel.export')<a href="{{ route('hostel.reports.export', request()->query() + ['type' => $type]) }}" class="btn btn-outline-primary" title="Export to Excel (CSV)"><i class="bi bi-file-earmark-excel"></i></a>@endcan
                <button type="button" class="btn btn-light" onclick="window.print()" title="Print / Save as PDF"><i class="bi bi-printer"></i></button>
            </div>
        </form>
    </div>

    @if ($type === 'bookings')
        <div class="row g-3 mb-3">
            @foreach ([['Total Bookings', $report['statusCounts']->sum(), 'tint-blue'], ['Approved / Stayed', ($report['statusCounts']['approved'] ?? 0) + ($report['statusCounts']['checked_in'] ?? 0) + ($report['statusCounts']['checked_out'] ?? 0), 'tint-green'], ['Pending', $report['statusCounts']['pending'] ?? 0, 'tint-amber'], ['Rejected / Cancelled', ($report['statusCounts']['rejected'] ?? 0) + ($report['statusCounts']['cancelled'] ?? 0), 'tint-rose']] as [$l, $v, $tint])
                <div class="col-6 col-xl-3"><div class="card stat-card"><div class="stat-icon {{ $tint }}"><i class="bi bi-journal-text"></i></div><div><div class="label">{{ $l }}</div><div class="value">{{ $v }}</div></div></div></div>
            @endforeach
        </div>
    @endif

    <div class="card">
        <div class="card-header">{{ App\Http\Controllers\Hostel\ReportController::TYPES[$type] }} <span class="small fw-normal text-muted-2">({{ count($report['rows']) }} rows)</span></div>
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead><tr>@foreach ($report['head'] as $i => $h)<th class="{{ in_array($i, $report['money'] ?? []) || ($report['pct'] ?? null) === $i ? 'text-end' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse ($report['rows'] as $row)
                    <tr>
                        @foreach ($row as $i => $v)
                            @if (in_array($i, $report['money'] ?? []))
                                <td class="text-end">{{ number_format($v) }}</td>
                            @elseif (($report['pct'] ?? null) === $i)
                                <td class="text-end" style="min-width:130px"><div class="d-flex align-items-center gap-2 justify-content-end"><div class="meter flex-grow-1" style="max-width:70px"><span style="width: {{ min(100, $v) }}%"></span></div><span class="small">{{ $v }}%</span></div></td>
                            @else
                                <td class="small">{{ $v }}</td>
                            @endif
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($report['head']) }}" class="text-center text-muted-2 py-5">No data for these filters.</td></tr>
                @endforelse
                </tbody>
                @if (! empty($report['money']) && $report['rows'])
                    <tfoot><tr class="fw-semibold">@foreach ($report['head'] as $i => $h)<td class="text-end">{{ $i === 0 ? 'Total' : (in_array($i, $report['money']) ? number_format(array_sum(array_column($report['rows'], $i))) : '') }}</td>@endforeach</tr></tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
