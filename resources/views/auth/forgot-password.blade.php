@extends('layouts.guest')
@section('title', 'Forgot password')

@section('content')
    <h1 class="mb-1">Forgot Password</h1>
    <p class="text-muted-2 mb-4">Enter your official email address. If an active account matches, we will send a single-use reset link.</p>

    @if (session('status'))
        <div class="alert alert-success small">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="mb-4">
            <label class="form-label fw-medium" for="email">Email Address</label>
            <div class="input-icon">
                <i class="bi bi-envelope"></i>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control form-control-lg fs-6 @error('email') is-invalid @enderror" required autofocus>
            </div>
            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <button class="btn btn-primary w-100 py-2">Send reset link</button>
    </form>
    <p class="text-center mt-4 mb-0"><a href="{{ route('login') }}" class="small"><i class="bi bi-arrow-left me-1"></i>Back to sign in</a></p>
@endsection
