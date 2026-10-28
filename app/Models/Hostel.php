<?php

namespace App\Models;

class Hostel extends OrganizationUnit
{
    protected $fillable = ['district_id', 'name', 'is_active', 'address', 'phone', 'email', 'remarks'];

    public function rooms()
    {
        return $this->hasMany(HostelRoom::class)->orderByRaw('LENGTH(room_number), room_number');
    }

    public function bookings()
    {
        return $this->hasMany(HostelBooking::class);
    }

    public function budgets()
    {
        return $this->hasMany(HostelBudget::class);
    }

    public function scopeWithinCoverageOf($query, User $user)
    {
        $ids = $user->scopeCoverage()->ids('hostel');

        return $ids === null ? $query : $query->whereIn('id', $ids);
    }

    public function isWithinScopeOf(User $user): bool
    {
        return $user->scopeCoverage()->covers('hostel', $this->id);
    }
}
