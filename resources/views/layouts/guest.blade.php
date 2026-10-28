<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
<div class="auth-wrap">
    {{-- Add an approved photo at public/images/login-hero.jpg to replace the illustrated background. --}}
    <section class="auth-hero" @if (file_exists(public_path('images/login-hero.jpg'))) style="--auth-image:url('{{ asset('images/login-hero.jpg') }}')" @endif>
        <div class="brand">
            @include('partials.logo', ['size' => 64])
            <div>
                <h2>Directorate of Youth Affairs (KP)</h2>
                <small>Management System</small>
            </div>
        </div>
        @unless (file_exists(public_path('images/login-hero.jpg')))
            <svg class="mountains" viewBox="0 0 800 400" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0 260 L110 150 L190 220 L300 90 L420 210 L520 130 L640 230 L720 170 L800 220 L800 400 L0 400 Z" fill="#163f5f"/>
                <path d="M0 310 L140 230 L240 290 L360 200 L470 280 L590 220 L700 290 L800 260 L800 400 L0 400 Z" fill="#1f5a52"/>
                <path d="M0 350 L160 300 L300 345 L450 300 L600 350 L800 320 L800 400 L0 400 Z" fill="#2d6b45"/>
            </svg>
        @endunless
        <div class="tagline">Empowering Our Youth<br>Building a Brighter Future</div>
    </section>

    <section class="auth-panel">
        <div class="auth-form">
            <div class="d-md-none d-flex align-items-center gap-2 mb-4">
                @include('partials.logo', ['size' => 44])
                <div class="fw-semibold lh-sm">Directorate of Youth Affairs (KP)<div class="small text-muted-2 fw-normal">Management System</div></div>
            </div>
            @yield('content')
        </div>
        <div class="auth-foot">Secure &bull; Reliable &bull; Directorate of Youth Affairs (KP)</div>
    </section>
</div>
<script>
    document.querySelectorAll('.toggle-pass').forEach(function (b) {
        b.addEventListener('click', function () {
            var i = document.getElementById(b.dataset.target);
            i.type = i.type === 'password' ? 'text' : 'password';
            b.querySelector('i').className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    });
</script>
</body>
</html>
