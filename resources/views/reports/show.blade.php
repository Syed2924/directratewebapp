@extends('layouts.app')
@section('title', $title)

@php
    $query = request()->query();
    $fmt = fn ($v, $kind) => match ($kind) { 'money' => 'PKR ' . number_format((float) $v), 'pct' => $v . '%', 'int' => number_format((float) $v), default => $v };
    $cat = App\Support\Reports\ReportCatalog::REPORTS[$key][0];
@endphp

@section('content')
    <div class="page-head">
        <div>
            <div class="small text-muted-2 mb-1"><a href="{{ route('reports.index') }}">Reports</a> › {{ App\Support\Reports\ReportCatalog::CATEGORIES[$cat][0] }}</div>
            <h1>{{ $title }}</h1>
            <p>{{ $purpose }} · Period: <strong>{{ $report['period'] ?? $f->period() }}</strong>{{ $f->districtName() ? ' · District: ' . $f->districtName() : '' }}</p>
        </div>
        @can('reports.export')
            <div class="d-flex gap-2">
                <a href="{{ route('reports.print', [$key] + $query) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf me-1"></i>PDF / Print</a>
                <a href="{{ route('reports.export', [$key] + $query) }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
            </div>
        @endcan
    </div>

    <form class="card mb-3" method="GET">
        <div class="card-body row g-2 align-items-end">
            @if (in_array('fy', $filters))
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="fy">Financial Year</label>
                    <select id="fy" name="fy" class="form-select">@foreach ($years as $y)<option value="{{ $y->id }}" @selected($f->fy && $f->fy->id === $y->id)>{{ $y->label }}</option>@endforeach</select></div>
            @endif
            @if (in_array('dates', $filters))
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="from">From</label><input type="date" id="from" name="from" value="{{ request('from', $f->from->toDateString()) }}" class="form-control"></div>
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="to">To</label><input type="date" id="to" name="to" value="{{ request('to', $f->to->toDateString()) }}" class="form-control"></div>
            @endif
            @if (in_array('month', $filters))
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="month">Month</label><input type="month" id="month" name="month" value="{{ $f->month->format('Y-m') }}" class="form-control"></div>
            @endif
            @if (in_array('district', $filters))
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="district">District / Office</label>
                    <select id="district" name="district" class="form-select"><option value="">All Districts</option>@foreach ($districts as $d)<option value="{{ $d->id }}" @selected($f->district === $d->id)>{{ $d->name }}</option>@endforeach</select></div>
            @endif
            @if (in_array('center', $filters))
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="center">Jawan Markaz</label>
                    <select id="center" name="center" class="form-select"><option value="">All Centers</option>@foreach ($centers as $c)<option value="{{ $c->id }}" @selected($f->center === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            @endif
            @if (in_array('hostel', $filters))
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="hostel">Youth Hostel</label>
                    <select id="hostel" name="hostel" class="form-select"><option value="">All Hostels</option>@foreach ($hostels as $h)<option value="{{ $h->id }}" @selected($f->hostel === $h->id)>{{ $h->name }}</option>@endforeach</select></div>
            @endif
            @if (in_array('category', $filters))
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="category">{{ $cat === 'jawan_markaz' ? 'Expense Category' : 'Activity Category' }}</label>
                    <select id="category" name="category" class="form-select"><option value="">All Categories</option>@foreach ($categories as $k => $l)<option value="{{ $k }}" @selected($f->category === $k)>{{ $l }}</option>@endforeach</select></div>
            @endif
            @if (in_array('status', $filters))
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="status">{{ $cat === 'jawan_markaz' ? 'Approval Status' : 'Status' }}</label>
                    <select id="status" name="status" class="form-select"><option value="">All Statuses</option>@foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected($f->status === $k)>{{ $l }}</option>@endforeach</select></div>
            @endif
            @if (in_array('payment', $filters))
                <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="payment">Payment Status</label>
                    <select id="payment" name="payment" class="form-select"><option value="">All</option>@foreach (['unpaid' => 'Approved, Unpaid', 'partially_paid' => 'Partially Paid', 'paid' => 'Paid'] as $k => $l)<option value="{{ $k }}" @selected($f->payment === $k)>{{ $l }}</option>@endforeach</select></div>
            @endif
            @if (in_array('q', $filters))
                <div class="col-md-2"><label class="form-label small text-muted-2 mb-1" for="q">Title / Tracking No.</label><input type="search" id="q" name="q" value="{{ $f->q }}" class="form-control"></div>
            @endif
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1">Generate Report</button>
                <a href="{{ route('reports.show', $key) }}" class="btn btn-light" title="Reset filters">Reset</a>
            </div>
        </div>
        @error('to')<div class="px-3 pb-2 text-danger small">{{ $message }}</div>@enderror
    </form>

    @if ($report['summary'])
        <div class="row g-3 mb-3">
            @foreach ($report['summary'] as [$label, $value, $kind])
                <div class="col-6 col-md-4 col-xl">
                    <div class="card h-100"><div class="card-body py-3">
                        <div class="small text-muted-2">{{ $label }}</div>
                        <div class="fs-5 fw-bold text-nowrap">{{ $fmt($value, $kind) }}</div>
                    </div></div>
                </div>
            @endforeach
        </div>
    @endif

    @if (! empty($report['chart']))
        @include('reports.partials.bars', ['chart' => $report['chart']])
    @endif

    @foreach ($report['sections'] as $i => $section)
        @include('reports.partials.section', ['section' => $section, 'interactive' => true, 'sid' => 'rs' . $i])
    @endforeach

    <p class="small text-muted-2 mb-0"><i class="bi bi-shield-check me-1"></i>Figures are calculated from the source module records and limited to your access scope ({{ auth()->user()->scopeCoverage()->summary() }}). Expenditure comes from the central expense register, so no transaction is counted twice. Guest identity numbers are masked.</p>
@endsection

@push('scripts')
    <script>
        (function () {
            document.querySelectorAll('table[data-page-size]').forEach(function (table) {
                var size = +table.dataset.pageSize, page = 0, rows = Array.prototype.slice.call(table.tBodies[0].rows), query = '';
                var pager = document.querySelector('[data-pager="' + table.id + '"]');
                function visible() { return rows.filter(function (r) { return !query || r.textContent.toLowerCase().indexOf(query) !== -1; }); }
                function render() {
                    var v = visible(), pages = size ? Math.max(1, Math.ceil(v.length / size)) : 1;
                    page = Math.min(page, pages - 1);
                    rows.forEach(function (r) { r.hidden = true; });
                    v.forEach(function (r, i) { r.hidden = size && (i < page * size || i >= (page + 1) * size); });
                    if (pager) {
                        pager.querySelector('[data-pager-info]').textContent = 'Showing ' + (v.length ? page * size + 1 : 0) + '–' + Math.min(v.length, (page + 1) * size) + ' of ' + v.length;
                        pager.querySelector('[data-pager-prev]').disabled = page === 0;
                        pager.querySelector('[data-pager-next]').disabled = page >= pages - 1;
                    }
                }
                if (pager) {
                    pager.querySelector('[data-pager-prev]').onclick = function () { page--; render(); };
                    pager.querySelector('[data-pager-next]').onclick = function () { page++; render(); };
                }
                var search = document.querySelector('[data-table-search="' + table.id + '"]');
                if (search) search.addEventListener('input', function () { query = search.value.toLowerCase(); page = 0; render(); });
                table.querySelectorAll('th[data-sort]').forEach(function (th, idx) {
                    var asc = true;
                    th.addEventListener('click', function () {
                        var numeric = th.dataset.sort !== 'text';
                        rows.sort(function (a, b) {
                            var x = numeric ? +a.cells[idx].dataset.v || 0 : a.cells[idx].textContent.trim().toLowerCase();
                            var y = numeric ? +b.cells[idx].dataset.v || 0 : b.cells[idx].textContent.trim().toLowerCase();
                            return (x > y ? 1 : x < y ? -1 : 0) * (asc ? 1 : -1);
                        });
                        asc = !asc;
                        rows.forEach(function (r) { table.tBodies[0].appendChild(r); });
                        render();
                    });
                });
                render();
            });
        })();
    </script>
@endpush
