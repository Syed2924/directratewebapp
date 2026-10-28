<?php

namespace App\Http\Controllers\JawanMarkaz;

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\District;
use App\Models\FinancialYear;
use App\Models\JawanMarkazExpense;
use App\Support\Audit;
use App\Support\JawanMarkazSummary;
use Illuminate\Http\Request;

/**
 * Center-wise summary and detailed expense report with the filters required by
 * the specification. Printable from the browser and exportable to CSV (Excel).
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('jawan_markaz.view');
        $fy = FinancialYear::fromRequest($request);
        $type = $request->input('type') === 'expenses' ? 'expenses' : 'summary';

        return view('jawan-markaz.reports.index', [
            'fy' => $fy,
            'type' => $type,
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'centers' => Center::withinCoverageOf($request->user())->orderBy('name')->get(),
            'districts' => District::whereIn('id', Center::withinCoverageOf($request->user())->pluck('district_id'))->orderBy('name')->get(),
            'summary' => $type === 'summary' ? JawanMarkazSummary::for($fy, $request->user(), $request->integer('center') ?: null, $request->integer('district') ?: null) : null,
            'expenses' => $type === 'expenses' ? $this->expenses($request, $fy)->get() : null,
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('jawan_markaz.export');
        $fy = FinancialYear::fromRequest($request);
        $type = $request->input('type') === 'expenses' ? 'expenses' : 'summary';

        if ($type === 'summary') {
            $s = JawanMarkazSummary::for($fy, $request->user(), $request->integer('center') ?: null, $request->integer('district') ?: null);
            $head = ['Center', 'District', 'Allocated', 'Pending Review', 'Approved', 'Paid', 'Approved Unpaid', 'Rejected', 'Available'];
            $rows = $s['rows']->map(fn ($r) => [$r['center']->name, $r['center']->district->name, $r['allocated'], $r['pending'], $r['approved'], $r['paid'], $r['unpaid'], $r['rejected'], $r['available']])->all();
        } else {
            $head = ['Expense ID', 'Center', 'District', 'Expense Date', 'Category', 'Description', 'Supplier', 'Invoice No.', 'Invoice Date', 'Requested', 'Approved', 'Paid', 'Status', 'Submitted By', 'Submitted On'];
            $rows = $this->expenses($request, $fy)->get()->map(fn ($e) => [
                $e->expense_number, $e->center->name, $e->center->district->name, $e->expense_date->format('Y-m-d'), $e->categoryLabel(), $e->description,
                $e->supplier_name, $e->invoice_number, $e->invoice_date->format('Y-m-d'), (float) $e->requested_amount, (float) $e->approved_amount,
                (float) $e->payments->sum('amount'), $e->statusLabel(), $e->submitter->name ?? '', $e->submitted_at?->format('Y-m-d'),
            ])->all();
        }
        Audit::log('jawan_markaz.report_exported', null, ['description' => "Jawan Markaz {$type} report " . ($fy?->label ?? '') . ' (' . count($rows) . ' rows)']);

        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $head);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => AuditLogController::csvSafe(is_float($v) ? number_format($v, 2, '.', '') : $v), $row));
            }
            fclose($out);
        }, "jawan-markaz-{$type}-" . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function expenses(Request $request, ?FinancialYear $fy)
    {
        $payment = $request->input('payment');

        return JawanMarkazExpense::visibleTo($request->user())->with('center.district', 'submitter', 'payments')
            ->when($fy, fn ($q) => $q->whereHas('budget', fn ($b) => $b->where('financial_year_id', $fy->id)))
            ->when($request->filled('center'), fn ($q) => $q->where('center_id', $request->integer('center')))
            ->when($request->filled('district'), fn ($q) => $q->whereHas('center', fn ($c) => $c->where('district_id', $request->integer('district'))))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('submitted_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('submitted_at', '<=', $request->input('to')))
            ->when($payment === 'unpaid', fn ($q) => $q->where('status', 'approved'))
            ->when($payment === 'partially_paid', fn ($q) => $q->where('status', 'partially_paid'))
            ->when($payment === 'paid', fn ($q) => $q->where('status', 'paid'))
            ->orderBy('center_id')->orderBy('expense_date');
    }
}
