<?php

namespace App\Support\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Add to any business-module model whose records belong to an organizational unit.
 *
 *   class Expense extends Model {
 *       use HasOrganizationalScope;
 *       protected string $scopeUnit = 'center';          // district|office|center|hostel
 *       protected string $scopeColumn = 'center_id';
 *   }
 *
 *   Expense::visibleTo($request->user())->paginate();   // list screens, reports and exports
 *   $expense->isWithinScopeOf($user);                    // single-record checks in policies
 */
trait HasOrganizationalScope
{
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->scopeCoverage()->ids($this->scopeUnit);

        return $ids === null ? $query : $query->whereIn($this->qualifyColumn($this->scopeColumn), $ids);
    }

    public function isWithinScopeOf(User $user): bool
    {
        return $user->scopeCoverage()->covers($this->scopeUnit, $this->getAttribute($this->scopeColumn));
    }
}
