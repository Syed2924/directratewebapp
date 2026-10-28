<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Delegation rules (spec §10): administrators can never grant more than
 * they hold, and can only manage accounts inside their own authority.
 */
class Authority
{
    /** Permission keys a role carries. */
    public static function roleKeys(Role $role): Collection
    {
        return $role->relationLoaded('permissions') ? $role->permissions->pluck('key') : $role->permissions()->pluck('key');
    }

    /** Actor holds every permission the role would grant. */
    public static function canAssignRole(User $actor, Role $role): bool
    {
        return $role->status === 'active'
            && self::roleKeys($role)->diff($actor->permissionKeys())->isEmpty();
    }

    /** Actor holds every permission in the given set. */
    public static function canGrantPermissions(User $actor, iterable $permissionKeys): bool
    {
        return collect($permissionKeys)->diff($actor->permissionKeys())->isEmpty();
    }

    /** Target's privileges and organizational scope both sit within the actor's. */
    public static function hasAuthorityOver(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        $targetKeys = $target->permissionKeys();

        return $targetKeys->diff($actor->permissionKeys())->isEmpty()
            && $actor->scopeCoverage()->contains($target->scopeCoverage());
    }

    /** Restrict a user query to accounts the actor may see in the directory. */
    public static function visibleUsers(User $actor, $query)
    {
        $coverage = $actor->scopeCoverage();
        if ($coverage->global) {
            return $query;
        }

        // Non-global administrators see accounts that have at least one scope
        // assignment inside their coverage, plus themselves.
        return $query->where(function ($q) use ($actor, $coverage) {
            $q->whereKey($actor->id)->orWhereHas('scopes', function ($s) use ($coverage) {
                $s->where(function ($w) use ($coverage) {
                    foreach (ScopeCoverage::UNIT_TYPES as $type) {
                        $ids = $coverage->ids($type);
                        if ($ids) {
                            $w->orWhere(fn ($x) => $x->where('scope_type', $type)->whereIn('scope_id', $ids));
                        }
                    }
                    $w->orWhereRaw('1 = 0');
                });
            });
        });
    }

    /** Permission ids for the given keys. */
    public static function permissionIds(iterable $keys): array
    {
        return Permission::whereIn('key', collect($keys)->all())->pluck('id')->all();
    }
}
