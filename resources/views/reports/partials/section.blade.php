{{-- One report section as a table. $section, $interactive (search/sort/paginate on screen), $sid --}}
@php
    $fmtCell = function ($v, $kind) {
        return match ($kind) {
            'money' => number_format((float) $v),
            'int' => number_format((float) $v),
            'pct' => rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%',
            default => $v,
        };
    };
    $totals = ! empty($section['totals']) && $section['rows'] ? App\Http\Controllers\Reports\ReportController::totals($section) : null;
@endphp
<div class="card mb-3 report-section">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>{{ $section['title'] }} <span class="small fw-normal text-muted-2">({{ count($section['rows']) }})</span></span>
        @if ($interactive && count($section['rows']) > 10)
            <input type="search" class="form-control form-control-sm no-print" style="max-width:240px" placeholder="Search this table…" data-table-search="{{ $sid }}" aria-label="Search {{ $section['title'] }}">
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0" id="{{ $sid }}" data-page-size="{{ $interactive ? 25 : 0 }}">
            <thead><tr>
                @foreach ($section['columns'] as $i => [$label, $kind])
                    <th class="{{ $kind !== 'text' ? 'text-end' : '' }}" @if ($interactive) data-sort="{{ $kind }}" role="button" title="Sort" @endif>{{ $label }}{{ $kind === 'money' ? ' (PKR)' : '' }}</th>
                @endforeach
            </tr></thead>
            <tbody>
            @forelse ($section['rows'] as $row)
                <tr>
                    @foreach ($section['columns'] as $i => [$label, $kind])
                        <td class="{{ $kind !== 'text' ? 'text-end' : '' }} {{ $kind === 'money' && $row[$i] < 0 ? 'text-danger' : '' }}" data-v="{{ is_numeric($row[$i]) ? $row[$i] : '' }}">{{ $fmtCell($row[$i], $kind) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($section['columns']) }}" class="text-center text-muted-2 py-4">No records for this period and these filters.</td></tr>
            @endforelse
            </tbody>
            @if ($totals)
                <tfoot><tr class="fw-semibold">@foreach ($section['columns'] as $i => [$label, $kind])<td class="{{ $kind !== 'text' ? 'text-end' : '' }}">{{ $totals[$i] === '' || $i === 0 ? $totals[$i] : $fmtCell($totals[$i], $kind) }}</td>@endforeach</tr></tfoot>
            @endif
        </table>
    </div>
    @if ($interactive && count($section['rows']) > 25)
        <div class="card-body py-2 border-top d-flex justify-content-between align-items-center small no-print" data-pager="{{ $sid }}">
            <span class="text-muted-2" data-pager-info></span>
            <div class="btn-group btn-group-sm"><button type="button" class="btn btn-light" data-pager-prev>‹ Prev</button><button type="button" class="btn btn-light" data-pager-next>Next ›</button></div>
        </div>
    @endif
</div>
