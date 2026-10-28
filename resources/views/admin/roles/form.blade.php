@extends('layouts.app')
@section('title', $role->exists ? 'Edit Role' : 'Add New Role')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $role->exists ? 'Edit Role' : 'Add New Role' }}</h1>
            <p>{{ $role->exists ? 'Update the role name, description and status.' : 'Name the role, then choose its permissions on the next screen.' }}</p>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <div class="card" style="max-width: 720px">
        <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="card-body">
            @csrf
            @if ($role->exists) @method('PUT') @endif

            <div class="mb-3">
                <label class="form-label" for="name">Role Name <span class="text-danger">*</span></label>
                <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $role->name) }}" required maxlength="100" @readonly($role->is_system)>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if ($role->is_system)<div class="form-text">System roles cannot be renamed or deactivated.</div>@endif
            </div>
            <div class="mb-3">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" rows="3" class="form-control @error('description') is-invalid @enderror" maxlength="500">{{ old('description', $role->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select" @disabled($role->is_system)>
                    <option value="active" @selected(old('status', $role->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $role->status) === 'inactive')>Inactive — users with only this role lose its permissions</option>
                </select>
                @if ($role->is_system)<input type="hidden" name="status" value="active">@endif
            </div>
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.roles.index') }}" class="btn btn-light">Cancel</a>
                <button class="btn btn-primary px-4">{{ $role->exists ? 'Save Changes' : 'Create Role' }}</button>
            </div>
        </form>
    </div>
@endsection
