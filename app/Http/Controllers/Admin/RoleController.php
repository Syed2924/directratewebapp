<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\Audit;
use App\Support\Authority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.form', ['role' => new Role(['status' => 'active'])]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Role::class);
        $data = $request->validate($this->rules());

        $role = Role::create($data + [
            'slug' => $this->uniqueSlug($data['name']),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        Audit::log('role.created', $role, ['after' => $role->only('name', 'description', 'status')]);

        return redirect()->route('admin.roles.permissions', ['role' => $role->id])
            ->with('status', 'Role created. Now choose its permissions.');
    }

    public function edit(Role $role)
    {
        $this->authorize('update', $role);

        return view('admin.roles.form', ['role' => $role]);
    }

    public function update(Request $request, Role $role)
    {
        $this->authorize('update', $role);
        $data = $request->validate($this->rules($role));

        if ($role->is_system && ($data['name'] !== $role->name || $data['status'] !== 'active')) {
            throw ValidationException::withMessages(['name' => 'System roles cannot be renamed or deactivated.']);
        }

        [$before, $after] = Audit::diff($role->only('name', 'description', 'status'), $data);
        $role->update($data + ['updated_by' => $request->user()->id]);
        if ($after) {
            Audit::log('role.updated', $role, ['before' => $before, 'after' => $after]);
        }

        return redirect()->route('admin.roles.index')->with('status', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);
        Audit::log('role.deleted', $role, ['before' => $role->only('name', 'description', 'status') + [
            'permissions' => $role->permissions()->pluck('key')->all(),
        ]]);
        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', 'Role deleted.');
    }

    /** Role-permission matrix editor (one role at a time). */
    public function permissions(Request $request)
    {
        $this->authorize('viewAny', Role::class);
        $roles = Role::orderBy('id')->get();
        $role = $roles->firstWhere('id', $request->integer('role')) ?? $roles->first();
        abort_unless($role, 404);

        $actor = $request->user();
        $editCheck = Gate::inspect('update', $role);

        return view('admin.roles.permissions', [
            'roles' => $roles,
            'role' => $role,
            'granted' => $role->permissions()->pluck('key')->all(),
            'actorKeys' => $actor->permissionKeys()->all(),
            'canEdit' => $editCheck->allowed(),
            'editDenial' => $editCheck->message(),
            'essential' => $role->isSuperAdmin() ? config('access.essential_permissions') : [],
        ]);
    }

    public function updatePermissions(Request $request, Role $role)
    {
        $this->authorize('update', $role);
        $actor = $request->user();

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,key'],
        ]);
        $requested = collect($data['permissions'] ?? [])->unique()->values();
        $current = $role->permissions()->pluck('key');

        $added = $requested->diff($current)->values();
        $removed = $current->diff($requested)->values();

        // You cannot grant (or revoke) what you do not hold yourself.
        if (! Authority::canGrantPermissions($actor, $added->merge($removed))) {
            Audit::failure('access.denied', $role, ['description' => 'Attempted to change permissions beyond own authority']);
            throw ValidationException::withMessages(['permissions' => 'You can only grant or remove permissions that you hold yourself.']);
        }

        if ($role->isSuperAdmin()) {
            $missing = collect(config('access.essential_permissions'))->diff($requested);
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages(['permissions' => 'The Super Admin role must keep: ' . $missing->implode(', ') . '.']);
            }
        }

        if ($added->isEmpty() && $removed->isEmpty()) {
            return back()->with('status', 'No changes to save.');
        }

        $role->permissions()->sync(Authority::permissionIds($requested));
        $role->update(['updated_by' => $actor->id]);

        Audit::log('role.permissions_changed', $role, [
            'description' => "Updated permissions for {$role->name} (+{$added->count()} / -{$removed->count()})",
            'before' => ['removed' => $removed->all()],
            'after' => ['added' => $added->all()],
        ]);

        return redirect()->route('admin.roles.permissions', ['role' => $role->id])->with('status', 'Permissions saved. Changes take effect immediately.');
    }

    protected function rules(?Role $role = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'role';
        $slug = $base;
        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}_{$i}";
        }

        return $slug;
    }
}
