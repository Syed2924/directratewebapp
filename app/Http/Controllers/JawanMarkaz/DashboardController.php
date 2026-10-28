<?php

namespace App\Http\Controllers\JawanMarkaz;

use App\Http\Controllers\Controller;
use App\Models\FinancialApproval;
use App\Models\FinancialYear;
use App\Models\JawanMarkazBudget;
use App\Models\JawanMarkazExpense;
use App\Models\JawanMarkazPayment;
use App\Support\JawanMarkazSummary;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('jawan_markaz.view');
        $user = $request->user();
        $fy = FinancialYear::fromRequest($request);
        $summary = JawanMarkazSummary::for($fy, $user);
        $budgetIds = $summary['rows']->pluck('budget.id')->filter();

        return view('jawan-markaz.dashboard', [
            'fy' => $fy,
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'summary' => $summary,
            'submitted' => (float) JawanMarkazExpense::visibleTo($user)->whereIn('jawan_markaz_budget_id', $budgetIds)->where('status', '!=', 'draft')->sum('requested_amount'),
            'months' => $fy ? $this->monthly($fy, $user, $budgetIds) : [],
            'queue' => $this->queue($user),
            'recent' => FinancialApproval::with('user', 'approvable')
                ->whereIn('approvable_type', [JawanMarkazExpense::class, JawanMarkazBudget::class])
                ->latest('id')->limit(40)->get()
                ->filter(fn ($a) => $a->approvable && $user->can('view', $a->approvable))->take(6),
        ]);
    }

    /** Approved vs paid by month. */
    protected function monthly(FinancialYear $fy, $user, $budgetIds): array
    {
        $months = [];
        for ($m = $fy->starts_on->copy()->startOfMonth(); $m->lte($fy->ends_on); $m->addMonth()) {
            $months[$m->format('Y-m')] = ['label' => $m->format('M'), 'year' => $m->format('Y'), 'approved' => 0.0, 'paid' => 0.0];
        }
        JawanMarkazExpense::visibleTo($user)->whereIn('jawan_markaz_budget_id', $budgetIds)->whereIn('status', JawanMarkazBudget::SPENDING)->whereNotNull('decided_at')
            ->get(['decided_at', 'approved_amount'])->each(function ($e) use (&$months) {
                $k = $e->decided_at->format('Y-m');
                if (isset($months[$k])) {
                    $months[$k]['approved'] += (float) $e->approved_amount;
                }
            });
        JawanMarkazPayment::visibleTo($user)->whereHas('expense', fn ($q) => $q->whereIn('jawan_markaz_budget_id', $budgetIds))
            ->get(['payment_date', 'amount'])->each(function ($p) use (&$months) {
                $k = $p->payment_date->format('Y-m');
                if (isset($months[$k])) {
                    $months[$k]['paid'] += (float) $p->amount;
                }
            });

        return $months;
    }

    protected function queue($user): array
    {
        $q = [];
        $expenses = JawanMarkazExpense::visibleTo($user);
        if ($user->hasPermission('jawan_markaz.approve')) {
            $q[] = ['Expenses awaiting decision', (clone $expenses)->whereIn('status', ['submitted', 'under_review'])->where('submitted_by', '!=', $user->id)->count(), route('jawan-markaz.approvals')];
        }
        if ($user->hasPermission('jawan_markaz.record_payment')) {
            $q[] = ['Approved, awaiting payment', (clone $expenses)->whereIn('status', ['approved', 'partially_paid'])->count(), route('jawan-markaz.payments.index')];
        }
        if ($user->hasPermission('jawan_markaz.edit')) {
            $q[] = ['Returned for correction', (clone $expenses)->where('status', 'returned')->count(), route('jawan-markaz.expenses.index', ['status' => 'returned'])];
            $q[] = ['My drafts', (clone $expenses)->where('status', 'draft')->where('created_by', $user->id)->count(), route('jawan-markaz.expenses.index', ['status' => 'draft'])];
        }
        if ($user->hasPermission('budget.approve')) {
            $q[] = ['Center budgets to approve', JawanMarkazBudget::visibleTo($user)->where('status', 'submitted')->where('submitted_by', '!=', $user->id)->count(), route('jawan-markaz.budgets.index')];
        }

        return array_filter($q, fn ($i) => $i[1] > 0);
    }
}
