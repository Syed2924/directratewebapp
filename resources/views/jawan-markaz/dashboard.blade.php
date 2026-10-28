@extends('layouts.app')
@section('title', 'Jawan Markaz')

@php
    $t = $summary['totals'];
    $c = config('budget.chart');
    $short = fn ($v) => abs($v) >= 1e6 ? rtrim(rtrim(number_format($v / 1e6, 1), '0'), '.') . 'M' : (abs($v) >= 1e3 ? number_format($v / 1e3) . 'K' : number_format($v));
    $pct = fn ($v) => $t['allocated'] > 0 ? round($v / $t['allocated'] * 100) : 0;
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Jawan Markaz Management Dashboard</h1>
            <p>Overview of centers, budgets, expenses and payments{{ auth()->user()->scopeCoverage()->global ? '' : ' for your assigned centers' }}.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @include('budget.partials.fy-picker')
            @can('create', App\Models\JawanMarkazExpense::class)
                <a href="{{ route('jawan-markaz.expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Submit Expense</a>
            @endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['Total Centers', $summary['rows']->count(), $summary['rows']->filter(fn ($r) => $r['center']->is_active)->count() . ' active', 'bi-building', 'tint-green', false],
            ['Total Budget Allocated', $t['allocated'], 'authorized, incl. revisions', 'bi-wallet2', 'tint-purple', true],
            ['Total Paid', $t['paid'], $pct($t['paid']) . '% of allocated', 'bi-cash-coin', 'tint-amber', true],
            ['Pending Approvals', $t['pending_count'], 'PKR ' . number_format($t['pending']) . ' awaiting review', 'bi-hourglass-split', 'tint-blue', false],
        ] as [$label, $value, $sub, $icon, $tint, $money])
            <div class="col-6 col-xl-3">
                <div class="card stat-card kpi">
                    <div class="stat-icon {{ $tint }}"><i class="bi {{ $icon }}"></i></div>
                    <div class="min-w-0"><div class="label">{{ $label }}</div>
                        <div class="value fs-5 text-nowrap">{{ $money ? 'PKR ' . number_format($value) : $value }}</div><div class="sub">{{ $sub }}</div></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mb-3">
        <div class="summary-strip">
            <div><div class="k">Expenses Submitted</div><div class="v">@pkr($submitted)</div></div>
            <div><div class="k">Pending Review</div><div class="v">@pkr($t['pending'])</div></div>
            <div><div class="k">Approved</div><div class="v">@pkr($t['approved'])</div></div>
            <div><div class="k">Rejected</div><div class="v">@pkr($t['rejected'])</div></div>
            <div><div class="k">Paid</div><div class="v">@pkr($t['paid'])</div></div>
            <div><div class="k">Approved but Unpaid</div><div class="v">@pkr($t['unpaid'])</div></div>
            <div><div class="k">Available Budget</div><div class="v {{ $t['available'] < 0 ? 'text-danger' : '' }}">@pkr($t['available'])</div></div>
        </div>
        <div class="px-3 pb-3 small text-muted-2"><i class="bi bi-info-circle me-1"></i>Available = authorized budget − approved expenses (paid or not). Pending and rejected submissions are not spending; approval is not payment.</div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-7">
            <div class="card h-100 viz">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Approved vs Paid (PKR)</span>
                    <span class="viz-key fw-normal"><span><i style="background: {{ $c['planned'] }}"></i>Approved</span><span><i style="background: {{ $c['actual'] }}"></i>Paid</span></span>
                </div>
                <div class="card-body">
                    @php
                        $max = max(1, collect($months)->max(fn ($m) => max($m['approved'], $m['paid'])));
                        $step = 10 ** floor(log10($max)); $niceMax = ceil($max / $step) * $step;
                        [$W, $H, $L, $B, $T] = [720, 220, 44, 22, 8];
                        $plotH = $H - $B - $T; $gw = ($W - $L) / max(1, count($months)); $bw = min(16, ($gw - 10) / 2);
                        $y = fn ($v) => $T + $plotH - ($v / $niceMax * $plotH);
                        $bar = function ($x, $v) use ($y, $T, $plotH, $bw) {
                            $top = $y($v); $base = $T + $plotH; $r = min(4, $bw / 2, max(0, $base - $top));
                            return "M{$x},{$base} V" . ($top + $r) . " Q{$x},{$top} " . ($x + $r) . ",{$top} H" . ($x + $bw - $r) . " Q" . ($x + $bw) . ",{$top} " . ($x + $bw) . "," . ($top + $r) . " V{$base} Z";
                        };
                    @endphp
                    @if ($fy)
                        <svg viewBox="0 0 {{ $W }} {{ $H }}" width="100%" role="img" aria-label="Approved versus paid by month">
                            @foreach ([0, 0.5, 1] as $f)
                                <line x1="{{ $L }}" x2="{{ $W }}" y1="{{ $y($niceMax * $f) }}" y2="{{ $y($niceMax * $f) }}" stroke="var(--viz-grid)"/>
                                <text x="{{ $L - 6 }}" y="{{ $y($niceMax * $f) + 3 }}" text-anchor="end">{{ $short($niceMax * $f) }}</text>
                            @endforeach
                            @foreach (array_values($months) as $i => $m)
                                @php($gx = $L + $i * $gw + ($gw - 2 * $bw - 2) / 2)
                                @if ($m['approved'] > 0)<path d="{{ $bar($gx, $m['approved']) }}" fill="{{ $c['planned'] }}"/>@endif
                                @if ($m['paid'] > 0)<path d="{{ $bar($gx + $bw + 2, $m['paid']) }}" fill="{{ $c['actual'] }}"/>@endif
                                <text x="{{ $L + $i * $gw + $gw / 2 }}" y="{{ $H - 6 }}" text-anchor="middle">{{ $m['label'] }}</text>
                                <rect class="hit" x="{{ $L + $i * $gw }}" y="{{ $T }}" width="{{ $gw }}" height="{{ $plotH }}" data-tip="{{ $m['label'] }} {{ $m['year'] }}&#10;Approved: PKR {{ number_format($m['approved']) }}&#10;Paid: PKR {{ number_format($m['paid']) }}"/>
                            @endforeach
                        </svg>
                        <details class="mt-2 small"><summary class="text-muted-2">View as table</summary>
                            <table class="table table-sm mt-2"><thead><tr><th>Month</th><th class="text-end">Approved</th><th class="text-end">Paid</th></tr></thead>
                                <tbody>@foreach ($months as $m)<tr><td>{{ $m['label'] }} {{ $m['year'] }}</td><td class="text-end">{{ number_format($m['approved']) }}</td><td class="text-end">{{ number_format($m['paid']) }}</td></tr>@endforeach</tbody></table>
                        </details>
                    @else
                        <p class="text-muted-2 small mb-0">No financial year set up yet.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header">Center-wise Spending</div>
                <ul class="list-group list-group-flush">
                    @forelse ($summary['rows'] as $r)
                        @php($ratio = $r['allocated'] > 0 ? $r['paid'] / $r['allocated'] : 0)
                        <li class="list-group-item" data-tip="{{ $r['center']->name }}&#10;Allocated PKR {{ number_format($r['allocated']) }}&#10;Approved PKR {{ number_format($r['approved']) }}&#10;Paid PKR {{ number_format($r['paid']) }}">
                            <div class="d-flex justify-content-between small mb-1"><a href="{{ route('jawan-markaz.centers.show', $r['center']) }}" class="text-reset">{{ $r['center']->name }}</a>
                                <span><strong>{{ $short($r['paid']) }}</strong> <span class="text-muted-2">/ {{ $short($r['allocated']) }} · {{ round($ratio * 100) }}% paid</span></span></div>
                            <div class="meter"><span style="width: {{ min(100, $ratio * 100) }}%"></span></div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted-2 small">No centers in your scope.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header">Recent Activities</div>
                <ul class="list-group list-group-flush">
                    @forelse ($recent as $r)
                        @php($isExpense = $r->approvable instanceof App\Models\JawanMarkazExpense)
                        <li class="list-group-item d-flex justify-content-between gap-2 align-items-center">
                            <div class="min-w-0">
                                <a href="{{ $isExpense ? route('jawan-markaz.expenses.show', $r->approvable) : route('jawan-markaz.budgets.show', $r->approvable) }}" class="small fw-medium">{{ $isExpense ? 'Expense' : 'Center budget' }} {{ $r->approvable->referenceLabel() }}</a>
                                <span class="small text-muted-2"> · {{ $r->approvable->center->name ?? '' }}</span>
                                <div class="small-2 text-muted-2">{{ $r->label() }} by {{ $r->user->name ?? '—' }} · {{ $r->created_at->format('d M Y') }}</div>
                            </div>
                            @if ($isExpense)@include('jawan-markaz.partials.status', ['status' => $r->to_status])@else @include('budget.partials.status', ['status' => $r->to_status])@endif
                        </li>
                    @empty
                        <li class="list-group-item small text-muted-2">Nothing yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">Waiting for You</div>
                <ul class="list-group list-group-flush queue-list">
                    @forelse ($queue as [$label, $n, $url])
                        <li class="list-group-item"><span class="badge rounded-pill text-bg-warning">{{ $n }}</span><a href="{{ $url }}" class="small">{{ $label }}</a></li>
                    @empty
                        <li class="list-group-item small text-muted-2">Nothing waiting for you.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    @include('budget.partials.tooltip')
@endsection
