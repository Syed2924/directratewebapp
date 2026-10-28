@extends('layouts.app')
@section('title', 'User Management')

@section('content')
    <div class="page-head">
        <div>
            <h1>User Management</h1>
            <p>Manage system users, their roles and status.</p>
        </div>
        @can('create', App\Models\User::class)
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add New User</a>
        @endcan
    </div>

    <div class="card">
        <form class="card-body border-bottom row g-2 align-items-center" method="GET">
            <div class="col-md-6">
                <div class="input-icon">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search by name, email or employee no.">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="role" class="form-select" onchange="this.form.submit()" aria-label="Filter by role">
                    <option value="">All Roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected(request('role') == $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select" onchange="this.form.submit()" aria-label="Filter by status">
                    <option value="">All Status</option>
                    @foreach (App\Models\User::STATUSES as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Search</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr><th>Name</th><th>Email</th><th>Roles</th><th>Last Login</th><th>Status</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <a href="{{ route('admin.users.show', $user) }}" class="d-flex align-items-center gap-2 text-reset text-decoration-none">
                                <span class="avatar">{{ $user->initials() }}</span>
                                <span class="fw-medium">{{ $user->name }}</span>
                            </a>
                        </td>
                        <td class="text-primary small">{{ $user->email }}</td>
                        <td class="small">{{ $user->roles->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td class="small text-muted-2 text-nowrap">{{ $user->last_login_at?->format('d M Y H:i') ?? 'Never' }}</td>
                        <td>@include('partials.status-badge', ['status' => $user->status])</td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="Actions for {{ $user->name }}"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><a class="dropdown-item" href="{{ route('admin.users.show', $user) }}"><i class="bi bi-eye me-2"></i>View details</a></li>
                                    @can('update', $user)
                                        <li><a class="dropdown-item" href="{{ route('admin.users.edit', $user) }}"><i class="bi bi-pencil me-2"></i>Edit, roles &amp; scope</a></li>
                                    @endcan
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted-2 py-5">No users match your filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
            <span class="small text-muted-2">Showing {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} of {{ $users->total() }}</span>
            {{ $users->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
