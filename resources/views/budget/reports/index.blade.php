@extends('layouts.app')
@section('title', 'Financial Reports')

@section('content')
    <div class="page-head">
        <div>
            <h1>Financial Reports</h1>
            <p>View and export what was planned, allocated, committed, spent and left available.</p>
        </div>
    </div>

    <div class="card mb-3">
        <form class="card-body row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label small text-muted-2 mb-1" for="type">Report Type</label>
                <select id="type" name="type" class="form-select">
                    @foreach (App\Http\Controllers\Budget\ReportController::TYPES as $k => $l)<option value="{{ $k }}" @selected($type === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted-2 mb-1" for="fy">Financial Year</label>
                <select id="fy" name="fy" class="form-select">
                    @foreach ($years as $y)<option value="{{ $y->id }}" @selected($fy && $fy->id === $y->id)>{{ $y->label }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted-2 mb-1" for="office">Office / District</label>
                <select id="office" name="office" class="form-select">
                    <option value="">All Offices</option>
                    @foreach ($offices as $o)<option value="{{ $o->id }}" @selected(request('office') == $o->id)>{{ $o->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1">Generate Report</button>
                @can('budget.export')
                    @if ($report)<a href="{{ route('budget.reports.export', request()->query() + ['type' => $type, 'fy' => $fy?->id]) }}" class="btn btn-outline-primary" title="Export CSV"><i class="bi bi-download"></i></a>@endif
                @endcan
                <button type="button" class="btn btn-light" onclick="window.print()" title="Print"><i class="bi bi-printer"></i></button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <span>{{ App\Http\Controllers\Budget\ReportController::TYPES[$type] }} {{ $fy ? '— ' . $fy->label : '' }}</span>
            <span class="small fw-normal text-muted-2">Generated {{ now()->format('d M Y H:i') }}</span>
        </div>
        @if (! $report)
            <div class="card-body text-muted-2">No financial year has been set up yet.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr>@foreach ($report['columns'] as [$label, $kind])<th class="{{ $kind !== 'text' ? 'text-end' : '' }}">{{ $label }}{{ $kind === 'money' ? ' (PKR)' : '' }}</th>@endforeach</tr></thead>
                    <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            @foreach ($report['columns'] as $i => [$label, $kind])
                                @if ($kind === 'money')
                                    <td class="text-end {{ $row[$i] < 0 ? 'text-danger' : '' }}">{{ number_format($row[$i]) }}</td>
                                @elseif ($kind === 'pct')
                                    <td class="text-end" style="min-width:140px" data-tip="{{ $label }}: {{ round($row[$i], 1) }}%">
                                        <div class="d-flex align-items-center gap-2 justify-content-end">
                                            <div class="meter flex-grow-1 {{ $row[$i] > 100 ? 'over' : '' }}" style="max-width:80px"><span style="width: {{ min(100, $row[$i]) }}%"></span></div>
                                            <span class="small">{{ round($row[$i]) }}%</span>
                                        </div>
                                    </td>
                                @else
                                    <td>{{ $row[$i] }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($report['columns']) }}" class="text-center text-muted-2 py-5">No data for these filters.</td></tr>
                    @endforelse
                    </tbody>
                    @if ($report['totals'])
                        <tfoot><tr class="fw-semibold">
                            @foreach ($report['columns'] as $i => [$label, $kind])
                                <td class="{{ $kind !== 'text' ? 'text-end' : '' }}">{{ $kind === 'money' ? number_format($report['totals'][$i]) : $report['totals'][$i] }}</td>
                            @endforeach
                        </tr></tfoot>
                    @endif
                </table>
            </div>
        @endif
    </div>
    <p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Allocated, committed and spent are separate measures and are never added together. Spent includes entries pending verification; rejected entries are excluded.</p>
    @include('budget.partials.tooltip')
@endsection
