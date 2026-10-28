@extends('layouts.app')
@section('title', 'Activity Reports')

@section('content')
    <div class="page-head">
        <div>
            <h1>Activity Reports</h1>
            <p>Proposal pipeline and activity outcomes for the districts in your scope.</p>
        </div>
        @can('export', App\Models\Activity::class)
            <a href="{{ route('activity.reports.export', request()->query()) }}" class="btn btn-primary"><i class="bi bi-download me-1"></i>Export CSV</a>
        @endcan
    </div>

    <div class="card mb-3">
        <form class="card-body row g-2 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label small text-muted-2 mb-1" for="q">Search</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tracking number, activity title…">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted-2 mb-1" for="district">District</label>
                <select id="district" name="district" class="form-select">
                    <option value="">All Districts</option>
                    @foreach ($districts as $d)
                        <option value="{{ $d->id }}" @selected(request('district') == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted-2 mb-1" for="category">Category</label>
                <select id="category" name="category" class="form-select">
                    <option value="">All Categories</option>
                    @foreach (config('activity.categories') as $k => $l)
                        <option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted-2 mb-1" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All Status</option>
                    @foreach (config('activity.activity_statuses') as $k => [$l])
                        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small text-muted-2 mb-1" for="from">From</label>
                <input type="date" id="from" name="from" value="{{ request('from') }}" class="form-control px-1">
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small text-muted-2 mb-1" for="to">To</label>
                <input type="date" id="to" name="to" value="{{ request('to') }}" class="form-control px-1">
            </div>
            <div class="col-6 col-md-1 d-grid"><button class="btn btn-outline-primary">Apply</button></div>
        </form>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['Activities', number_format($totals['activities']), $totals['completed'] . ' completed', 'bi-calendar-check', 'tint-blue'],
            ['Participants', number_format($totals['participants']), number_format($totals['female']) . ' female', 'bi-people', 'tint-purple'],
            ['Approved Budget (PKR)', number_format($totals['budget']), 'for listed activities', 'bi-wallet2', 'tint-green'],
            ['Actual Expenses (PKR)', number_format($totals['spent']), $totals['budget'] > 0 ? round($totals['spent'] / $totals['budget'] * 100) . '% of budget' : '—', 'bi-receipt', 'tint-amber'],
        ] as [$label, $value, $delta, $icon, $tint])
            <div class="col-6 col-xl-3">
                <div class="card stat-card">
                    <div class="stat-icon {{ $tint }}"><i class="bi {{ $icon }}"></i></div>
                    <div><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div><div class="delta">{{ $delta }}</div></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">Proposal Pipeline</div>
                <ul class="list-group list-group-flush">
                    @php($max = max(1, $proposalCounts->max() ?? 1))
                    @foreach (config('activity.proposal_statuses') as $k => [$label])
                        @php($n = $proposalCounts[$k] ?? 0)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between small mb-1"><span>{{ $label }}</span><strong>{{ $n }}</strong></div>
                            <div class="progress" style="height:6px" role="progressbar" aria-label="{{ $label }}" aria-valuenow="{{ $n }}" aria-valuemin="0" aria-valuemax="{{ $max }}">
                                <div class="progress-bar" style="width: {{ $n / $max * 100 }}%; background: var(--dya-green)"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">By District</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>District</th><th class="text-end">Activities</th><th class="text-end">Participants</th><th class="text-end">Expenses (PKR)</th></tr></thead>
                        <tbody>
                        @forelse ($byDistrict as $name => $d)
                            <tr><td>{{ $name }}</td><td class="text-end">{{ $d['count'] }}</td><td class="text-end">{{ number_format($d['participants']) }}</td><td class="text-end">{{ number_format($d['spent']) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted-2 py-4">No activities match these filters.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Activities</div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Tracking No.</th><th>Activity Title</th><th>District</th><th>Status</th><th>Date</th><th class="text-end">Participants</th><th class="text-end">Budget</th><th class="text-end">Spent</th><th class="text-end">Report</th></tr></thead>
                <tbody>
                @forelse ($rows as $a)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('activity.activities.show', $a) }}" class="fw-medium">{{ $a->proposal->tracking_no }}</a></td>
                        <td>{{ $a->proposal->title }}</td>
                        <td>{{ $a->district->name }}</td>
                        <td>@include('activity.partials.badge', ['type' => 'activity', 'status' => $a->status])</td>
                        <td class="small text-nowrap">{{ $a->actual_start?->format('d M Y') ?? '—' }}</td>
                        <td class="text-end">{{ $a->participantsTotal() }}</td>
                        <td class="text-end">{{ number_format($a->proposal->estimated_budget) }}</td>
                        <td class="text-end">{{ number_format($a->expenses_sum_amount ?? 0) }}</td>
                        <td class="text-end"><a href="{{ route('activity.activities.report', $a) }}" target="_blank" class="btn btn-sm btn-light" aria-label="Print report"><i class="bi bi-printer"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted-2 py-5">No activities match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
            <span class="small text-muted-2">Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }}</span>
            {{ $rows->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
