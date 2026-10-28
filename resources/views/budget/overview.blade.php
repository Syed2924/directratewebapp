@extends('layouts.app')
@section('title', 'Budget & Planning')

@php
    $s = $summary;
    $c = config('budget.chart');
    $short = fn ($v) => abs($v) >= 1e6 ? rtrim(rtrim(number_format($v / 1e6, 1), '0'), '.') . 'M' : (abs($v) >= 1e3 ? number_format($v / 1e3) . 'K' : number_format($v));
    $base = $s['revised'] ?? $s['allocated'];
    $pct = fn ($v) => $base > 0 ? round($v / $base * 100) : 0;
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Budget &amp; Planning Dashboard</h1>
            <p>Overview of financial status, allocations and spending{{ $s['global'] ? '' : ' for the offices in your scope' }}.</p>
        </div>
        @include('budget.partials.fy-picker')
    </div>

    @unless ($fy)
        <div class="card"><div class="card-body text-center py-5">
            <i class="bi bi-wallet2 fs-2 text-primary d-block mb-2"></i>
            <p class="mb-3">No financial year has been set up yet. Start by registering the Directorate's approved budget.</p>
            @can('create', App\Models\Budget::class)<a href="{{ route('budget.budgets.create') }}" class="btn btn-primary">Register Directorate Budget</a>@endcan
        </div></div>
    @else
        @if ($s['global'] && (! $s['budget'] || ! $s['budget']->isApproved()))
            <div class="alert alert-warning">The {{ $fy->label }} Directorate budget is {{ $s['budget'] ? strtolower($s['budget']->statusLabel()) : 'not registered yet' }}. Allocations can be approved once it is approved.
                @if ($s['budget'])<a href="{{ route('budget.budgets.show', $s['budget']) }}" class="alert-link">Open budget</a>@endif</div>
        @endif

        <div class="row g-3 mb-3">
            @foreach ([
                [$s['global'] ? 'Approved Budget (revised)' : 'Allocated to my offices', $s['global'] ? $s['revised'] : $s['allocated'], $s['global'] ? ($s['budget'] ? 'Original ' . $short($s['budget']->original_amount) : '—') : 'approved allocations', 'bi-bank', 'tint-green'],
                ['Allocated', $s['allocated'], $s['global'] ? $pct($s['allocated']) . '% of budget' : 'incl. transfers', 'bi-diagram-3', 'tint-blue'],
                ['Committed', $s['committed'], $pct($s['committed']) . '% — reserved, not yet spent', 'bi-bookmark-check', 'tint-amber'],
                ['Actual Expenditure', $s['spent'], $pct($s['spent']) . '% of ' . ($s['global'] ? 'budget' : 'allocation'), 'bi-receipt', 'tint-purple'],
            ] as [$label, $value, $sub, $icon, $tint])
                <div class="col-6 col-xl-3">
                    <div class="card stat-card kpi">
                        <div class="stat-icon {{ $tint }}"><i class="bi {{ $icon }}"></i></div>
                        <div class="min-w-0">
                            <div class="label">{{ $label }}</div>
                            <div class="value fs-5 text-nowrap">{{ $value === null ? '—' : 'PKR ' . number_format($value) }}</div>
                            <div class="sub">{{ $sub }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3 mb-3">
            {{-- Part-to-whole: where the budget stands --}}
            <div class="col-xl-5">
                <div class="card h-100 viz">
                    <div class="card-header">Budget Position</div>
                    <div class="card-body">
                        @php
                            $segments = [
                                ['Spent', max(0, $s['spent']), $c['spent'], 'Recorded expenditure'],
                                ['Committed', max(0, $s['committed']), $c['committed'], 'Reserved for approved obligations'],
                                ['Available in allocations', max(0, $s['available']), $c['available'], 'Allocated, free to commit'],
                            ];
                            if ($s['unallocated'] !== null) {
                                $segments[] = ['Not yet allocated', max(0, $s['unallocated']), $c['unallocated'], 'Budget not assigned to any office'];
                            }
                            $whole = array_sum(array_column($segments, 1));
                        @endphp
                        @if ($whole > 0)
                            <div class="small text-muted-2 mb-2">{{ $s['unallocated'] !== null ? 'Revised budget' : 'Allocated' }}: <strong class="text-reset">@pkr($whole)</strong></div>
                            <div class="stack-bar" role="img" aria-label="Budget position bar">
                                @foreach ($segments as [$label, $value, $color, $hint])
                                    @if ($value > 0)
                                        <span style="width: {{ $value / $whole * 100 }}%; background: {{ $color }}" data-tip="{{ $label }}: PKR {{ number_format($value) }} ({{ round($value / $whole * 100, 1) }}%)"></span>
                                    @endif
                                @endforeach
                            </div>
                            <ul class="viz-legend">
                                @foreach ($segments as [$label, $value, $color, $hint])
                                    <li><span class="sw" style="background: {{ $color }}"></span>
                                        <div><div class="lk">{{ $label }}</div><div class="lv">@pkr($value) <span class="fw-normal text-muted-2 small">{{ round($value / $whole * 100) }}%</span></div><div class="small-2 text-muted-2">{{ $hint }}</div></div></li>
                                @endforeach
                            </ul>
                            @if ($s['available'] < 0)<div class="alert alert-danger py-2 small mt-3 mb-0">Spending and commitments exceed allocations by @pkr(-$s['available']).</div>@endif
                        @else
                            <p class="text-muted-2 small mb-0">No approved allocations for {{ $fy->label }} yet.</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Change over time: planned vs actual per month --}}
            <div class="col-xl-7">
                <div class="card h-100 viz">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Planned vs Actual Spending (PKR)</span>
                        <span class="viz-key fw-normal"><span><i style="background: {{ $c['planned'] }}"></i>Planned</span><span><i style="background: {{ $c['actual'] }}"></i>Actual</span></span>
                    </div>
                    <div class="card-body">
                        @php
                            $max = max(1, collect($months)->max(fn ($m) => max($m['planned'], $m['actual'])));
                            $step = 10 ** floor(log10($max)); $niceMax = ceil($max / $step) * $step;
                            [$W, $H, $L, $B, $T] = [720, 220, 44, 22, 8];
                            $plotH = $H - $B - $T; $gw = ($W - $L) / max(1, count($months)); $bw = min(16, ($gw - 10) / 2);
                            $y = fn ($v) => $T + $plotH - ($v / $niceMax * $plotH);
                            $bar = function ($x, $v) use ($y, $T, $plotH, $bw) {
                                $top = $y($v); $base = $T + $plotH; $r = min(4, $bw / 2, max(0, $base - $top));
                                return "M{$x},{$base} V" . ($top + $r) . " Q{$x},{$top} " . ($x + $r) . ",{$top} H" . ($x + $bw - $r) . " Q" . ($x + $bw) . ",{$top} " . ($x + $bw) . "," . ($top + $r) . " V{$base} Z";
                            };
                        @endphp
                        <svg viewBox="0 0 {{ $W }} {{ $H }}" width="100%" role="img" aria-label="Planned versus actual spending by month">
                            @foreach ([0, 0.5, 1] as $f)
                                <line x1="{{ $L }}" x2="{{ $W }}" y1="{{ $y($niceMax * $f) }}" y2="{{ $y($niceMax * $f) }}" stroke="var(--viz-grid)" stroke-width="1"/>
                                <text x="{{ $L - 6 }}" y="{{ $y($niceMax * $f) + 3 }}" text-anchor="end">{{ $short($niceMax * $f) }}</text>
                            @endforeach
                            @foreach (array_values($months) as $i => $m)
                                @php($gx = $L + $i * $gw + ($gw - 2 * $bw - 2) / 2)
                                @if ($m['planned'] > 0)<path d="{{ $bar($gx, $m['planned']) }}" fill="{{ $c['planned'] }}"/>@endif
                                @if ($m['actual'] > 0)<path d="{{ $bar($gx + $bw + 2, $m['actual']) }}" fill="{{ $c['actual'] }}"/>@endif
                                <text x="{{ $L + $i * $gw + $gw / 2 }}" y="{{ $H - 6 }}" text-anchor="middle">{{ $m['label'] }}</text>
                                <rect class="hit" x="{{ $L + $i * $gw }}" y="{{ $T }}" width="{{ $gw }}" height="{{ $plotH }}"
                                      data-tip="{{ $m['label'] }} {{ $m['year'] }}&#10;Planned: PKR {{ number_format($m['planned']) }}&#10;Actual: PKR {{ number_format($m['actual']) }}"/>
                            @endforeach
                        </svg>
                        <details class="mt-2 small">
                            <summary class="text-muted-2">View as table</summary>
                            <table class="table table-sm mt-2">
                                <thead><tr><th>Month</th><th class="text-end">Planned</th><th class="text-end">Actual</th></tr></thead>
                                <tbody>@foreach ($months as $m)<tr><td>{{ $m['label'] }} {{ $m['year'] }}</td><td class="text-end">{{ number_format($m['planned']) }}</td><td class="text-end">{{ number_format($m['actual']) }}</td></tr>@endforeach</tbody>
                            </table>
                        </details>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-5">
                <div class="card h-100">
                    <div class="card-header">Spending by Budget Head</div>
                    <ul class="list-group list-group-flush">
                        @forelse ($byHead as $head => $h)
                            @php($ratio = $h['allocated'] > 0 ? $h['spent'] / $h['allocated'] : 0)
                            <li class="list-group-item" data-tip="{{ config("budget.budget_heads.$head", $head) }}&#10;Spent PKR {{ number_format($h['spent']) }} of PKR {{ number_format($h['allocated']) }} allocated">
                                <div class="d-flex justify-content-between small mb-1"><span>{{ config("budget.budget_heads.$head", $head) }}</span>
                                    <span><strong>{{ $short($h['spent']) }}</strong> <span class="text-muted-2">/ {{ $short($h['allocated']) }} · {{ round($ratio * 100) }}%</span></span></div>
                                <div class="meter {{ $ratio > 1 ? 'over' : '' }}"><span style="width: {{ min(100, $ratio * 100) }}%"></span></div>
                            </li>
                        @empty
                            <li class="list-group-item text-muted-2 small">No allocations yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-header">Recent Activities</div>
                    <ul class="list-group list-group-flush">
                        @forelse ($recent as $r)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between gap-2">
                                    <a href="{{ App\Http\Controllers\Budget\WorkflowController::urlFor($r->approvable) }}" class="small fw-medium text-truncate">{{ class_basename($r->approvable) === 'FinancialTransaction' ? 'Expense' : preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename($r->approvable)) }} {{ $r->approvable->referenceLabel() }}</a>
                                    @include('budget.partials.status', ['status' => $r->to_status])
                                </div>
                                <div class="small-2 text-muted-2">{{ $r->label() }} by {{ $r->user->name ?? '—' }} · {{ $r->created_at->format('d M Y') }}</div>
                            </li>
                        @empty
                            <li class="list-group-item text-muted-2 small">Nothing yet.</li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card mb-3">
                    <div class="card-header">Waiting for You</div>
                    <ul class="list-group list-group-flush queue-list">
                        @php($queueLabels = ['budget' => ['Budgets', 'budgets.index'], 'revision' => ['Budget revisions', 'budgets.index'], 'allocation' => ['Allocations', 'allocations.index'], 'transfer' => ['Transfers', 'allocations.index'], 'plan' => ['Budget plans', 'plans.index'], 'transaction' => ['Expenses to verify', 'transactions.index'], 'voucher' => ['Petty cash vouchers', 'petty-cash.index'], 'jm_budget' => ['Jawan Markaz budgets', 'overview'], 'jm_revision' => ['Jawan Markaz budget revisions', 'overview'], 'hostel_budget' => ['Hostel budgets', 'overview'], 'hostel_revision' => ['Hostel budget revisions', 'overview']])
                        @forelse ($queue as $type => $records)
                            <li class="list-group-item">
                                <span class="badge rounded-pill text-bg-warning">{{ $records->count() }}</span>
                                <a href="{{ $records->count() === 1 ? App\Http\Controllers\Budget\WorkflowController::urlFor($records->first()) : route('budget.' . $queueLabels[$type][1], in_array($type, ['transaction']) ? ['status' => 'pending'] : []) }}" class="small">{{ $queueLabels[$type][0] }}</a>
                            </li>
                        @empty
                            <li class="list-group-item small text-muted-2">Nothing waiting for your decision.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="card">
                    <div class="card-header">Quick Actions</div>
                    <div class="card-body d-grid gap-2">
                        @can('create', App\Models\BudgetAllocation::class)<a href="{{ route('budget.allocations.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Allocation</a>@endcan
                        @can('budget.record_payment')<a href="{{ route('budget.transactions.create') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-receipt me-1"></i>Record Expense</a>@endcan
                        @can('create', App\Models\BudgetPlan::class)<a href="{{ route('budget.plans.create') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-range me-1"></i>New Budget Plan</a>@endcan
                        <a href="{{ route('budget.reports.index') }}" class="btn btn-light btn-sm"><i class="bi bi-file-earmark-bar-graph me-1"></i>Financial Reports</a>
                    </div>
                </div>
            </div>
        </div>
    @endunless
    @include('budget.partials.tooltip')
@endsection
