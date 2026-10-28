@extends('layouts.app')
@section('title', 'Role Management')

@section('content')
    <div class="page-head">
        <div>
            <h1>Role Management</h1>
            <p>Create and manage roles with specific permissions.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.roles.permissions') }}" class="btn btn-outline-primary"><i class="bi bi-grid-3x3-gap me-1"></i>Permission Matrix</a>
            @can('create', App\Models\Role::class)
                <a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add New Role</a>
            @endcan
        </div>
    </div>

    <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>These roles are a <strong>draft proposal</strong>. Final role names, authority and the permission matrix must be approved by the Directorate before deployment.</div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Role Name</th><th>Description</th><th class="text-center">Users</th><th class="text-center">Permissions</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td class="fw-medium text-nowrap">{{ $role->name }} @if($role->is_system)<i class="bi bi-shield-lock text-muted-2 ms-1" title="System role"></i>@endif</td>
                        <td class="small text-muted-2">{{ $role->description }}</td>
                        <td class="text-center">{{ $role->users_count }}</td>
                        <td class="text-center">{{ $role->permissions_count }}</td>
                        <td>@include('partials.status-badge', ['status' => $role->status])</td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="Actions for {{ $role->name }}"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><a class="dropdown-item" href="{{ route('admin.roles.permissions', ['role' => $role->id]) }}"><i class="bi bi-grid-3x3-gap me-2"></i>Permissions</a></li>
                                    @can('update', $role)
                                        <li><a class="dropdown-item" href="{{ route('admin.roles.edit', $role) }}"><i class="bi bi-pencil me-2"></i>Edit details</a></li>
                                    @endcan
                                    @can('delete', $role)
                                        <li>
                                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" data-confirm="Delete the role {{ $role->name }}? This cannot be undone.">
                                                @csrf @method('DELETE')
                                                <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
                                            </form>
                                        </li>
                                    @endcan
                                </ul>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
