<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Authority;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return true;
        }

        return $actor->hasPermission('users.view')
            && Authority::visibleUsers($actor, User::query())->whereKey($target->id)->exists();
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function update(User $actor, User $target): Response
    {
        if (! $actor->hasPermission('users.manage')) {
            return Response::deny('You do not have permission to manage users.');
        }
        if ($actor->is($target)) {
            return Response::deny('Use My Account to change your own details. Your roles and status must be changed by another administrator.');
        }
        if (! Authority::hasAuthorityOver($actor, $target)) {
            return Response::deny('This account has privileges or organizational scope outside your authority.');
        }

        return Response::allow();
    }

    public function deactivate(User $actor, User $target): Response
    {
        $base = $this->update($actor, $target);
        if ($base->denied()) {
            return $base;
        }
        if ($target->isLastActiveSuperAdmin()) {
            return Response::deny('The last active Super Admin cannot be deactivated.');
        }

        return Response::allow();
    }
}
