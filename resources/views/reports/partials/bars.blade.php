{{-- Two-series grouped bar chart. $chart = ['title', 'series' => [a, b], 'rows' => [[label, [a, b]]], 'money' => bool] --}}
@php
    $cc = config('budget.chart');
    $colors = [$cc['planned'], $cc['actual']];
    $rows = $chart['rows'];
    $fmt = fn ($v) => $chart['money'] ? (abs($v) >= 1e6 ? rtrim(rtrim(number_format($v / 1e6, 1), '0'), '.') . 'M' : (abs($v) >= 1e3 ? number_format($v / 1e3) . 'K' : number_format($v))) : number_format($v);
    $max = max(1, collect($rows)->max(fn ($r) => max($r[1])));
    $step = 10 ** floor(log10($max)); $niceMax = ceil($max / $step) * $step;
    [$W, $H, $L, $B, $T] = [720, 210, 48, 34, 8];
    $plotH = $H - $B - $T; $gw = ($W - $L) / max(1, count($rows)); $bw = max(4, min(18, ($gw - 10) / 2));
    $y = fn ($v) => $T + $plotH - ($v / $niceMax * $plotH);
    $bar = function ($x, $v) use ($y, $T, $plotH, $bw) {
        $top = $y($v); $base = $T + $plotH; $r = min(4, $bw / 2, max(0, $base - $top));
        return "M{$x},{$base} V" . ($top + $r) . " Q{$x},{$top} " . ($x + $r) . ",{$top} H" . ($x + $bw - $r) . " Q" . ($x + $bw) . ",{$top} " . ($x + $bw) . "," . ($top + $r) . " V{$base} Z";
    };
@endphp
<div class="card mb-3 viz">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ $chart['title'] }}</span>
        <span class="viz-key fw-normal">@foreach ($chart['series'] as $i => $s)<span><i style="background: {{ $colors[$i] }}"></i>{{ $s }}</span>@endforeach</span>
    </div>
    <div class="card-body">
        <svg viewBox="0 0 {{ $W }} {{ $H }}" width="100%" role="img" aria-label="{{ $chart['title'] }}">
            @foreach ([0, 0.5, 1] as $g)
                <line x1="{{ $L }}" x2="{{ $W }}" y1="{{ $y($niceMax * $g) }}" y2="{{ $y($niceMax * $g) }}" stroke="var(--viz-grid)"/>
                <text x="{{ $L - 6 }}" y="{{ $y($niceMax * $g) + 3 }}" text-anchor="end">{{ $fmt($niceMax * $g) }}</text>
            @endforeach
            @foreach ($rows as $i => [$label, $vals])
                @php($gx = $L + $i * $gw + ($gw - 2 * $bw - 2) / 2)
                @foreach ($vals as $s => $v)
                    @if ($v > 0)<path d="{{ $bar($gx + $s * ($bw + 2), $v) }}" fill="{{ $colors[$s] }}"/>@endif
                @endforeach
                <text x="{{ $L + $i * $gw + $gw / 2 }}" y="{{ $H - 18 }}" text-anchor="middle">{{ \Illuminate\Support\Str::limit($label, max(4, (int) ($gw / 6)), '…') }}</text>
                <rect class="hit" x="{{ $L + $i * $gw }}" y="{{ $T }}" width="{{ $gw }}" height="{{ $plotH }}"
                      data-tip="{{ $label }}&#10;{{ $chart['series'][0] }}: {{ $chart['money'] ? 'PKR ' : '' }}{{ number_format($vals[0]) }}&#10;{{ $chart['series'][1] }}: {{ $chart['money'] ? 'PKR ' : '' }}{{ number_format($vals[1]) }}"/>
            @endforeach
        </svg>
    </div>
</div>
@include('budget.partials.tooltip')
