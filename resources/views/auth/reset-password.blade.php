@extends('layouts.guest')
@section('title', 'Set password')

@section('content')
    <h1 class="mb-1">Set Your Password</h1>
    <p class="text-muted-2 mb-4">Choose a new password for your account. This link works once.</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label class="form-label fw-medium" for="email">Email Address</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" class="form-control @error('email') is-invalid @enderror" required autocomplete="username">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-medium" for="password">New Password</label>
            <div class="input-icon">
                <i class="bi bi-lock"></i>
                <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                <button type="button" class="toggle-pass" data-target="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
            </div>
            <div class="form-text">{{ $hint }}</div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="mb-4">
            <label class="form-label fw-medium" for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>
        <button class="btn btn-primary w-100 py-2">Save password</button>
    </form>
@endsection
