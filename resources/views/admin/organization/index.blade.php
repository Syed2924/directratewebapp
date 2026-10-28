@extends('layouts.app')
@section('title', 'Organization Units')

@section('content')
    <div class="page-head">
        <div>
            <h1>Organization Units</h1>
            <p>Districts, offices, centers and hostels used to define each user's access scope.</p>
        </div>
    </div>

    <ul class="nav nav-tabs mb-0">
        @foreach ($types as $key => $label)
            <li class="nav-item"><a class="nav-link {{ $type === $key ? 'active' : '' }}" href="{{ route('admin.organization.index', ['type' => $key]) }}">{{ $label }}</a></li>
        @endforeach
    </ul>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card" style="border-top-left-radius:0">
                <form class="card-body border-bottom" method="GET">
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search {{ strtolower($types[$type]) }}…">
                </form>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Name</th>@if($type !== 'district')<th>District</th>@endif<th>Status</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                        @forelse ($units as $unit)
                            <tr>
                                <td>{{ $unit->name }}</td>
                                @if ($type !== 'district')<td class="small">{{ $unit->district->name }}</td>@endif
                                <td>@include('partials.status-badge', ['status' => $unit->is_active ? 'active' : 'inactive'])</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.organization.toggle', [$type, $unit->id]) }}" class="d-inline"
                                          data-confirm="{{ $unit->is_active ? 'Deactivate' : 'Activate' }} {{ $unit->name }}? {{ $unit->is_active ? 'It will no longer be offered when assigning scope.' : '' }}">
                                        @csrf
                                        <button class="btn btn-sm {{ $unit->is_active ? 'btn-outline-danger' : 'btn-outline-primary' }}">{{ $unit->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted-2 py-5">Nothing here yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($units->hasPages())
                    <div class="card-body border-top">{{ $units->links('pagination::bootstrap-5') }}</div>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Add {{ \Illuminate\Support\Str::singular($types[$type]) }}</div>
                <form class="card-body" method="POST" action="{{ route('admin.organization.store') }}">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    @if ($type !== 'district')
                        <div class="mb-3">
                            <label class="form-label" for="district_id">District</label>
                            <select id="district_id" name="district_id" class="form-select" required>
                                <option value="">Select district…</option>
                                @foreach ($districts as $d)
                                    <option value="{{ $d->id }}" @selected(old('district_id') == $d->id)>{{ $d->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label" for="unit_name">Name</label>
                        <input id="unit_name" name="name" class="form-control" value="{{ old('name') }}" required maxlength="150">
                    </div>
                    <button class="btn btn-primary w-100">Add</button>
                </form>
            </div>
        </div>
    </div>
@endsection
