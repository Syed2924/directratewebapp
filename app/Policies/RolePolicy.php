<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\Authority;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('roles.view');
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->hasPermission('roles.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('roles.manage');
    }

    /** Editing a role requires holding every permission the role currently has. */
    public function update(User $actor, Role $role): Response
    {
        if (! $actor->hasPermission('roles.manage')) {
            return Response::deny('You do not have permission to manage roles.');
        }
        if (! Authority::canGrantPermissions($actor, Authority::roleKeys($role))) {
            return Response::deny('This role holds permissions you do not have, so you cannot change it.');
        }
        if ($actor->roles->contains($role) && ! $actor->isSuperAdmin()) {
            return Response::deny('You cannot change a role you hold yourself.');
        }

        return Response::allow();
    }

    public function delete(User $actor, Role $role): Response
    {
        $base = $this->update($actor, $role);
        if ($base->denied()) {
            return $base;
        }
        if ($role->is_system) {
            return Response::deny('System roles cannot be deleted.');
        }
        if ($role->users()->exists()) {
            return Response::deny('Remove this role from all users (or deactivate it) before deleting it.');
        }

        return Response::allow();
    }
}
