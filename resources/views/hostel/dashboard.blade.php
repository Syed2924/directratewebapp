@extends('layouts.app')
@section('title', 'Hostel Booking')

@php
    $c = config('budget.chart');
    $total = $counts->except('draft')->sum();
    $approved = ($counts['approved'] ?? 0) + ($counts['checked_in'] ?? 0) + ($counts['checked_out'] ?? 0);
    $short = fn ($v) => abs($v) >= 1e6 ? rtrim(rtrim(number_format($v / 1e6, 1), '0'), '.') . 'M' : (abs($v) >= 1e3 ? number_format($v / 1e3) . 'K' : number_format($v));
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Hostel Booking Dashboard</h1>
            <p>Bookings, occupancy and financial status{{ request('hostel') ? ' for ' . optional($hostels->firstWhere('id', (int) request('hostel')))->name : ' across all hostels in your scope' }}.</p>
        </div>
        <form method="GET" class="d-flex gap-2 align-items-center">
            <select name="hostel" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()" aria-label="Hostel">
                <option value="">All Hostels</option>
                @foreach ($hostels as $h)<option value="{{ $h->id }}" @selected(request('hostel') == $h->id)>{{ $h->name }}</option>@endforeach
            </select>
            <select name="fy" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()" aria-label="Financial year">
                @foreach ($years as $y)<option value="{{ $y->id }}" @selected($fy && $fy->id === $y->id)>{{ $y->label }}</option>@endforeach
            </select>
            @can('create', App\Models\HostelBooking::class)<a href="{{ route('hostel.bookings.create') }}" class="btn btn-primary btn-sm text-nowrap"><i class="bi bi-plus-lg me-1"></i>New Booking</a>@endcan
        </form>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['Total Booking Requests', $total, 'submitted in ' . ($fy->label ?? '—'), 'bi-journal-text', 'tint-blue'],
            ['Approved Bookings', $approved, 'confirmed reservations', 'bi-check2-circle', 'tint-green'],
            ['Pending Requests', $counts['pending'] ?? 0, 'not yet confirmed', 'bi-hourglass-split', 'tint-amber'],
            ['Rejected / Cancelled', ($counts['rejected'] ?? 0) + ($counts['cancelled'] ?? 0), ($counts['rejected'] ?? 0) . ' rejected · ' . ($counts['cancelled'] ?? 0) . ' cancelled', 'bi-x-octagon', 'tint-rose'],
        ] as [$label, $value, $sub, $icon, $tint])
            <div class="col-6 col-xl-3">
                <div class="card stat-card kpi"><div class="stat-icon {{ $tint }}"><i class="bi {{ $icon }}"></i></div>
                    <div><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div><div class="sub">{{ $sub }}</div></div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-5">
            <div class="card h-100 viz">
                <div class="card-header">Occupancy Today <span class="small fw-normal text-muted-2">{{ now()->format('d M Y') }}</span></div>
                <div class="card-body">
                    @php
                        $t = $today;
                        $segments = [['Occupied (checked in)', $t['occupied'], $c['spent']], ['Reserved (approved)', $t['reserved'], $c['committed']], ['Free', $t['free'], $c['available']]];
                        $whole = max(1, $t['rooms']);
                    @endphp
                    <div class="d-flex justify-content-between align-items-end mb-2">
                        <div><div class="display-6 fw-bold lh-1">{{ $t['rooms'] ? round($t['occupied'] / $t['rooms'] * 100) : 0 }}%</div><div class="small text-muted-2">of {{ $t['rooms'] }} bookable rooms occupied</div></div>
                        <div class="text-end"><div class="fs-4 fw-bold">{{ $t['guests'] }}</div><div class="small text-muted-2">guests staying</div></div>
                    </div>
                    <div class="stack-bar" role="img" aria-label="Room occupancy today">
                        @foreach ($segments as [$label, $v, $color])
                            @if ($v > 0)<span style="width: {{ $v / $whole * 100 }}%; background: {{ $color }}" data-tip="{{ $label }}: {{ $v }} room(s)"></span>@endif
                        @endforeach
                    </div>
                    <ul class="viz-legend">
                        @foreach ($segments as [$label, $v, $color])
                            <li><span class="sw" style="background: {{ $color }}"></span><div><div class="lk">{{ $label }}</div><div class="lv">{{ $v }} <span class="fw-normal small text-muted-2">rooms</span></div></div></li>
                        @endforeach
                    </ul>
                    <p class="small text-muted-2 mt-3 mb-0">Requests are not reservations, and reservations are not occupancy: pending requests hold no rooms.</p>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card h-100 viz">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Booking Trend (by check-in month)</span>
                    <span class="viz-key fw-normal"><span><i style="background: {{ $c['planned'] }}"></i>Requested</span><span><i style="background: {{ $c['actual'] }}"></i>Confirmed</span></span>
                </div>
                <div class="card-body">
                    @php
                        $max = max(1, collect($months)->max('requested'));
                        [$W, $H, $L, $B, $T] = [720, 200, 30, 22, 8];
                        $plotH = $H - $B - $T; $gw = ($W - $L) / max(1, count($months)); $bw = min(16, ($gw - 10) / 2);
                        $y = fn ($v) => $T + $plotH - ($v / $max * $plotH);
                        $bar = function ($x, $v) use ($y, $T, $plotH, $bw) {
                            $top = $y($v); $base = $T + $plotH; $r = min(4, $bw / 2, max(0, $base - $top));
                            return "M{$x},{$base} V" . ($top + $r) . " Q{$x},{$top} " . ($x + $r) . ",{$top} H" . ($x + $bw - $r) . " Q" . ($x + $bw) . ",{$top} " . ($x + $bw) . "," . ($top + $r) . " V{$base} Z";
                        };
                    @endphp
                    <svg viewBox="0 0 {{ $W }} {{ $H }}" width="100%" role="img" aria-label="Bookings requested and confirmed by month">
                        @foreach ([0, 0.5, 1] as $f)
                            <line x1="{{ $L }}" x2="{{ $W }}" y1="{{ $y($max * $f) }}" y2="{{ $y($max * $f) }}" stroke="var(--viz-grid)"/>
                            <text x="{{ $L - 6 }}" y="{{ $y($max * $f) + 3 }}" text-anchor="end">{{ round($max * $f) }}</text>
                        @endforeach
                        @foreach (array_values($months) as $i => $m)
                            @php($gx = $L + $i * $gw + ($gw - 2 * $bw - 2) / 2)
                            @if ($m['requested'] > 0)<path d="{{ $bar($gx, $m['requested']) }}" fill="{{ $c['planned'] }}"/>@endif
                            @if ($m['confirmed'] > 0)<path d="{{ $bar($gx + $bw + 2, $m['confirmed']) }}" fill="{{ $c['actual'] }}"/>@endif
                            <text x="{{ $L + $i * $gw + $gw / 2 }}" y="{{ $H - 6 }}" text-anchor="middle">{{ $m['label'] }}</text>
                            <rect class="hit" x="{{ $L + $i * $gw }}" y="{{ $T }}" width="{{ $gw }}" height="{{ $plotH }}" data-tip="{{ $m['label'] }} {{ $m['year'] }}&#10;Requested: {{ $m['requested'] }}&#10;Confirmed: {{ $m['confirmed'] }}"/>
                        @endforeach
                    </svg>
                    <details class="mt-2 small"><summary class="text-muted-2">View as table</summary>
                        <table class="table table-sm mt-2"><thead><tr><th>Month</th><th class="text-end">Requested</th><th class="text-end">Confirmed</th></tr></thead>
                            <tbody>@foreach ($months as $m)<tr><td>{{ $m['label'] }} {{ $m['year'] }}</td><td class="text-end">{{ $m['requested'] }}</td><td class="text-end">{{ $m['confirmed'] }}</td></tr>@endforeach</tbody></table>
                    </details>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header">Financial Summary — {{ $fy->label ?? '' }}</div>
                <ul class="list-group list-group-flush small">
                    @foreach ([
                        ['Total approved budget (original)', $finance['original']], ['Authorized budget revisions', $finance['revisions']], ['Revised budget', $finance['allocated']],
                        ['Recorded expenditure (paid)', $finance['paid']], ['Outstanding commitments (approved, unpaid)', $finance['unpaid']],
                        ['Remaining budget', $finance['remaining']], ['Available to commit', $finance['available']],
                    ] as [$label, $v])
                        <li class="list-group-item d-flex justify-content-between {{ in_array($label, ['Revised budget', 'Available to commit']) ? 'fw-semibold' : '' }}"><span>{{ $label }}</span><span>@pkr($v)</span></li>
                    @endforeach
                </ul>
                <div class="card-body border-top">
                    @php($spentPct = $finance['allocated'] > 0 ? $finance['paid'] / $finance['allocated'] * 100 : 0)
                    <div class="d-flex justify-content-between small mb-1"><span>Spent</span><span>{{ round($spentPct) }}%</span></div>
                    <div class="meter"><span style="width: {{ min(100, $spentPct) }}%"></span></div>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">Recent Bookings <a href="{{ route('hostel.bookings.index') }}" class="small fw-normal">View all bookings</a></div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Booking ID</th><th>Guest Name</th><th>Hostel</th><th>Check-in</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($recent as $b)
                            <tr>
                                <td><a href="{{ route('hostel.bookings.show', $b) }}" class="fw-medium">{{ $b->booking_no }}</a></td>
                                <td>{{ $b->guest_name }}</td><td class="small">{{ $b->hostel->name }}</td>
                                <td class="small text-nowrap">{{ $b->check_in->format('d M Y') }}</td>
                                <td>@include('hostel.partials.status', ['status' => $b->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted-2 py-4">No bookings yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @include('budget.partials.tooltip')
@endsection
