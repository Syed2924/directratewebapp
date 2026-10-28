<?php

namespace App\Support;

use App\Models\Center;
use App\Models\FinancialYear;
use App\Models\JawanMarkazBudget;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Center-wise financial figures for a financial year, limited to the user's
 * center scope. Submitted, approved, rejected and paid are separate measures
 * and are never added together; available = authorized budget − approved.
 */
class JawanMarkazSummary
{
    public const MEASURES = ['allocated', 'pending', 'approved', 'paid', 'unpaid', 'rejected', 'available'];

    /** @return array{rows: Collection, totals: array} */
    public static function for(?FinancialYear $fy, User $user, ?int $centerId = null, ?int $districtId = null): array
    {
        $centers = Center::withinCoverageOf($user)->with('district')
            ->when($centerId, fn ($q) => $q->whereKey($centerId))
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->orderBy('name')->get();
        $budgets = $fy ? JawanMarkazBudget::where('financial_year_id', $fy->id)->whereIn('center_id', $centers->pluck('id'))->get()->keyBy('center_id') : collect();

        $rows = $centers->map(function (Center $c) use ($budgets) {
            $budget = $budgets[$c->id] ?? null;
            $f = $budget ? $budget->figures() : array_fill_keys(self::MEASURES, 0.0) + ['pending_count' => 0, 'rejected_count' => 0, 'returned_count' => 0];

            return ['center' => $c, 'budget' => $budget] + $f;
        });

        $totals = [];
        foreach (array_merge(self::MEASURES, ['pending_count']) as $m) {
            $totals[$m] = $rows->sum($m);
        }

        return ['rows' => $rows, 'totals' => $totals];
    }
}
