<?php

namespace App\Support;

use App\Models\ActivityProposal;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who may be assigned a proposal for internal review: active users holding
 * activity.review through an active role, whose scope covers the proposal's district.
 */
class ActivityOfficers
{
    public static function reviewersFor(ActivityProposal $proposal): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($r) => $r->where('status', 'active')
                ->whereHas('permissions', fn ($p) => $p->where('key', 'activity.review')))
            ->with('scopes', 'roles')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $u) => $u->scopeCoverage()->covers('district', $proposal->district_id))
            ->values();
    }
}
