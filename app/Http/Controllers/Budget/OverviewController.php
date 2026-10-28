<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\BudgetPlan;
use App\Models\FinancialApproval;
use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Support\BudgetSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Budget & Planning dashboard for the selected financial year.
 */
class OverviewController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('budget.view');
        $user = $request->user();
        $fy = FinancialYear::fromRequest($request);
        $summary = BudgetSummary::for($fy, $user);

        return view('budget.overview', [
            'fy' => $fy,
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'summary' => $summary,
            'months' => $fy ? $this->monthly($fy, $user) : [],
            'byHead' => $summary['allocations']->groupBy('budget_head')
                ->map(fn ($g) => ['allocated' => $g->where('status', 'approved')->sum(fn ($a) => $a->revised()), 'spent' => $g->sum(fn ($a) => $a->spent())])
                ->sortByDesc('spent'),
            'queue' => $this->queue($user),
            'recent' => $this->recent($user),
        ]);
    }

    /** Planned (approved/submitted plans) vs actual (recorded expenditure) for each month of the year. */
    protected function monthly(FinancialYear $fy, $user): array
    {
        $months = [];
        for ($m = $fy->starts_on->copy()->startOfMonth(); $m->lte($fy->ends_on); $m->addMonth()) {
            $months[$m->format('Y-m')] = ['label' => $m->format('M'), 'year' => $m->format('Y'), 'planned' => 0.0, 'actual' => 0.0];
        }

        BudgetPlan::visibleTo($user)->where('financial_year_id', $fy->id)->whereNotIn('status', ['rejected'])->get()
            ->each(function ($p) use (&$months, $fy) {
                $date = $p->expected_on ?? $fy->starts_on->copy()->addMonths(($p->quarter - 1) * 3);
                $key = Carbon::parse($date)->format('Y-m');
                if (isset($months[$key])) {
                    $months[$key]['planned'] += (float) $p->planned_amount;
                }
            });

        FinancialTransaction::visibleTo($user)->where('financial_year_id', $fy->id)->where('status', '!=', 'rejected')
            ->selectRaw("DATE_FORMAT(txn_date, '%Y-%m') as ym, SUM(amount) as total")->groupBy('ym')->pluck('total', 'ym')
            ->each(function ($total, $ym) use (&$months) {
                if (isset($months[$ym])) {
                    $months[$ym]['actual'] += (float) $total;
                }
            });

        return $months;
    }

    /** Counts of entries waiting for this user's decision. */
    protected function queue($user): array
    {
        $out = [];
        foreach (WorkflowController::TYPES as $type => $class) {
            $awaiting = (new $class)->usesSubmission() ? 'submitted' : 'pending';
            $records = $class::query()->where('status', $awaiting)
                ->when(method_exists($class, 'scopeVisibleTo'), fn ($q) => $q->visibleTo($user))
                ->get()->filter(fn ($r) => $user->can('decide', $r));
            if ($records->isNotEmpty()) {
                $out[$type] = $records;
            }
        }

        return $out;
    }

    protected function recent($user)
    {
        return FinancialApproval::with('user', 'approvable')->whereIn('action', ['submitted', 'approved', 'verified', 'returned', 'rejected', 'closed'])
            ->latest('id')->limit(40)->get()
            ->filter(fn ($a) => $a->approvable && $user->can('view', $a->approvable))
            ->take(6);
    }
}
