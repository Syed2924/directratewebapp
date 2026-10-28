@extends('layouts.app')
@section('title', $hostel->name)

@php
    $badge = ['available' => 'badge-active', 'reserved' => 'badge-review', 'occupied' => 'badge-inactive', 'maintenance' => 'badge-pending', 'inactive' => 'badge-draft'];
    $label = ['available' => 'Available', 'reserved' => 'Reserved', 'occupied' => 'Occupied', 'maintenance' => 'Maintenance', 'inactive' => 'Inactive'];
    $canManage = auth()->user()->can('organization.manage');
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Room Availability</h1>
            <p>{{ $hostel->name }} · {{ $hostel->district->name }}{{ $hostel->address ? ' · ' . $hostel->address : '' }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('hostel.hostels.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Hostels</a>
            @if ($canManage)<a href="{{ route('hostel.hostels.edit', $hostel) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit Hostel</a>@endif
            @can('create', App\Models\HostelBooking::class)<a href="{{ route('hostel.bookings.create', ['hostel' => $hostel->id]) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Booking</a>@endcan
        </div>
    </div>

    <form class="card mb-3" method="GET">
        <div class="card-body row g-2 align-items-end">
            <div class="col-6 col-md-3"><label class="form-label small text-muted-2 mb-1" for="from">Check-in</label><input type="date" id="from" name="from" value="{{ $from->toDateString() }}" class="form-control"></div>
            <div class="col-6 col-md-3"><label class="form-label small text-muted-2 mb-1" for="to">Check-out</label><input type="date" id="to" name="to" value="{{ $to->toDateString() }}" class="form-control"></div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Change Dates</button></div>
            <div class="col-md-4 small text-muted-2">Showing {{ $from->format('d M') }} – {{ $to->format('d M Y') }} ({{ $from->diffInDays($to) }} night{{ $from->diffInDays($to) === 1 ? '' : 's' }}). A room is available only if it is free for every night.</div>
        </div>
    </form>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Room Types</div>
                <ul class="list-group list-group-flush">
                    @forelse ($byType as $type => $t)
                        <li class="list-group-item d-flex align-items-center gap-3">
                            <span class="stat-icon tint-green" style="width:34px;height:34px;flex-basis:34px;font-size:.95rem"><i class="bi bi-door-closed"></i></span>
                            <div class="flex-grow-1"><div class="fw-medium small">{{ config("hostel.room_types.$type.0", $type) }}</div>
                                <div class="small-2 text-muted-2">{{ $t['total'] }} rooms · {{ $t['free'] }} available · {{ $t['capacity'] }} bed{{ $t['capacity'] > 1 ? 's' : '' }} each</div></div>
                        </li>
                    @empty
                        <li class="list-group-item small text-muted-2">No rooms recorded yet.</li>
                    @endforelse
                </ul>
            </div>
            <div class="card mb-3">
                <div class="card-body small">
                    <div class="fw-semibold mb-1">Contact</div>
                    <div>{{ $hostel->phone ?? '—' }}</div><div>{{ $hostel->email ?? '' }}</div>
                    @if ($hostel->remarks)<div class="text-muted-2 mt-2">{{ $hostel->remarks }}</div>@endif
                </div>
            </div>
            @if ($canManage)
                <div class="card">
                    <div class="card-header">Add Room</div>
                    <form method="POST" action="{{ route('hostel.hostels.rooms.store', $hostel) }}" class="card-body row g-2" novalidate>
                        @csrf
                        <div class="col-4"><label class="form-label small" for="room_number">Room No.</label><input id="room_number" name="room_number" class="form-control @error('room_number') is-invalid @enderror" value="{{ old('room_number') }}" maxlength="20"></div>
                        <div class="col-5"><label class="form-label small" for="room_type">Type</label>
                            <select id="room_type" name="room_type" class="form-select">@foreach (config('hostel.room_types') as $k => [$l, $cap])<option value="{{ $k }}" data-cap="{{ $cap }}" @selected(old('room_type') === $k)>{{ $l }}</option>@endforeach</select></div>
                        <div class="col-3"><label class="form-label small" for="capacity">Beds</label><input type="number" min="1" max="50" id="capacity" name="capacity" class="form-control" value="{{ old('capacity', 1) }}"></div>
                        @error('room_number')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
                        <div class="col-12 d-grid"><button class="btn btn-outline-primary btn-sm">Add Room</button></div>
                    </form>
                </div>
            @endif
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">Room List
                    <div class="status-chips" role="tablist">
                        @foreach (['all' => 'All', 'available' => 'Available', 'reserved' => 'Reserved', 'occupied' => 'Occupied'] as $k => $l)
                            <a href="#" data-filter="{{ $k }}" class="{{ $k === 'all' ? 'active' : '' }}">{{ $l }} <span class="n">{{ $k === 'all' ? $rooms->count() : $rooms->where('availability', $k)->count() }}</span></a>
                        @endforeach
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="roomTable">
                        <thead><tr><th>Room No.</th><th>Type</th><th class="text-end">Beds</th><th>Status for these dates</th>@if ($canManage)<th class="text-end">Room status</th>@endif</tr></thead>
                        <tbody>
                        @forelse ($rooms as $r)
                            <tr data-status="{{ $r->availability }}">
                                <td class="fw-medium">{{ $r->room_number }}</td>
                                <td class="small">{{ $r->typeLabel() }}</td>
                                <td class="text-end">{{ $r->capacity }}</td>
                                <td><span class="badge-status {{ $badge[$r->availability] }}">{{ $label[$r->availability] }}</span></td>
                                @if ($canManage)
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('hostel.hostels.rooms.update', [$hostel, $r]) }}" class="d-inline-flex gap-1">
                                            @csrf @method('PUT')
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Status of room {{ $r->room_number }}">
                                                @foreach (config('hostel.room_statuses') as $k => $l)<option value="{{ $k }}" @selected($r->status === $k)>{{ $l }}</option>@endforeach
                                            </select>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted-2 py-4">No rooms. {{ $canManage ? 'Add rooms using the form.' : '' }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top small d-flex gap-3 flex-wrap">
                    @foreach ($label as $k => $l)<span><span class="badge-status {{ $badge[$k] }}">{{ $l }}</span></span>@endforeach
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            document.querySelectorAll('[data-filter]').forEach(function (a) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    document.querySelectorAll('[data-filter]').forEach(function (x) { x.classList.toggle('active', x === a); });
                    document.querySelectorAll('#roomTable tbody tr[data-status]').forEach(function (tr) { tr.hidden = a.dataset.filter !== 'all' && tr.dataset.status !== a.dataset.filter; });
                });
            });
            var type = document.getElementById('room_type'), cap = document.getElementById('capacity');
            if (type) type.addEventListener('change', function () { cap.value = type.selectedOptions[0].dataset.cap; });
        })();
    </script>
@endpush
