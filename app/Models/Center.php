<?php

namespace App\Models;

class Center extends OrganizationUnit
{
    protected $fillable = ['district_id', 'name', 'is_active', 'location', 'phone', 'email', 'incharge_id', 'remarks'];

    protected $casts = ['is_active' => 'boolean', 'district_id' => 'integer', 'incharge_id' => 'integer'];

    public function incharge()
    {
        return $this->belongsTo(User::class, 'incharge_id');
    }

    public function jmBudgets()
    {
        return $this->hasMany(JawanMarkazBudget::class);
    }

    public function jmExpenses()
    {
        return $this->hasMany(JawanMarkazExpense::class);
    }

    /** Active users explicitly assigned to this center (directly or through its district). */
    public function assignedStaff()
    {
        return User::where('status', 'active')
            ->whereHas('scopes', fn ($s) => $s->where(fn ($q) => $q->where('scope_type', 'center')->where('scope_id', $this->id))
                ->orWhere(fn ($q) => $q->where('scope_type', 'district')->where('scope_id', $this->district_id)))
            ->whereHas('roles', fn ($r) => $r->where('status', 'active')->whereHas('permissions', fn ($p) => $p->where('module', 'jawan_markaz')))
            ->orderBy('name')->get();
    }

    public function scopeWithinCoverageOf($query, User $user)
    {
        $ids = $user->scopeCoverage()->ids('center');

        return $ids === null ? $query : $query->whereIn('id', $ids);
    }

    /** Lets the shared scoped policy treat a center like any other record. */
    public function isWithinScopeOf(User $user): bool
    {
        return $user->scopeCoverage()->covers('center', $this->id);
    }
}
