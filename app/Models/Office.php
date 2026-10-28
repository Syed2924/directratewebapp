<?php

namespace App\Models;

class Office extends OrganizationUnit
{
    public function pettyCashFund()
    {
        return $this->hasOne(PettyCashFund::class);
    }

    /** Offices inside the user's organizational scope (all offices for global users). */
    public function scopeWithinCoverageOf($query, User $user)
    {
        $ids = $user->scopeCoverage()->ids('office');

        return $ids === null ? $query : $query->whereIn('id', $ids);
    }
}
