@extends('layouts.app')
@section('title', 'Booking List')

@section('content')
    <div class="page-head">
        <div>
            <h1>Booking List</h1>
            <p>Hostel booking register. Guest identity numbers are shown masked.</p>
        </div>
        @can('create', App\Models\HostelBooking::class)<a href="{{ route('hostel.bookings.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Booking</a>@endcan
    </div>

    <div class="status-chips mb-3">
        <a href="{{ route('hostel.bookings.index', request()->except('status', 'page')) }}" class="{{ request('status') ? '' : 'active' }}">All<span class="n">{{ $counts->sum() }}</span></a>
        @foreach (config('hostel.booking_statuses') as $k => [$l])
            @if ($counts->has($k))<a href="{{ route('hostel.bookings.index', ['status' => $k] + request()->except('status', 'page')) }}" class="{{ request('status') === $k ? 'active' : '' }}">{{ $l }}<span class="n">{{ $counts[$k] }}</span></a>@endif
        @endforeach
    </div>

    <div class="card">
        <form class="card-body border-bottom row g-2 align-items-end" method="GET">
            @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <div class="col-md-3"><label class="form-label small text-muted-2 mb-1" for="q">Search</label><input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Booking ID, guest, organization…"></div>
            <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="hostel">Hostel</label>
                <select id="hostel" name="hostel" class="form-select"><option value="">All Hostels</option>@foreach ($hostels as $h)<option value="{{ $h->id }}" @selected(request('hostel') == $h->id)>{{ $h->name }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="district">District/HQ</label>
                <select id="district" name="district" class="form-select"><option value="">All</option>@foreach ($districts as $d)<option value="{{ $d->id }}" @selected(request('district') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="from">Stay from</label><input type="date" id="from" name="from" value="{{ request('from') }}" class="form-control"></div>
            <div class="col-6 col-md-2"><label class="form-label small text-muted-2 mb-1" for="to">to</label><input type="date" id="to" name="to" value="{{ request('to') }}" class="form-control"></div>
            <div class="col-md-1 d-grid"><button class="btn btn-outline-primary">Apply</button></div>
        </form>
        @if (request()->hasAny(['from', 'to', 'hostel']))
            <div class="card-body border-bottom py-2 small"><i class="bi bi-door-open me-1 text-primary"></i><strong>{{ $roomsBooked }}</strong> room(s) booked by approved / checked-in reservations matching these filters.</div>
        @endif
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Booking ID</th><th>Request Date</th><th>Guest Name</th><th>Organization</th><th>Hostel</th><th>Check-in</th><th>Check-out</th><th class="text-end">Guests / Rooms</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse ($bookings as $b)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('hostel.bookings.show', $b) }}" class="fw-medium">{{ $b->booking_no }}</a></td>
                        <td class="small text-nowrap">{{ $b->request_date->format('d M Y') }}</td>
                        <td>{{ $b->guest_name }}<div class="small-2 text-muted-2">{{ $b->maskedCnic() }}</div></td>
                        <td class="small">{{ $b->organization ?? '—' }}</td>
                        <td class="small">{{ $b->hostel->name }}</td>
                        <td class="small text-nowrap">{{ $b->check_in->format('d M Y') }}</td>
                        <td class="small text-nowrap">{{ $b->check_out->format('d M Y') }}</td>
                        <td class="text-end">{{ $b->guest_count }} / {{ $b->room_count }}</td>
                        <td>@include('hostel.partials.status', ['status' => $b->status])</td>
                        <td class="text-end">
                            <a href="{{ route('hostel.bookings.show', $b) }}" class="btn btn-sm btn-light" title="View" aria-label="View {{ $b->booking_no }}"><i class="bi bi-eye"></i></a>
                            @can('update', $b)<a href="{{ route('hostel.bookings.edit', $b) }}" class="btn btn-sm btn-light" title="Edit" aria-label="Edit {{ $b->booking_no }}"><i class="bi bi-pencil"></i></a>@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted-2 py-5">No bookings match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
            <span class="small text-muted-2">Showing {{ $bookings->firstItem() ?? 0 }}–{{ $bookings->lastItem() ?? 0 }} of {{ $bookings->total() }}</span>
            {{ $bookings->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
