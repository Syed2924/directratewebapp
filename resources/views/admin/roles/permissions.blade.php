@extends('layouts.app')
@section('title', 'Role Permissions')

@php
    $modules = config('access.modules');
    $moduleActions = config('access.module_actions');
    $shortNames = ['activity' => 'Activity Mgmt.', 'budget' => 'Budget & Planning', 'jawan_markaz' => 'Jawan Markaz', 'hostel' => 'Hostel Booking', 'reports' => 'Reports'];
    $actionIcons = ['view' => 'bi-eye', 'create' => 'bi-plus-square', 'edit' => 'bi-pencil-square', 'submit' => 'bi-send', 'review' => 'bi-search',
        'approve' => 'bi-check2-square', 'record_payment' => 'bi-cash-coin', 'export' => 'bi-box-arrow-up-right'];
    $sensitive = array_merge(config('access.sensitive_permissions'));
    // A box is editable only if the role is editable and the actor holds that permission.
    $editable = fn ($key) => $canEdit && in_array($key, $actorKeys, true) && ! in_array($key, $essential, true);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Role Permissions</h1>
            <p>Define what each role can do. Use the matrix below to configure access.</p>
        </div>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Roles</a>
    </div>

    <form method="GET" class="mb-3" style="max-width: 360px">
        <label class="form-label small text-muted-2" for="roleSelect">Select Role</label>
        <select id="roleSelect" name="role" class="form-select" onchange="this.form.submit()">
            @foreach ($roles as $r)
                <option value="{{ $r->id }}" @selected($r->id === $role->id)>{{ $r->name }}{{ $r->status !== 'active' ? ' (inactive)' : '' }}</option>
            @endforeach
        </select>
    </form>

    @unless ($canEdit)
        <div class="alert alert-secondary small"><i class="bi bi-lock me-1"></i>Read-only: {{ $editDenial ?: 'you cannot change this role.' }}</div>
    @endunless

    <form method="POST" action="{{ route('admin.roles.permissions.update', $role) }}">
        @csrf @method('PUT')

        <div class="card mb-3">
            <div class="card-header">Business Modules</div>
            <div class="table-responsive">
                <table class="table matrix">
                    <thead>
                    <tr>
                        <th>Permission</th>
                        @foreach ($modules as $m => $label)<th>{{ $shortNames[$m] }}</th>@endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach (config('access.actions') as $action => $actionLabel)
                        <tr>
                            <td class="text-nowrap">
                                <i class="bi {{ $actionIcons[$action] }} text-muted-2 me-2"></i>{{ $actionLabel }}
                                @if (in_array($action, config('access.sensitive_actions'), true))<i class="bi bi-exclamation-diamond text-warning ms-1" title="Sensitive permission"></i>@endif
                            </td>
                            @foreach ($modules as $m => $label)
                                @php($key = "$m.$action")
                                <td>
                                    @if (in_array($action, $moduleActions[$m], true))
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $key }}" aria-label="{{ $actionLabel }} — {{ $label }}"
                                               @checked(in_array($key, $granted, true)) @disabled(! $editable($key))>
                                        @if (! $editable($key) && in_array($key, $granted, true))
                                            <input type="hidden" name="permissions[]" value="{{ $key }}">
                                        @endif
                                    @else
                                        <span class="na" title="Not applicable">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">System Administration</div>
            <ul class="list-group list-group-flush">
                @foreach (config('access.admin_permissions') as $key => $description)
                    <li class="list-group-item">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $key }}" id="p-{{ $key }}"
                                   @checked(in_array($key, $granted, true)) @disabled(! $editable($key))>
                            @if (! $editable($key) && in_array($key, $granted, true))
                                <input type="hidden" name="permissions[]" value="{{ $key }}">
                            @endif
                            <label class="form-check-label" for="p-{{ $key }}">
                                <code class="small">{{ $key }}</code> — {{ $description }}
                                @if (in_array($key, $sensitive, true))<i class="bi bi-exclamation-diamond text-warning ms-1" title="Sensitive permission"></i>@endif
                                @if (in_array($key, $essential, true))<span class="badge text-bg-light ms-1">required for Super Admin</span>@endif
                            </label>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($canEdit)
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="small text-muted-2"><i class="bi bi-journal-check me-1"></i>Changes take effect immediately and are recorded in the audit log.</span>
                <button class="btn btn-primary px-4">Save Permissions</button>
            </div>
        @endif
    </form>
@endsection
