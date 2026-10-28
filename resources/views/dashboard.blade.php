@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
    <div class="page-head">
        <div>
            <h1>Dashboard</h1>
            <p>Welcome back, {{ auth()->user()->name }}</p>
        </div>
    </div>

    @if ($isAdmin)
        <div class="row g-3 mb-3">
            @php($cards = [
                ['Total Users', $stats['users'], '+' . $stats['users_new'] . ' new this month', 'bi-people', 'tint-green'],
                ['Total Roles', $stats['roles'], $stats['roles_active'] . ' active', 'bi-person-badge', 'tint-purple'],
                ['Permissions', $stats['permissions'], 'across ' . count(config('access.modules')) . ' modules + admin', 'bi-key', 'tint-blue'],
                ['Audit Logs', number_format($stats['audit']), '+' . $stats['audit_today'] . ' today', 'bi-shield-lock', 'tint-amber'],
            ])
            @foreach ($cards as [$label, $value, $delta, $icon, $tint])
                <div class="col-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="stat-icon {{ $tint }}"><i class="bi {{ $icon }}"></i></div>
                        <div>
                            <div class="label">{{ $label }}</div>
                            <div class="value">{{ $value }}</div>
                            <div class="delta">{{ $delta }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-3">
        <div class="{{ $isAdmin ? 'col-xl-7' : 'col-xl-8' }}">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ auth()->user()->hasPermission('audit.view') ? 'Recent Activity' : 'My Recent Activity' }}</span>
                    @can('audit.view')<a href="{{ route('admin.audit.index') }}" class="small fw-normal">View all</a>@endcan
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Time</th><th>Event</th><th>User</th></tr></thead>
                        <tbody>
                        @forelse ($recent as $log)
                            <tr>
                                <td class="text-nowrap small">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    {{ $log->eventLabel() }}
                                    @if ($log->outcome === 'failure')<span class="badge-status badge-inactive ms-1">failed</span>@endif
                                </td>
                                <td class="small">{{ $log->actor_name ?? 'Guest' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted-2 py-4">No activity yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="{{ $isAdmin ? 'col-xl-5' : 'col-xl-4' }}">
            @if ($isAdmin)
                <div class="card h-100">
                    <div class="card-header">Module Access Overview</div>
                    @php($look = ['activity' => ['bi-calendar-event', 'tint-blue'], 'budget' => ['bi-wallet2', 'tint-green'], 'jawan_markaz' => ['bi-building', 'tint-amber'], 'hostel' => ['bi-house-door', 'tint-purple'], 'reports' => ['bi-file-earmark-bar-graph', 'tint-rose']])
                    <ul class="list-group list-group-flush">
                        @foreach ($moduleAccess as $key => $m)
                            <li class="list-group-item d-flex align-items-center gap-3 py-3">
                                <span class="stat-icon {{ $look[$key][1] }}" style="width:34px;height:34px;flex-basis:34px;font-size:.95rem"><i class="bi {{ $look[$key][0] }}"></i></span>
                                <span class="flex-grow-1">{{ $m['label'] }}</span>
                                <span class="small text-primary">{{ $m['users'] }} {{ \Illuminate\Support\Str::plural('user', $m['users']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @else
                <div class="card h-100">
                    <div class="card-header">My Modules</div>
                    <div class="list-group list-group-flush">
                        @forelse ($modules as $key => $label)
                            <a href="{{ route('modules.show', $key) }}" class="list-group-item list-group-item-action py-3"><i class="bi bi-arrow-right-circle me-2 text-primary"></i>{{ $label }}</a>
                        @empty
                            <div class="list-group-item text-muted-2 py-4">No modules have been assigned to your account yet. Contact your administrator.</div>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
