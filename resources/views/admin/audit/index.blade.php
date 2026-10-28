@extends('layouts.app')
@section('title', 'Audit Log')

@section('content')
    <div class="page-head">
        <div>
            <h1>Audit Log</h1>
            <p>Track important security and administrative events.</p>
        </div>
        @can('audit.export')
            <a href="{{ route('admin.audit.export', request()->query()) }}" class="btn btn-primary"><i class="bi bi-download me-1"></i>Export CSV</a>
        @endcan
    </div>

    <div class="card">
        <form class="card-body border-bottom row g-2" method="GET">
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted-2" for="from">From</label>
                <input id="from" type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted-2" for="to">To</label>
                <input id="to" type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted-2" for="event">Event</label>
                <select id="event" name="event" class="form-select">
                    <option value="">All Events</option>
                    @foreach ($events as $key => $label)
                        <option value="{{ $key }}" @selected(request('event') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted-2" for="actor">User</label>
                <select id="actor" name="actor" class="form-select">
                    <option value="">All Users</option>
                    @foreach ($actors as $a)
                        <option value="{{ $a->id }}" @selected(request('actor') == $a->id)>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small text-muted-2" for="outcome">Outcome</label>
                <select id="outcome" name="outcome" class="form-select">
                    <option value="">All</option>
                    <option value="success" @selected(request('outcome') === 'success')>Success</option>
                    <option value="failure" @selected(request('outcome') === 'failure')>Failure</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted-2" for="q">Search</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Details, record, IP">
            </div>
            <div class="col-md-1 d-flex align-items-end gap-1">
                <button class="btn btn-outline-primary w-100" title="Apply filters"><i class="bi bi-funnel"></i></button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Date &amp; Time</th><th>User</th><th>Event</th><th>Record</th><th>Details</th><th>IP Address</th><th></th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="small text-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                        <td class="small">{{ $log->actor_name ?? 'Guest' }}</td>
                        <td class="text-nowrap">
                            {{ $log->eventLabel() }}
                            @if ($log->outcome === 'failure')<span class="badge-status badge-inactive ms-1">failed</span>@endif
                        </td>
                        <td class="small">{{ $log->entity_label }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($log->description, 80) }}</td>
                        <td class="small text-muted-2">{{ $log->ip_address }}</td>
                        <td><a href="{{ route('admin.audit.show', $log) }}" class="btn btn-sm btn-light" aria-label="View entry"><i class="bi bi-chevron-right"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted-2 py-5">No audit entries match your filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
            <span class="small text-muted-2">Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ number_format($logs->total()) }}</span>
            {{ $logs->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
