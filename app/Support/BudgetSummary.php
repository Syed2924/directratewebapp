<?php

namespace App\Support;

use App\Models\BudgetAllocation;
use App\Models\FinancialYear;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Financial-year figures for the dashboard and reports, limited to the user's scope.
 *
 * The four measures are kept separate and never added together:
 *   allocated  — revised value of approved allocations
 *   committed  — still-reserved part of open commitments
 *   spent      — recorded expenditure (pending + verified)
 *   available  — allocated − committed − spent
 * Directorate-wide users also see the budget itself and what is unallocated.
 */
class BudgetSummary
{
    public static function for(?FinancialYear $fy, User $user, ?int $officeId = null): array
    {
        $empty = ['budget' => null, 'revised' => null, 'unallocated' => null, 'allocated' => 0.0, 'committed' => 0.0,
            'spent' => 0.0, 'available' => 0.0, 'allocations' => collect(), 'global' => $user->scopeCoverage()->global && ! $officeId];
        if (! $fy) {
            return $empty;
        }

        $allocations = self::allocations($fy, $user, $officeId);
        $approved = $allocations->where('status', 'approved');

        $out = [
            'allocations' => $allocations,
            'allocated' => $approved->sum(fn ($a) => $a->revised()),
            'committed' => $approved->sum(fn ($a) => $a->committed()),
            'spent' => $allocations->sum(fn ($a) => $a->spent()),
            'available' => $approved->sum(fn ($a) => $a->available()),
        ] + $empty;

        $budget = $fy->budget;
        if ($out['global'] && $budget) {
            $out['budget'] = $budget;
            if ($budget->isApproved()) {
                $out['revised'] = $budget->revisedAmount();
                $out['unallocated'] = $out['revised'] - $out['allocated'];
            }
        }

        return $out;
    }

    public static function allocations(FinancialYear $fy, User $user, ?int $officeId = null): Collection
    {
        return BudgetAllocation::visibleTo($user)
            ->where('financial_year_id', $fy->id)
            ->when($officeId, fn ($q) => $q->where('office_id', $officeId))
            ->withLedger()
            ->with('office.district')
            ->get()
            ->sortBy(fn ($a) => $a->office->name . '|' . $a->budget_head)
            ->values();
    }

    /** Allocations that can fund a new obligation of $amount in a district, best match first. */
    public static function fundingOptions(int $districtId, float $amount, ?string $preferredHead = null): Collection
    {
        $fy = FinancialYear::current();
        if (! $fy) {
            return collect();
        }

        return BudgetAllocation::where('financial_year_id', $fy->id)
            ->where('status', 'approved')
            ->whereNotIn('budget_head', config('budget.non_activity_heads', []))
            ->whereHas('office', fn ($o) => $o->where('district_id', $districtId))
            ->withLedger()->with('office')
            ->get()
            ->filter(fn ($a) => $a->available() >= $amount)
            ->sortByDesc(fn ($a) => $a->budget_head === $preferredHead)
            ->values();
    }
}
