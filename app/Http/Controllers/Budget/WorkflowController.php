<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetPlan;
use App\Models\BudgetRevision;
use App\Models\BudgetTransfer;
use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\PettyCashVoucher;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Submit, approve, verify, return and reject — the same steps for every
 * financial record. Business rules live on each model (checkDecision).
 */
class WorkflowController extends Controller
{
    public const TYPES = [
        'budget' => Budget::class,
        'revision' => BudgetRevision::class,
        'allocation' => BudgetAllocation::class,
        'transfer' => BudgetTransfer::class,
        'plan' => BudgetPlan::class,
        'transaction' => FinancialTransaction::class,
        'voucher' => PettyCashVoucher::class,
        'jm_budget' => \App\Models\JawanMarkazBudget::class,
        'jm_revision' => \App\Models\JawanMarkazBudgetRevision::class,
        'hostel_budget' => \App\Models\HostelBudget::class,
        'hostel_revision' => \App\Models\HostelBudgetRevision::class,
    ];

    public function submit(Request $request, string $type, int $id)
    {
        $record = self::resolve($type, $id);
        $this->authorize('submit', $record);
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($record, $request, $data) {
            $record->submitted_by = $request->user()->id;
            $record->recordAction('submitted', 'submitted', $request->user(), $data['remarks'] ?? null);
        });
        Audit::log("budget.{$type}_submitted", $record, ['entity_label' => $record->referenceLabel(), 'reason' => $data['remarks'] ?? null]);

        return redirect()->to(self::urlFor($record))->with('status', $record->referenceLabel() . ' submitted for approval.');
    }

    public function decide(Request $request, string $type, int $id)
    {
        $record = self::resolve($type, $id);
        $this->authorize('decide', $record);
        $options = $record->decisionOptions();
        $data = $request->validate([
            'decision' => ['required', Rule::in(array_keys($options))],
            'remarks' => ['required_if:decision,return,reject', 'nullable', 'string', 'max:2000'],
        ] + $record->decisionRules(), ['remarks.required_if' => 'Give a reason when returning or rejecting.']);
        [, $to] = $options[$data['decision']];

        // Lock the financial year so concurrent decisions cannot overspend or over-allocate.
        DB::transaction(function () use ($record, $request, $data, $to) {
            if ($fyId = self::financialYearOf($record)) {
                FinancialYear::whereKey($fyId)->lockForUpdate()->first();
            }
            if ($error = $record->checkDecision($data['decision'], $data)) {
                throw ValidationException::withMessages(['decision' => $error]);
            }
            $record->applyDecision($data['decision'], $data);
            $record->recordAction($to, $to, $request->user(), $data['remarks'] ?? null);
        });

        Audit::log("budget.{$type}_{$to}", $record, [
            'entity_label' => $record->referenceLabel(),
            'description' => $record->referenceLabel() . ' ' . $record->statusLabel() . (isset($data['approved_amount']) ? ' — PKR ' . number_format((float) $data['approved_amount']) : ''),
            'reason' => $data['remarks'] ?? null,
        ]);

        return redirect()->to(self::urlFor($record))->with('status', $record->referenceLabel() . ': ' . strtolower($record->statusLabel()) . '.');
    }

    public static function resolve(string $type, int $id): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type]::findOrFail($id);
    }

    public static function typeOf(Model $record): string
    {
        return array_search(get_class($record), self::TYPES, true);
    }

    public static function financialYearOf(Model $record): ?int
    {
        return $record->financial_year_id
            ?? ($record instanceof BudgetRevision ? $record->budget->financial_year_id : null)
            ?? ($record instanceof BudgetTransfer ? $record->from->financial_year_id : null)
            ?? ($record instanceof FinancialTransaction ? $record->allocation->financial_year_id : null)
            ?? ($record instanceof \App\Models\JawanMarkazBudgetRevision ? $record->budget->financial_year_id : null);
    }

    public static function urlFor(Model $record): string
    {
        return match (true) {
            $record instanceof Budget => route('budget.budgets.show', $record),
            $record instanceof BudgetRevision => route('budget.budgets.show', $record->budget_id),
            $record instanceof BudgetAllocation => route('budget.allocations.show', $record),
            $record instanceof BudgetTransfer => route('budget.allocations.show', $record->from_allocation_id),
            $record instanceof BudgetPlan => route('budget.plans.show', $record),
            $record instanceof FinancialTransaction => route('budget.transactions.show', $record),
            $record instanceof PettyCashVoucher => route('budget.petty-cash.show', $record->fund_id),
            $record instanceof \App\Models\JawanMarkazBudget => route('jawan-markaz.budgets.show', $record),
            $record instanceof \App\Models\HostelBudget => route('hostel.budgets.show', $record),
            $record instanceof \App\Models\HostelBudgetRevision => route('hostel.budgets.show', $record->hostel_budget_id),
            $record instanceof \App\Models\JawanMarkazBudgetRevision => route('jawan-markaz.budgets.show', $record->jawan_markaz_budget_id),
            default => route('budget.overview'),
        };
    }
}
