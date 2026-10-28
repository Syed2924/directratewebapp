@extends('layouts.app')
@section('title', 'Security Settings')

@section('content')
    <div class="page-head">
        <div>
            <h1>Security Settings</h1>
            <p>Configure password policies, session settings and security protections.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.security.update') }}">
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">Password Policy</div>
                    <div class="card-body">
                        <div class="row align-items-center mb-3">
                            <label class="col-7 col-form-label" for="password_min_length">Minimum password length</label>
                            <div class="col-5"><input type="number" id="password_min_length" name="password_min_length" min="8" max="64" class="form-control" value="{{ old('password_min_length', $s['password_min_length']) }}"></div>
                        </div>
                        @foreach (['password_require_uppercase' => 'Require uppercase letter', 'password_require_lowercase' => 'Require lowercase letter', 'password_require_number' => 'Require number', 'password_require_special' => 'Require special character'] as $key => $label)
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
                                <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="{{ $key }}" name="{{ $key }}" value="1" @checked(old($key, $s[$key]))></div>
                            </div>
                        @endforeach
                        <div class="row align-items-center">
                            <label class="col-7 col-form-label" for="password_expiry_days">Password expiry (days)<div class="form-text mt-0">0 = never expires</div></label>
                            <div class="col-5"><input type="number" id="password_expiry_days" name="password_expiry_days" min="0" max="365" class="form-control" value="{{ old('password_expiry_days', $s['password_expiry_days']) }}"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header">Login Protection</div>
                    <div class="card-body">
                        <div class="row align-items-center mb-3">
                            <label class="col-7 col-form-label" for="max_failed_attempts">Allow failed login attempts</label>
                            <div class="col-5"><input type="number" id="max_failed_attempts" name="max_failed_attempts" min="3" max="20" class="form-control" value="{{ old('max_failed_attempts', $s['max_failed_attempts']) }}"></div>
                        </div>
                        <div class="row align-items-center mb-3">
                            <label class="col-7 col-form-label" for="lockout_minutes">Lockout duration (minutes)</label>
                            <div class="col-5"><input type="number" id="lockout_minutes" name="lockout_minutes" min="1" max="1440" class="form-control" value="{{ old('lockout_minutes', $s['lockout_minutes']) }}"></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-check-label" for="captcha_enabled">Enable CAPTCHA on sign-in</label>
                            <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="captcha_enabled" name="captcha_enabled" value="1" @checked(old('captcha_enabled', $s['captcha_enabled']))></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Session Settings</div>
                    <div class="card-body">
                        <div class="row align-items-center mb-3">
                            <label class="col-7 col-form-label" for="session_timeout_minutes">Session timeout (minutes idle)</label>
                            <div class="col-5"><input type="number" id="session_timeout_minutes" name="session_timeout_minutes" min="5" max="{{ config('session.lifetime') }}" class="form-control" value="{{ old('session_timeout_minutes', $s['session_timeout_minutes']) }}"></div>
                        </div>
                        <div class="row align-items-center">
                            <label class="col-7 col-form-label" for="remember_me_days">Remember me (days)<div class="form-text mt-0">0 = hide "Remember me"</div></label>
                            <div class="col-5"><input type="number" id="remember_me_days" name="remember_me_days" min="0" max="30" class="form-control" value="{{ old('remember_me_days', $s['remember_me_days']) }}"></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Password Reset</div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-check-label" for="password_reset_enabled">Allow self-service "Forgot password" by email</label>
                            <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" id="password_reset_enabled" name="password_reset_enabled" value="1" @checked(old('password_reset_enabled', $s['password_reset_enabled']))></div>
                        </div>
                        <p class="form-text mb-0">Enable only after the Directorate confirms email as the reset channel and outgoing mail is configured. Administrators can always send single-use password links from a user's page.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-3">
            <button class="btn btn-primary px-4">Save Settings</button>
        </div>
    </form>
@endsection
