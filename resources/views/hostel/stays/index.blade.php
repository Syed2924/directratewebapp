@extends('layouts.app')
@section('title', 'Check-in / Check-out')

@section('content')
    <div class="page-head">
        <div>
            <h1>Check-in / Check-out</h1>
            <p>Record the actual arrival and departure of guests.</p>
        </div>
    </div>

    <div class="card">
        <form class="card-body border-bottom row g-2 align-items-end" method="GET">
            <div class="col-md-3"><label class="form-label small text-muted-2 mb-1" for="hostel">Hostel</label>
                <select id="hostel" name="hostel" class="form-select"><option value="">All Hostels</option>@foreach ($hostels as $h)<option value="{{ $h->id }}" @selected(request('hostel') == $h->id)>{{ $h->name }}</option>@endforeach</select></div>
            <div class="col-6 col-md-3"><label class="form-label small text-muted-2 mb-1" for="from">From</label><input type="date" id="from" name="from" value="{{ $from }}" class="form-control"></div>
            <div class="col-6 col-md-3"><label class="form-label small text-muted-2 mb-1" for="to">To</label><input type="date" id="to" name="to" value="{{ $to }}" class="form-control"></div>
            <div class="col-md-3 d-grid"><button class="btn btn-outline-primary">Show</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Booking ID</th><th>Guest Name</th><th>Hostel</th><th>Rooms</th><th>Booked Stay</th><th>Actual Check-in</th><th>Actual Check-out</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                @forelse ($bookings as $b)
                    <tr>
                        <td><a href="{{ route('hostel.bookings.show', $b) }}" class="fw-medium">{{ $b->booking_no }}</a></td>
                        <td>{{ $b->guest_name }}<div class="small-2 text-muted-2">{{ $b->guest_count }} guest(s)</div></td>
                        <td class="small">{{ $b->hostel->name }}</td>
                        <td class="small">{{ $b->rooms->pluck('room_number')->implode(', ') ?: '—' }}</td>
                        <td class="small text-nowrap">{{ $b->check_in->format('d M') }} – {{ $b->check_out->format('d M Y') }}</td>
                        <td class="small text-nowrap">{{ $b->actual_check_in_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="small text-nowrap">{{ $b->actual_check_out_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td>
                            @if ($b->status === 'approved')
                                <span class="badge-status {{ $b->check_out->isPast() ? 'badge-inactive' : 'badge-received' }}">{{ $b->check_out->isPast() ? 'No-show' : ($b->check_in->isFuture() ? 'Scheduled' : 'Due today') }}</span>
                            @else
                                @include('hostel.partials.status', ['status' => $b->status])
                            @endif
                        </td>
                        <td class="text-end">
                            @can('checkIn', $b)<a href="{{ route('hostel.bookings.show', $b) }}#stay" class="btn btn-sm btn-primary">Check In</a>@endcan
                            @can('checkOut', $b)<a href="{{ route('hostel.bookings.show', $b) }}#stay" class="btn btn-sm btn-outline-primary">Check Out</a>@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted-2 py-5">No arrivals, stays or departures in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Shows guests currently checked in, approved bookings arriving in the period, and departures recorded in the period. Checking out releases the rooms for future reservations.</p>
@endsection
