@extends('layouts.app')
@section('title', 'Hostel Management')

@section('content')
    <div class="page-head">
        <div>
            <h1>Hostel Management</h1>
            <p>Hostel information, rooms and today's availability.</p>
        </div>
        @can('organization.manage')<a href="{{ route('admin.organization.index') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Hostel</a>@endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>ID</th><th>Hostel Name</th><th>District / HQ</th><th class="text-end">Total Rooms</th><th class="text-end">Bed Capacity</th><th class="text-end">Occupied Today</th><th class="text-end">Available Today</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse ($hostels as $h)
                    @php($t = $today['by_hostel'][$h->id])
                    <tr>
                        <td class="small text-muted-2">H-{{ str_pad($h->id, 3, '0', STR_PAD_LEFT) }}</td>
                        <td><a href="{{ route('hostel.hostels.show', $h) }}" class="fw-medium">{{ $h->name }}</a><div class="small-2 text-muted-2">{{ $h->address }}</div></td>
                        <td>{{ $h->district->name }}</td>
                        <td class="text-end">{{ $h->rooms_count }}@if ($h->rooms_count > $h->bookable_count)<div class="small-2 text-muted-2">{{ $h->rooms_count - $h->bookable_count }} out of service</div>@endif</td>
                        <td class="text-end">{{ (int) $h->rooms_sum_capacity }}</td>
                        <td class="text-end">{{ $t['occupied'] }}</td>
                        <td class="text-end fw-semibold">{{ $t['free'] }}</td>
                        <td>@include('partials.status-badge', ['status' => $h->is_active ? 'active' : 'inactive'])</td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="Actions for {{ $h->name }}"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><a class="dropdown-item" href="{{ route('hostel.hostels.show', $h) }}"><i class="bi bi-grid-3x3-gap me-2"></i>Rooms &amp; availability</a></li>
                                    @can('create', App\Models\HostelBooking::class)<li><a class="dropdown-item" href="{{ route('hostel.bookings.create', ['hostel' => $h->id]) }}"><i class="bi bi-plus-lg me-2"></i>New booking</a></li>@endcan
                                    @can('organization.manage')<li><a class="dropdown-item" href="{{ route('hostel.hostels.edit', $h) }}"><i class="bi bi-pencil me-2"></i>Edit hostel</a></li>@endcan
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted-2 py-5">No hostels in your scope.</td></tr>
                @endforelse
                </tbody>
                @if ($hostels->isNotEmpty())
                    <tfoot><tr class="fw-semibold"><td colspan="3">Total</td><td class="text-end">{{ $hostels->sum('rooms_count') }}</td><td class="text-end">{{ (int) $hostels->sum('rooms_sum_capacity') }}</td><td class="text-end">{{ $today['occupied'] }}</td><td class="text-end">{{ $today['free'] }}</td><td colspan="2"></td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>
    <p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Hostels come from the shared Organization Units list, which is also used to assign hostel staff. “Available today” excludes rooms occupied, reserved by approved bookings, or out of service.</p>
@endsection
