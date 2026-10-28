<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=2">
    @stack('head')
</head>
<body>
<div class="app-shell">
    @include('partials.sidebar')
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="main">
        <header class="topbar">
            <button class="btn btn-light d-lg-none" type="button" id="sidebarToggle" aria-label="Open menu"><i class="bi bi-list fs-5"></i></button>
            <div class="flex-grow-1"></div>
            <div class="text-muted-2 small d-none d-md-block"><i class="bi bi-calendar3 me-1"></i>{{ now()->format('D, d M Y') }}</div>
            <div class="dropdown">
                <button class="btn btn-link text-decoration-none text-reset d-flex align-items-center gap-2 p-0" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar">{{ auth()->user()->initials() }}</span>
                    <span class="text-start d-none d-sm-block lh-sm">
                        <span class="d-block fw-semibold small">{{ auth()->user()->name }}</span>
                        <span class="d-block text-muted-2 small-2">{{ auth()->user()->primaryRoleName() }}</span>
                    </span>
                    <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><a class="dropdown-item" href="{{ route('account.show') }}"><i class="bi bi-person me-2"></i>My Account</a></li>
                    <li><a class="dropdown-item" href="{{ route('account.password') }}"><i class="bi bi-key me-2"></i>Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="content">
            @include('partials.alerts')
            @yield('content')
        </main>
    </div>
</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script>
    (function () {
        var sb = document.getElementById('sidebar'), bd = document.getElementById('sidebarBackdrop');
        function toggle(open) { sb.classList.toggle('show', open); bd.classList.toggle('show', open); }
        document.getElementById('sidebarToggle').addEventListener('click', function () { toggle(true); });
        bd.addEventListener('click', function () { toggle(false); });
        // Confirm destructive actions.
        document.querySelectorAll('form[data-confirm]').forEach(function (f) {
            f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) e.preventDefault(); });
        });
    })();
</script>
@stack('scripts')
</body>
</html>
