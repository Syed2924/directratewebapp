@extends('layouts.app')
@section('title', $user->name)

@section('content')
    <div class="page-head">
        <div class="d-flex align-items-center gap-3">
            <span class="avatar avatar-lg">{{ $user->initials() }}</span>
            <div>
                <h1>{{ $user->name }} @include('partials.status-badge', ['status' => $user->status])</h1>
                <p>{{ $user->designation ?: $user->primaryRoleName() }} · {{ $user->email }}</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Users</a>
            @if ($canEdit)
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
                @if ($user->status !== 'inactive')
                    <form method="POST" action="{{ route('admin.users.password-link', $user) }}" data-confirm="Send a single-use password link to {{ $user->email }}?">@csrf
                        <button class="btn btn-outline-secondary"><i class="bi bi-envelope me-1"></i>Send password link</button>
                    </form>
                @endif
                @if ($user->status !== 'active')
                    <form method="POST" action="{{ route('admin.users.activate', $user) }}" data-confirm="{{ $user->status === 'locked' ? 'Unlock' : 'Activate' }} {{ $user->name }}?">@csrf
                        <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>{{ $user->status === 'locked' ? 'Unlock' : 'Activate' }}</button>
                    </form>
                @endif
                @if ($user->status !== 'inactive')
                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deactivateModal" @disabled($deactivateCheck->denied()) title="{{ $deactivateCheck->message() }}">
                        <i class="bi bi-person-x me-1"></i>Deactivate
                    </button>
                @endif
            @endif
        </div>
    </div>

    @if ($canEdit && $deactivateCheck->denied() && $user->status !== 'inactive')
        <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>{{ $deactivateCheck->message() }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Account</div>
                <div class="card-body">
                    <dl class="kv mb-0">
                        <dt>User ID</dt><dd>#{{ $user->id }}</dd>
                        <dt>Phone</dt><dd>{{ $user->phone ?: '—' }}</dd>
                        <dt>Employee / Reference No.</dt><dd>{{ $user->employee_no ?: '—' }}</dd>
                        <dt>Last Login</dt><dd>{{ $user->last_login_at?->format('d M Y H:i') ?? 'Never' }} @if($user->last_login_ip)<span class="text-muted-2 small">from {{ $user->last_login_ip }}</span>@endif</dd>
                        <dt>Password Last Changed</dt><dd>{{ $user->password_changed_at?->format('d M Y') ?? 'Not yet set by user' }}</dd>
                        @if ($user->status === 'locked')
                            <dt>Locked Until</dt><dd>{{ $user->locked_until?->format('d M Y H:i') ?? '—' }}</dd>
                        @endif
                        @if ($user->status === 'inactive')
                            <dt>Deactivated</dt><dd>{{ $user->deactivated_at?->format('d M Y H:i') }}<br><span class="small">{{ $user->deactivation_reason }}</span></dd>
                        @endif
                        <dt>Created</dt><dd>{{ $user->created_at->format('d M Y H:i') }} @if($user->creator) by {{ $user->creator->name }} @endif</dd>
                    </dl>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header">Roles</div>
                <ul class="list-group list-group-flush">
                    @forelse ($user->roles as $role)
                        <li class="list-group-item d-flex justify-content-between">{{ $role->name }}
                            @if ($role->status !== 'active')<span class="badge-status badge-inactive">role inactive</span>@endif</li>
                    @empty
                        <li class="list-group-item text-muted-2">No roles assigned</li>
                    @endforelse
                </ul>
            </div>
            <div class="card">
                <div class="card-header">Organizational Scope</div>
                <ul class="list-group list-group-flush">
                    @foreach ($scopeList as $line)
                        <li class="list-group-item small">{{ $line }}</li>
                    @endforeach
                    @if (! $scopeList)
                        <li class="list-group-item text-muted-2 small">No scope assigned — no scoped records are visible.</li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">Account History</div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Date &amp; Time</th><th>Event</th><th>By</th><th>Details</th></tr></thead>
                        <tbody>
                        @forelse ($history as $log)
                            <tr>
                                <td class="small text-nowrap">{{ $log->created_at->format('d M Y H:i') }}</td>
                                <td>{{ $log->eventLabel() }} @if($log->outcome === 'failure')<span class="badge-status badge-inactive ms-1">failed</span>@endif</td>
                                <td class="small">{{ $log->actor_name ?? 'Guest' }}</td>
                                <td class="small">{{ $log->description }} @if($log->reason)<br><span class="text-muted-2">Reason: {{ $log->reason }}</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted-2 py-4">No history yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($history->hasPages())
                    <div class="card-body border-top">{{ $history->links('pagination::bootstrap-5') }}</div>
                @endif
            </div>
        </div>
    </div>

    @if ($canEdit && $deactivateCheck->allowed())
        <div class="modal fade" id="deactivateModal" tabindex="-1" aria-labelledby="deactivateTitle" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('admin.users.deactivate', $user) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="deactivateTitle">Deactivate {{ $user->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted-2">The user is signed out everywhere immediately and cannot sign in. Their records and history are kept for audit.</p>
                        <label class="form-label" for="reason">Reason <span class="text-danger">*</span></label>
                        <textarea id="reason" name="reason" class="form-control" rows="3" required minlength="5" maxlength="500" placeholder="e.g. Transferred to another office"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-danger">Deactivate</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
