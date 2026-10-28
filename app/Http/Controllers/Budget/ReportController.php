<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Controller;
use App\Models\BudgetPlan;
use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Office;
use App\Models\PettyCashFund;
use App\Support\Audit;
use App\Support\BudgetSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Financial reports. Every report is built once (build()) and used for both
 * the screen and the CSV export, so the two can never disagree.
 */
class ReportController extends Controller
{
    public const TYPES = [
        'budget_vs_actual' => 'Budget vs Actual (by allocation)',
        'by_office' => 'Office-wise Summary',
        'by_category' => 'Category-wise Expenditure',
        'planned_vs_actual' => 'Planned vs Actual (by quarter)',
        'petty_cash' => 'Petty Cash Statement',
    ];

    public function index(Request $request)
    {
        $this->authorize('budget.view');
        $fy = FinancialYear::fromRequest($request);
        $type = array_key_exists($request->input('type'), self::TYPES) ? $request->input('type') : 'budget_vs_actual';

        return view('budget.reports.index', [
            'fy' => $fy,
            'type' => $type,
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'offices' => Office::withinCoverageOf($request->user())->orderBy('name')->get(),
            'report' => $fy ? $this->build($type, $fy, $request) : null,
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('budget.export');
        $fy = FinancialYear::fromRequest($request);
        abort_unless($fy, 404);
        $type = array_key_exists($request->input('type'), self::TYPES) ? $request->input('type') : 'budget_vs_actual';
        $report = $this->build($type, $fy, $request);
        Audit::log('budget.report_exported', null, ['description' => self::TYPES[$type] . " {$fy->label} (" . count($report['rows']) . ' rows)']);

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_column($report['columns'], 0));
            foreach (array_merge($report['rows'], $report['totals'] ? [$report['totals']] : []) as $row) {
                fputcsv($out, array_map(fn ($v) => AuditLogController::csvSafe(is_float($v) ? number_format($v, 2, '.', '') : $v), $row));
            }
            fclose($out);
        }, "budget-{$type}-{$fy->label}-" . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{columns: array<array{0:string,1:string}>, rows: array, totals: ?array} column = [label, 'text'|'money'|'pct'] */
    protected function build(string $type, FinancialYear $fy, Request $request): array
    {
        $user = $request->user();
        $office = $request->filled('office') ? (int) $request->input('office') : null;
        $money = fn ($label) => [$label, 'money'];

        switch ($type) {
            case 'by_office':
                $rows = BudgetSummary::allocations($fy, $user, $office)->groupBy('office_id')->map(function ($g) {
                    $ok = $g->where('status', 'approved');
                    $alloc = $ok->sum(fn ($a) => $a->revised());
                    $spent = $g->sum(fn ($a) => $a->spent());

                    return [$g->first()->office->name, $g->first()->office->district->name, $alloc, $ok->sum(fn ($a) => $a->committed()), $spent, $ok->sum(fn ($a) => $a->available()), $alloc > 0 ? $spent / $alloc * 100 : 0.0];
                })->sortBy(0)->values()->all();
                $columns = [['Office', 'text'], ['District', 'text'], $money('Allocated'), $money('Committed'), $money('Spent'), $money('Remaining'), ['Utilization', 'pct']];

                return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, $columns)];

            case 'by_category':
                $rows = FinancialTransaction::visibleTo($user)->where('financial_year_id', $fy->id)->where('status', '!=', 'rejected')
                    ->when($office, fn ($q) => $q->where('office_id', $office))
                    ->selectRaw("category, SUM(amount) as total, SUM(CASE WHEN status = 'verified' THEN amount ELSE 0 END) as verified, COUNT(*) as n")
                    ->groupBy('category')->orderByDesc('total')->get()
                    ->map(fn ($r) => [config("budget.expense_categories.{$r->category}", $r->category), (int) $r->n, (float) $r->total, (float) $r->verified, (float) $r->total - (float) $r->verified])->all();
                $grand = array_sum(array_column($rows, 2));
                $rows = array_map(fn ($r) => array_merge($r, [$grand > 0 ? $r[2] / $grand * 100 : 0.0]), $rows);
                $columns = [['Category', 'text'], ['Entries', 'text'], $money('Total Spent'), $money('Verified'), $money('Pending Verification'), ['Share', 'pct']];

                return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, $columns)];

            case 'planned_vs_actual':
                $planned = BudgetPlan::visibleTo($user)->where('financial_year_id', $fy->id)->where('status', '!=', 'rejected')
                    ->when($office, fn ($q) => $q->where('office_id', $office))->get()
                    ->groupBy(fn ($p) => $p->quarter . '|' . $p->budget_head)->map->sum('planned_amount');
                $actual = FinancialTransaction::visibleTo($user)->where('financial_year_id', $fy->id)->where('status', '!=', 'rejected')
                    ->when($office, fn ($q) => $q->where('office_id', $office))->with('allocation:id,budget_head')->get()
                    ->groupBy(fn ($t) => $this->quarterOf($fy, $t->txn_date) . '|' . $t->allocation->budget_head)->map->sum('amount');
                $rows = $planned->keys()->merge($actual->keys())->unique()->sort()->map(function ($key) use ($planned, $actual) {
                    [$q, $head] = explode('|', $key);
                    $p = (float) ($planned[$key] ?? 0);
                    $a = (float) ($actual[$key] ?? 0);

                    return [config("budget.quarters.{$q}"), config("budget.budget_heads.{$head}", $head), $p, $a, $p - $a, $p > 0 ? $a / $p * 100 : 0.0];
                })->values()->all();
                $columns = [['Quarter', 'text'], ['Budget Head', 'text'], $money('Planned'), $money('Actual'), $money('Variance'), ['Actual / Planned', 'pct']];

                return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, $columns)];

            case 'petty_cash':
                $rows = PettyCashFund::visibleTo($user)->when($office, fn ($q) => $q->where('office_id', $office))->with('office', 'custodian', 'reconciliations')->get()
                    ->map(function ($f) {
                        $t = $f->totals();
                        $rec = $f->reconciliations->first();

                        return [$f->office->name, $f->custodian->name ?? '—', $t['opening'], $t['received'], $t['paid'], $t['balance'],
                            $rec ? $rec->reconciled_on->format('d M Y') . ($rec->difference != 0 ? ' (diff ' . number_format($rec->difference) . ')' : ' (balanced)') : 'Never'];
                    })->all();
                $columns = [['Office', 'text'], ['Custodian', 'text'], $money('Opening'), $money('Received'), $money('Paid'), $money('Closing Balance'), ['Last Reconciliation', 'text']];

                return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, $columns)];

            default: // budget_vs_actual
                $rows = BudgetSummary::allocations($fy, $user, $office)->map(fn ($a) => [
                    $a->reference_no, $a->office->name, $a->headLabel(), $a->statusLabel(),
                    $a->revised(), $a->committed(), $a->spent(), $a->available(), $a->revised() > 0 ? $a->spent() / $a->revised() * 100 : 0.0,
                ])->all();
                $columns = [['Reference', 'text'], ['Office', 'text'], ['Budget Head', 'text'], ['Status', 'text'], $money('Allocated'), $money('Committed'), $money('Spent'), $money('Remaining'), ['Utilization', 'pct']];

                return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->totals($rows, $columns)];
        }
    }

    /** Totals row: money columns summed, other columns left blank (percentages are not additive). */
    protected function totals(array $rows, array $columns): ?array
    {
        if (! $rows) {
            return null;
        }
        $totals = [];
        foreach ($columns as $i => [, $kind]) {
            $totals[$i] = $kind === 'money' ? array_sum(array_column($rows, $i)) : '';
        }
        $totals[0] = 'Total';

        return $totals;
    }

    /** Financial-year quarter (1–4) of a date. */
    protected function quarterOf(FinancialYear $fy, $date): int
    {
        return intdiv($fy->starts_on->copy()->startOfMonth()->diffInMonths(Carbon::parse($date)->startOfMonth()), 3) + 1;
    }
}
