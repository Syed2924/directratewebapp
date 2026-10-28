@php
    $u = auth()->user();
    $icons = ['activity' => 'bi-calendar-event', 'budget' => 'bi-wallet2', 'jawan_markaz' => 'bi-building', 'hostel' => 'bi-house-door', 'reports' => 'bi-file-earmark-bar-graph'];
    $admin = [
        ['admin.users.index', 'admin.users.*', 'bi-people', 'Users', $u->hasPermission('users.view')],
        ['admin.roles.index', 'admin.roles.*', 'bi-shield-check', 'Roles & Permissions', $u->hasPermission('roles.view')],
        ['admin.audit.index', 'admin.audit.*', 'bi-journal-text', 'Audit Log', $u->hasPermission('audit.view')],
        ['admin.security.edit', 'admin.security.*', 'bi-lock', 'Security Settings', $u->hasPermission('security.manage')],
        ['admin.organization.index', 'admin.organization.*', 'bi-diagram-3', 'Organization Units', $u->hasPermission('organization.manage')],
    ];
@endphp
{{-- Menu items are hidden for convenience only; every route is also protected on the server. --}}
<aside class="sidebar" id="sidebar">
    <div class="brand">
        @include('partials.logo', ['size' => 42])
        <div class="brand-text">Directorate of Youth Affairs (KP)<small>Management System</small></div>
    </div>

    <nav class="pt-2">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2"></i>Dashboard</a>
        @foreach ($admin as [$route, $pattern, $icon, $label, $allowed])
            @if ($allowed)
                <a class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}"><i class="bi {{ $icon }}"></i>{{ $label }}</a>
            @endif
        @endforeach

        @php($modules = collect(config('access.modules'))->filter(fn ($l, $m) => $u->hasPermission("$m.view")))
        @if ($modules->isNotEmpty())
            <div class="nav-section">Business Modules</div>
            @php($submenus = [
                'activity' => [
                    ['activity.proposals.index', 'activity.proposals.*', 'Proposals'],
                    ['activity.assignments.index', 'activity.assignments.*', 'My Assignments'],
                    ['activity.activities.index', 'activity.activities.*', 'Activities'],
                    ['activity.reports.index', 'activity.reports.*', 'Reports'],
                ],
                'budget' => [
                    ['budget.overview', 'budget.overview', 'Overview'],
                    ['budget.budgets.index', 'budget.budgets.*', 'Directorate Budget'],
                    ['budget.allocations.index', 'budget.allocations.*', 'Allocations'],
                    ['budget.plans.index', 'budget.plans.*', 'Planning'],
                    ['budget.commitments.index', 'budget.commitments.*', 'Commitments'],
                    ['budget.transactions.index', 'budget.transactions.*', 'Expense Register'],
                    ['budget.petty-cash.index', 'budget.petty-cash.*', 'Petty Cash'],
                    ['budget.reports.index', 'budget.reports.*', 'Reports'],
                ],
                'jawan_markaz' => [
                    ['jawan-markaz.dashboard', 'jawan-markaz.dashboard', 'Dashboard'],
                    ['jawan-markaz.centers.index', 'jawan-markaz.centers.*', 'Centers'],
                    ['jawan-markaz.budgets.index', 'jawan-markaz.budgets.*', 'Budgets'],
                    ['jawan-markaz.expenses.index', 'jawan-markaz.expenses.*', 'Expenses'],
                    ['jawan-markaz.approvals', 'jawan-markaz.approvals', 'Approvals'],
                    ['jawan-markaz.payments.index', 'jawan-markaz.payments.*', 'Payments'],
                    ['jawan-markaz.reports.index', 'jawan-markaz.reports.*', 'Reports'],
                ],
                'hostel' => [
                    ['hostel.dashboard', 'hostel.dashboard', 'Dashboard'],
                    ['hostel.hostels.index', 'hostel.hostels.*', 'Hostels'],
                    ['hostel.bookings.index', 'hostel.bookings.*', 'Bookings'],
                    ['hostel.stays.index', 'hostel.stays.*', 'Check-in / Check-out'],
                    ['hostel.budgets.index', 'hostel.budgets.*', 'Budget'],
                    ['hostel.expenses.index', 'hostel.expenses.*', 'Expenditure'],
                    ['hostel.reports.index', 'hostel.reports.*', 'Reports'],
                ],
                'reports' => [
                    ['reports.dashboard', 'reports.dashboard', 'Report Dashboard'],
                    ['reports.index', ['reports.index', 'reports.show'], 'Reports'],
                ],
            ])
            @php($groupRoutes = ['activity' => 'activity.*', 'budget' => 'budget.*', 'jawan_markaz' => 'jawan-markaz.*', 'hostel' => 'hostel.*', 'reports' => 'reports.*'])
            @foreach ($modules as $key => $label)
                @if (isset($submenus[$key]))
                    @php($open = request()->routeIs($groupRoutes[$key]))
                    <a class="nav-link nav-group {{ $open ? 'open' : 'collapsed' }}" data-bs-toggle="collapse" href="#nav-{{ $key }}" role="button" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="nav-{{ $key }}">
                        <i class="bi {{ $icons[$key] }}"></i>{{ $label }}<i class="bi bi-chevron-down caret ms-auto"></i>
                    </a>
                    <div class="collapse {{ $open ? 'show' : '' }}" id="nav-{{ $key }}">
                        @foreach ($submenus[$key] as [$route, $pattern, $text])
                            <a class="nav-link nav-sub {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route) }}">{{ $text }}</a>
                        @endforeach
                    </div>
                @else
                    <a class="nav-link {{ request()->is("modules/$key") ? 'active' : '' }}" href="{{ route('modules.show', $key) }}"><i class="bi {{ $icons[$key] }}"></i>{{ $label }}</a>
                @endif
            @endforeach
        @endif
    </nav>

    <div class="user-box">
        <span class="avatar">{{ $u->initials() }}</span>
        <div class="flex-grow-1 min-w-0">
            <div class="name text-truncate">{{ $u->name }}</div>
            <div class="role text-truncate">{{ $u->primaryRoleName() }}</div>
        </div>
        <a href="{{ route('account.show') }}" class="text-reset" title="My Account"><i class="bi bi-three-dots-vertical"></i></a>
    </div>
</aside>
