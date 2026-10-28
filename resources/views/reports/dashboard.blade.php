@extends('layouts.app')
@section('title', 'Report Dashboard')

@php
    $b = $budget;
    $h = $hostel;
    $occ = $h['today']['rooms'] ? round($h['today']['occupied'] / $h['today']['rooms'] * 100) : 0;
    $link = fn ($key) => route('reports.show', [$key, 'fy' => $fy?->id]);
    $c = config('budget.chart');
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Report Management Dashboard</h1>
            <p>Consolidated view of activities, finances, Jawan Markaz and hostel information{{ auth()->user()->scopeCoverage()->global ? '' : ' within your access scope' }}.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @include('budget.partials.fy-picker')
            <a href="{{ route('reports.index') }}" class="btn btn-primary"><i class="bi bi-file-earmark-bar-graph me-1"></i>All Reports</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['Activity Summary', 'bi-calendar-event', 'tint-blue', 'activity_summary', [['Total Proposals', $activity['total']], ['Approved', $activity['approved']], ['Pending', $activity['pending']], ['Completed', $activity['completed']]]],
            ['Financial Summary', 'bi-wallet2', 'tint-green', 'budget_directorate', array_values(array_filter([
                $b['revised'] !== null ? ['Approved Budget', 'PKR ' . number_format($b['revised'])] : null, ['Allocated', 'PKR ' . number_format($b['allocated'])],
                ['Commitments', 'PKR ' . number_format($b['committed'])], ['Actual Expenditure', 'PKR ' . number_format($b['spent'])],
                ['Remaining', 'PKR ' . number_format($b['unallocated'] !== null ? $b['revised'] - $b['spent'] - $b['committed'] : $b['available'])]]))],
            ['Jawan Markaz Summary', 'bi-building', 'tint-purple', 'jm_financial', [['Centers', $jm['centers']], ['Allocated Budgets', 'PKR ' . number_format($jm['allocated'])], ['Pending Expenses', $jm['pending_count']], ['Approved', 'PKR ' . number_format($jm['approved'])], ['Payments', 'PKR ' . number_format($jm['paid'])]]],
            ['Hostel Summary', 'bi-house-door', 'tint-amber', 'hostel_occupancy', [['Hostels', $h['hostels']], ['Total Rooms', $h['today']['rooms']], ['Approved Bookings', $h['approved']], ['Occupancy Today', $occ . '%'], ['Available Rooms', $h['today']['free']], ['Hostel Expenditure', 'PKR ' . number_format($h['spent'])]]],
        ] as [$title, $icon, $tint, $report, $items])
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2"><span class="stat-icon {{ $tint }}" style="width:34px;height:34px;flex-basis:34px;font-size:.95rem"><i class="bi {{ $icon }}"></i></span><span class="fw-semibold">{{ $title }}</span></div>
                        <ul class="list-unstyled small mb-2">
                            @foreach ($items as [$label, $value])
                                <li class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted-2">{{ $label }}</span><strong>{{ $value }}</strong></li>
                            @endforeach
                        </ul>
                        <a href="{{ $link($report) }}" class="small">Detailed report <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($months)
        <div class="row g-3">
            <div class="col-xl-6">
                @include('reports.partials.bars', ['chart' => ['title' => 'Activity Overview — proposals received vs approved', 'series' => ['Received', 'Approved'], 'money' => false,
                    'rows' => collect($months)->map(fn ($m) => [$m['label'], [$m['data']['proposals'], $m['data']['approved']]])->all()]])
            </div>
            <div class="col-xl-6">
                @include('reports.partials.bars', ['chart' => ['title' => 'Monthly Expenditure — total vs Jawan Markaz + hostels', 'series' => ['Total expenditure', 'JM + hostel payments'], 'money' => true,
                    'rows' => collect($months)->map(fn ($m) => [$m['label'], [$m['data']['expenditure'], $m['data']['jm_paid'] + $m['data']['hostel_paid']]])->all()]])
            </div>
            <div class="col-xl-6">
                <div class="card h-100 viz">
                    <div class="card-header">Budget Utilization</div>
                    <div class="card-body">
                        @php
                            $segments = [['Spent', max(0, $b['spent']), $c['spent']], ['Committed', max(0, $b['committed']), $c['committed']], ['Available in allocations', max(0, $b['available']), $c['available']]];
                            if ($b['unallocated'] !== null) { $segments[] = ['Not yet allocated', max(0, $b['unallocated']), $c['unallocated']]; }
                            $whole = max(1, array_sum(array_column($segments, 1)));
                        @endphp
                        <div class="stack-bar" role="img" aria-label="Budget utilization">
                            @foreach ($segments as [$label, $v, $color])@if ($v > 0)<span style="width: {{ $v / $whole * 100 }}%; background: {{ $color }}" data-tip="{{ $label }}: PKR {{ number_format($v) }}"></span>@endif @endforeach
                        </div>
                        <ul class="viz-legend">
                            @foreach ($segments as [$label, $v, $color])
                                <li><span class="sw" style="background: {{ $color }}"></span><div><div class="lk">{{ $label }}</div><div class="lv">PKR {{ number_format($v) }} <span class="fw-normal small text-muted-2">{{ round($v / $whole * 100) }}%</span></div></div></li>
                            @endforeach
                        </ul>
                        <a href="{{ $link('budget_utilization') }}" class="btn btn-sm btn-outline-primary mt-3">View Financial Report</a>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card h-100 viz">
                    <div class="card-header">Hostel Occupancy Today</div>
                    <div class="card-body">
                        @php($t = $h['today'])
                        <div class="d-flex justify-content-between align-items-end mb-2">
                            <div><div class="display-6 fw-bold lh-1">{{ $occ }}%</div><div class="small text-muted-2">of {{ $t['rooms'] }} bookable rooms occupied</div></div>
                            <div class="text-end"><div class="fs-4 fw-bold">{{ $t['guests'] }}</div><div class="small text-muted-2">guests staying</div></div>
                        </div>
                        @php($whole = max(1, $t['rooms']))
                        <div class="stack-bar" role="img" aria-label="Hostel occupancy">
                            @foreach ([['Occupied', $t['occupied'], $c['spent']], ['Reserved', $t['reserved'], $c['committed']], ['Free', $t['free'], $c['available']]] as [$label, $v, $color])
                                @if ($v > 0)<span style="width: {{ $v / $whole * 100 }}%; background: {{ $color }}" data-tip="{{ $label }}: {{ $v }} rooms"></span>@endif
                            @endforeach
                        </div>
                        <ul class="viz-legend">
                            @foreach ([['Occupied (checked in)', $t['occupied'], $c['spent']], ['Reserved (approved)', $t['reserved'], $c['committed']], ['Free', $t['free'], $c['available']]] as [$label, $v, $color])
                                <li><span class="sw" style="background: {{ $color }}"></span><div><div class="lk">{{ $label }}</div><div class="lv">{{ $v }} <span class="fw-normal small text-muted-2">rooms</span></div></div></li>
                            @endforeach
                        </ul>
                        <a href="{{ $link('hostel_occupancy') }}" class="btn btn-sm btn-outline-primary mt-3">View Occupancy Report</a>
                    </div>
                </div>
            </div>
        </div>
    @endif
    @include('budget.partials.tooltip')
@endsection
