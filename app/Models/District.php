<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function offices()
    {
        return $this->hasMany(Office::class);
    }

    public function centers()
    {
        return $this->hasMany(Center::class);
    }

    public function hostels()
    {
        return $this->hasMany(Hostel::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Districts inside the user's organizational scope (all districts for global users). */
    public function scopeWithinCoverageOf($query, User $user)
    {
        $ids = $user->scopeCoverage()->ids('district');

        return $ids === null ? $query : $query->whereIn('id', $ids);
    }
}
