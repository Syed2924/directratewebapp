@extends('layouts.app')
@section('title', 'My Account')

@section('content')
    <div class="page-head">
        <div class="d-flex align-items-center gap-3">
            <span class="avatar avatar-lg">{{ $user->initials() }}</span>
            <div>
                <h1>My Account</h1>
                <p>{{ $user->name }} · {{ $user->email }}</p>
            </div>
        </div>
        <a href="{{ route('account.password') }}" class="btn btn-outline-primary"><i class="bi bi-key me-1"></i>Change Password</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">Profile</div>
                <form class="card-body" method="POST" action="{{ route('account.update') }}">
                    @csrf @method('PUT')
                    <dl class="kv">
                        <dt>Full name</dt><dd>{{ $user->name }}</dd>
                        <dt>Email (login)</dt><dd>{{ $user->email }}</dd>
                        <dt>Employee / Reference No.</dt><dd>{{ $user->employee_no ?: '—' }}</dd>
                        <dt>Roles</dt><dd>{{ $user->roles->pluck('name')->implode(', ') ?: '—' }}</dd>
                        <dt>Scope</dt><dd>{{ $user->scopeCoverage()->summary() }}</dd>
                        <dt>Password last changed</dt><dd>{{ $user->password_changed_at?->format('d M Y') ?? '—' }}</dd>
                    </dl>
                    <label class="form-label" for="phone">Phone Number</label>
                    <div class="input-group">
                        <input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}">
                        <button class="btn btn-primary">Save</button>
                    </div>
                    <p class="form-text mb-0">Name, email, roles and scope are changed by an administrator.</p>
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">My Recent Activity</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Date &amp; Time</th><th>Event</th><th>IP Address</th></tr></thead>
                        <tbody>
                        @forelse ($recent as $log)
                            <tr>
                                <td class="small text-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>{{ $log->eventLabel() }}</td>
                                <td class="small text-muted-2">{{ $log->ip_address }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted-2 py-4">No activity yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top small text-muted-2">If you see a sign-in you don't recognise, change your password and tell your administrator.</div>
            </div>
        </div>
    </div>
@endsection
