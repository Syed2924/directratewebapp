<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use App\Support\Authority;
use App\Support\PasswordPolicy;
use App\Support\ScopeAssignment;
use App\Support\ScopeCoverage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);
        $actor = $request->user();

        $users = Authority::visibleUsers($actor, User::query())
            ->with('roles')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . str_replace(['%', '_'], ['\%', '\_'], $request->input('q')) . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('employee_no', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('roles.id', $request->integer('role'))))
            ->when(in_array($request->input('status'), User::STATUSES, true), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', User::class);

        return view('admin.users.form', $this->formData($request->user(), new User(['status' => 'active'])));
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);
        $actor = $request->user();

        $data = $request->validate($this->rules() + [
            'setup_method' => ['required', Rule::in(['link', 'password'])],
            'password' => array_merge(['nullable', 'required_if:setup_method,password', 'confirmed'], PasswordPolicy::rules()),
        ]);

        $roles = $this->authorizedRoles($actor, $data['roles']);
        $coverage = $this->authorizedCoverage($actor, $request->input('scope'));

        $user = DB::transaction(function () use ($data, $actor, $roles, $coverage) {
            $byPassword = $data['setup_method'] === 'password';
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'employee_no' => $data['employee_no'] ?? null,
                'designation' => $data['designation'] ?? null,
                // With the link method the account stays Pending until the user sets a password.
                'password' => Hash::make($byPassword ? $data['password'] : Str::random(64)),
                'status' => $byPassword ? 'active' : 'pending',
                'must_change_password' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->syncRoles($user, $roles, $actor);
            ScopeAssignment::save($user, $coverage);

            return $user;
        });

        Audit::log('user.created', $user, [
            'after' => [
                'email' => $user->email,
                'status' => $user->status,
                'roles' => $roles->pluck('name')->all(),
                'scope' => ScopeAssignment::describe($coverage),
                'onboarding' => $data['setup_method'] === 'password' ? 'Temporary password (must change at first sign-in)' : 'Password setup link',
            ],
        ]);

        if ($data['setup_method'] === 'link') {
            $this->sendLink($user);
        }

        return redirect()->route('admin.users.show', $user)->with('status', $data['setup_method'] === 'link'
            ? 'User created. A password setup link has been sent to ' . $user->email . '.'
            : 'User created. They must change the temporary password at first sign-in.');
    }

    public function show(Request $request, User $user)
    {
        $this->authorize('view', $user);
        $user->load('roles', 'scopes', 'creator');

        $history = AuditLog::where(fn ($q) => $q->where('entity_type', 'User')->where('entity_id', $user->id))
            ->orWhere('actor_id', $user->id)
            ->latest('id')->paginate(10);

        return view('admin.users.show', [
            'user' => $user,
            'scopeList' => ScopeAssignment::describe($user->scopeCoverage()),
            'history' => $history,
            'canEdit' => $request->user()->can('update', $user),
            'deactivateCheck' => \Illuminate\Support\Facades\Gate::inspect('deactivate', $user),
        ]);
    }

    public function edit(Request $request, User $user)
    {
        $this->authorize('update', $user);

        return view('admin.users.form', $this->formData($request->user(), $user->load('roles', 'scopes')));
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $actor = $request->user();
        $data = $request->validate($this->rules($user));

        $roles = $this->authorizedRoles($actor, $data['roles'], $user->roles->pluck('id')->all());
        $coverage = $this->authorizedCoverage($actor, $request->input('scope'));

        $superSlug = config('access.super_admin_role');
        if ($user->isLastActiveSuperAdmin() && ! $roles->contains('slug', $superSlug)) {
            throw ValidationException::withMessages(['roles' => 'The last active Super Admin must keep the Super Admin role.']);
        }

        $profileFields = ['name', 'email', 'phone', 'employee_no', 'designation'];
        $data['email'] = Str::lower($data['email']);
        [$before, $after] = Audit::diff($user->only($profileFields), array_intersect_key($data, array_flip($profileFields)));
        $oldRoles = $user->roles->pluck('name')->sort()->values()->all();
        $oldScope = ScopeAssignment::describe($user->scopeCoverage());

        DB::transaction(function () use ($user, $data, $profileFields, $actor, $roles, $coverage) {
            $user->update(array_intersect_key($data, array_flip($profileFields)) + ['updated_by' => $actor->id]);
            $this->syncRoles($user, $roles, $actor);
            ScopeAssignment::save($user, $coverage);
        });

        if ($after) {
            Audit::log('user.updated', $user, ['before' => $before, 'after' => $after]);
        }
        $newRoles = $roles->pluck('name')->sort()->values()->all();
        if ($newRoles !== $oldRoles) {
            Audit::log('user.roles_changed', $user, [
                'description' => 'Roles changed to: ' . (implode(', ', $newRoles) ?: 'none'),
                'before' => ['roles' => $oldRoles], 'after' => ['roles' => $newRoles],
            ]);
        }
        $newScope = ScopeAssignment::describe($coverage);
        if ($newScope !== $oldScope) {
            Audit::log('user.scope_changed', $user, ['before' => ['scope' => $oldScope], 'after' => ['scope' => $newScope]]);
        }

        return redirect()->route('admin.users.show', $user)->with('status', 'User updated.');
    }

    public function deactivate(Request $request, User $user)
    {
        $this->authorize('deactivate', $user);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $before = $user->status;
        $user->forceFill([
            'status' => 'inactive',
            'deactivation_reason' => $data['reason'],
            'deactivated_at' => now(),
            'updated_by' => $request->user()->id,
        ])->save();
        $user->revokeSessions();

        Audit::log('user.deactivated', $user, [
            'reason' => $data['reason'],
            'description' => 'Account deactivated; active sessions revoked',
            'before' => ['status' => $before], 'after' => ['status' => 'inactive'],
        ]);

        return back()->with('status', "{$user->name} has been deactivated and signed out everywhere.");
    }

    public function activate(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $before = $user->status;
        $user->forceFill([
            'status' => 'active',
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'deactivation_reason' => null,
            'deactivated_at' => null,
            'updated_by' => $request->user()->id,
        ])->save();

        Audit::log($before === 'locked' ? 'account.unlocked' : 'user.activated', $user, [
            'reason' => $data['reason'] ?? null,
            'before' => ['status' => $before], 'after' => ['status' => 'active'],
        ]);

        return back()->with('status', "{$user->name} is now active.");
    }

    /** Administrator-controlled reset: emails a single-use link; the admin never sees or sets the password. */
    public function sendPasswordLink(Request $request, User $user)
    {
        $this->authorize('update', $user);
        abort_if($user->status === 'inactive', 422, 'Activate the account before sending a password link.');

        $this->sendLink($user);

        return back()->with('status', "A single-use password link has been sent to {$user->email}.");
    }

    protected function sendLink(User $user): void
    {
        $status = Password::broker()->sendResetLink(['email' => $user->email]);
        Audit::log('password.link_sent', $user, [
            'outcome' => $status === Password::RESET_LINK_SENT ? 'success' : 'failure',
            'description' => $status === Password::RESET_LINK_SENT ? 'Password link emailed' : 'Link not sent (' . __($status) . ')',
        ]);
    }

    protected function rules(?User $user = null): array
    {
        // Login identifiers are case-insensitive: normalise before the uniqueness check.
        request()->merge(['email' => Str::lower(trim((string) request()->input('email')))]);

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'employee_no' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_no')->ignore($user?->id)],
            'designation' => ['nullable', 'string', 'max:150'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ] + ScopeAssignment::rules();
    }

    /** Reject roles beyond the actor's authority (spec §10). */
    protected function authorizedRoles(User $actor, array $roleIds, array $alreadyHeld = [])
    {
        $roles = Role::with('permissions')->whereIn('id', $roleIds)->get();
        // Keeping a role the user already holds is not a new grant.
        $denied = $roles->reject(fn ($r) => in_array($r->id, $alreadyHeld, true) || Authority::canAssignRole($actor, $r));

        if ($denied->isNotEmpty()) {
            Audit::failure('access.denied', null, ['description' => 'Attempted to assign role(s) beyond authority: ' . $denied->pluck('name')->implode(', ')]);
            throw ValidationException::withMessages([
                'roles' => 'You cannot assign: ' . $denied->pluck('name')->implode(', ') . '. These roles are inactive or carry permissions you do not hold.',
            ]);
        }

        return $roles;
    }

    /** Reject scope beyond the actor's own organizational scope. */
    protected function authorizedCoverage(User $actor, ?array $input): ScopeCoverage
    {
        $coverage = ScopeAssignment::fromInput($input);
        if (! $actor->scopeCoverage()->contains($coverage)) {
            Audit::failure('access.denied', null, ['description' => 'Attempted to assign organizational scope beyond authority']);
            throw ValidationException::withMessages(['scope' => 'You can only assign districts and units within your own scope.']);
        }

        return $coverage;
    }

    protected function syncRoles(User $user, $roles, User $actor): void
    {
        $existing = $user->roles()->pluck('roles.id')->all();
        $sync = [];
        foreach ($roles as $role) {
            $sync[$role->id] = in_array($role->id, $existing, true)
                ? []
                : ['assigned_by' => $actor->id, 'assigned_at' => now()];
        }
        $user->roles()->sync($sync);
        $user->flushAccessCache();
    }

    protected function formData(User $actor, User $user): array
    {
        $roles = Role::with('permissions')->orderBy('id')->get();

        return [
            'user' => $user,
            'roles' => $roles,
            'assignable' => $roles->filter(fn ($r) => Authority::canAssignRole($actor, $r))->pluck('id')->all(),
            'selectedRoles' => old('roles', $user->exists ? $user->roles->pluck('id')->all() : []),
            'tree' => ScopeAssignment::treeFor($actor),
            'selectedScope' => old('scope', $user->exists ? $this->scopeArray($user) : []),
            'passwordHint' => PasswordPolicy::hint(),
        ];
    }

    protected function scopeArray(User $user): array
    {
        $c = $user->scopeCoverage();

        return ['global' => $c->global] + $c->explicit;
    }
}
