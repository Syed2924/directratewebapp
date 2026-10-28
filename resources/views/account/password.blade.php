@extends('layouts.app')
@section('title', 'Change Password')

@section('content')
    <div class="page-head">
        <div>
            <h1>Change Password</h1>
            <p>Confirm your current password, then choose a new one. Your other sessions will be signed out.</p>
        </div>
    </div>

    <div class="card" style="max-width: 520px">
        <form class="card-body" method="POST" action="{{ route('account.password.update') }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label" for="current_password">Current Password</label>
                <input id="current_password" type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required autocomplete="current-password">
                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">New Password</label>
                <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                <div class="form-text">{{ $hint }}</div>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-4">
                <label class="form-label" for="password_confirmation">Confirm New Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
            </div>
            <button class="btn btn-primary w-100">Update Password</button>
        </form>
    </div>
@endsection
