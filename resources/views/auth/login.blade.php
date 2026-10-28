@extends('layouts.guest')
@section('title', 'Sign in')

@section('content')
    <h1 class="mb-1">Welcome Back</h1>
    <p class="text-muted-2 mb-4">Sign in to access your account</p>

    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif
    @if (session('status_error'))
        <div class="alert alert-warning small">{{ session('status_error') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <div class="mb-3">
            <label class="form-label fw-medium" for="email">Email Address</label>
            <div class="input-icon">
                <i class="bi bi-envelope"></i>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg fs-6 @error('email') is-invalid @enderror"
                       placeholder="Enter your email address" autocomplete="username" required autofocus>
            </div>
            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-medium" for="password">Password</label>
            <div class="input-icon">
                <i class="bi bi-lock"></i>
                <input id="password" type="password" name="password" class="form-control form-control-lg fs-6 @error('password') is-invalid @enderror"
                       placeholder="Enter your password" autocomplete="current-password" required>
                <button type="button" class="toggle-pass" data-target="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
            </div>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>

        @if ($captcha)
            <div class="mb-3">
                <label class="form-label fw-medium" for="captcha">Security check: what is {{ $captcha }}?</label>
                <input id="captcha" type="text" inputmode="numeric" name="captcha" class="form-control @error('captcha') is-invalid @enderror" autocomplete="off" required>
                @error('captcha')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-4">
            @if ($rememberEnabled)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
            @else
                <span></span>
            @endif
            @if ($resetEnabled)
                <a href="{{ route('password.request') }}" class="small">Forgot password?</a>
            @endif
        </div>

        <button class="btn btn-primary btn-lg w-100 fs-6 py-2">Login</button>
    </form>

    @unless ($resetEnabled)
        <p class="small text-muted-2 mt-4 mb-0"><i class="bi bi-info-circle me-1"></i>Forgotten your password? Contact your system administrator.</p>
    @endunless
@endsection
