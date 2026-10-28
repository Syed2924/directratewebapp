@extends('layouts.app')
@section('title', $user->exists ? 'Edit User' : 'Add New User')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $user->exists ? 'Edit User' : 'Add New User' }}</h1>
            <p>{{ $user->exists ? 'Update profile, roles and organizational scope for ' . $user->name . '.' : 'Create a new user and assign roles and organizational scope.' }}</p>
        </div>
        <a href="{{ $user->exists ? route('admin.users.show', $user) : route('admin.users.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" novalidate>
        @csrf
        @if ($user->exists) @method('PUT') @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header">Profile</div>
                    <div class="card-body row g-3">
                        <div class="col-12">
                            <label class="form-label" for="name">Full Name <span class="text-danger">*</span></label>
                            <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" placeholder="Enter full name" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email Address (login) <span class="text-danger">*</span></label>
                            <input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" placeholder="user@example.com" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}" placeholder="+92 300 1234567">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="employee_no">Employee / Reference No.</label>
                            <input id="employee_no" name="employee_no" class="form-control @error('employee_no') is-invalid @enderror" value="{{ old('employee_no', $user->employee_no) }}">
                            @error('employee_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="designation">Designation</label>
                            <input id="designation" name="designation" class="form-control" value="{{ old('designation', $user->designation) }}">
                        </div>
                    </div>
                </div>

                @unless ($user->exists)
                    <div class="card mb-3">
                        <div class="card-header">Account Setup</div>
                        <div class="card-body">
                            @php($method = old('setup_method', 'link'))
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="setup_method" id="setup-link" value="link" @checked($method === 'link')>
                                <label class="form-check-label" for="setup-link"><strong>Email a password setup link</strong> <span class="text-muted-2">(recommended)</span><br>
                                    <span class="small text-muted-2">The account stays <em>Pending</em> until the user sets their own password with a single-use link.</span></label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="setup_method" id="setup-password" value="password" @checked($method === 'password')>
                                <label class="form-check-label" for="setup-password"><strong>Set a temporary password</strong><br>
                                    <span class="small text-muted-2">Hand it over in person. The user must change it at first sign-in.</span></label>
                            </div>
                            <div class="row g-3 mt-1" id="passwordFields">
                                <div class="col-md-6">
                                    <label class="form-label" for="password">Temporary Password</label>
                                    <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                                </div>
                                <div class="col-12 form-text mt-1">{{ $passwordHint }}</div>
                            </div>
                        </div>
                    </div>
                @endunless
            </div>

            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header">Assign Roles <span class="text-danger">*</span></div>
                    <div class="card-body">
                        @foreach ($roles as $role)
                            @php($can = in_array($role->id, $assignable, true))
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role-{{ $role->id }}"
                                       @checked(in_array($role->id, array_map('intval', $selectedRoles), true)) @disabled(! $can)>
                                <label class="form-check-label {{ $can ? '' : 'text-muted-2' }}" for="role-{{ $role->id }}">
                                    {{ $role->name }}
                                    @if ($role->status !== 'active')<span class="badge-status badge-inactive ms-1">inactive</span>@endif
                                    @unless ($can)<i class="bi bi-lock-fill small ms-1" title="Beyond your authority"></i>@endunless
                                </label>
                            </div>
                            {{-- Keep roles the actor cannot change when they are already assigned. --}}
                            @if (! $can && in_array($role->id, array_map('intval', $selectedRoles), true))
                                <input type="hidden" name="roles[]" value="{{ $role->id }}">
                            @endif
                        @endforeach
                        @error('roles')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        <p class="small text-muted-2 mt-2 mb-0">Assign more than one role only where the Directorate has approved it.</p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Organizational Scope</div>
                    <div class="card-body">
                        <p class="small text-muted-2">Records are limited to the selected districts and units. A user with no scope sees no scoped records.</p>
                        @include('admin.users._scope-tree', ['tree' => $tree, 'selected' => $selectedScope])
                        @error('scope')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ $user->exists ? route('admin.users.show', $user) : route('admin.users.index') }}" class="btn btn-light">Cancel</a>
            <button class="btn btn-primary px-4">{{ $user->exists ? 'Save Changes' : 'Create User' }}</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
(function () {
    var box = document.getElementById('passwordFields');
    if (!box) return;
    function sync() { box.style.display = document.getElementById('setup-password').checked ? '' : 'none'; }
    document.querySelectorAll('input[name=setup_method]').forEach(function (r) { r.addEventListener('change', sync); });
    sync();
})();
</script>
@endpush
