<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Controller;
use App\Models\ActivityProposal;
use App\Models\Center;
use App\Models\District;
use App\Models\FinancialYear;
use App\Models\Hostel;
use App\Models\HostelBooking;
use App\Models\HostelPayment;
use App\Models\JawanMarkazExpense;
use App\Support\Audit;
use App\Support\BudgetSummary;
use App\Support\HostelAvailability;
use App\Support\JawanMarkazSummary;
use App\Support\Reports\ReportCatalog;
use App\Support\Reports\ReportFilters;
use Illuminate\Http\Request;

/**
 * Report Management: a dashboard, a catalog of reports, and each report on
 * screen, as a printable / PDF document and as an Excel (CSV) export.
 * reports.view opens reports; reports.export is needed to print or export.
 */
class ReportController extends Controller
{
    public function dashboard(Request $request)
    {
        $this->authorize('reports.view');
        $user = $request->user();
        $f = ReportFilters::fromRequest($request);
        $catalog = new ReportCatalog($user, $f);
        $fy = $f->fy;

        $proposals = ActivityProposal::visibleTo($user)->where('status', '!=', 'draft')->whereBetween('received_on', $f->dates())->with('activity')->get();
        $jm = JawanMarkazSummary::for($fy, $user);
        $hostels = Hostel::withinCoverageOf($user)->get();
        $budget = BudgetSummary::for($fy, $user);

        $months = [];
        if ($fy) {
            for ($m = $fy->starts_on->copy()->startOfMonth(); $m->lte($fy->ends_on); $m->addMonth()) {
                $months[] = ['label' => $m->format('M'), 'year' => $m->format('Y'), 'data' => $catalog->metrics($m->copy(), $m->copy()->endOfMonth())];
            }
        }

        return view('reports.dashboard', [
            'fy' => $fy,
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'activity' => [
                'total' => $proposals->count(),
                'approved' => $proposals->where('status', 'approved')->count(),
                'pending' => $proposals->whereIn('status', ['received', 'incomplete', 'under_review', 'returned', 'recommended'])->count(),
                'completed' => $proposals->filter(fn ($p) => $p->activity?->status === 'completed')->count(),
            ],
            'budget' => $budget,
            'jm' => $jm['totals'] + ['centers' => $jm['rows']->count(),
                'pending_count' => JawanMarkazExpense::visibleTo($user)->whereIn('status', ['submitted', 'under_review'])->count()],
            'hostel' => [
                'hostels' => $hostels->count(),
                'today' => HostelAvailability::today($hostels),
                'approved' => HostelBooking::visibleTo($user)->whereIn('status', ['approved', 'checked_in', 'checked_out'])->whereBetween('check_in', $f->dates())->count(),
                'spent' => (float) HostelPayment::visibleTo($user)->whereBetween('payment_date', $f->dates())->sum('amount'),
            ],
            'months' => $months,
        ]);
    }

    public function index(Request $request)
    {
        $this->authorize('reports.view');

        return view('reports.index');
    }

    public function show(Request $request, string $key)
    {
        $this->authorize('reports.view');
        abort_unless(isset(ReportCatalog::REPORTS[$key]), 404);
        $f = ReportFilters::fromRequest($request);

        return view('reports.show', $this->viewData($request, $key, $f));
    }

    /** Print-ready document (letterhead, period, summary, details) — use the browser's "Save as PDF". */
    public function print(Request $request, string $key)
    {
        $this->authorize('reports.export');
        abort_unless(isset(ReportCatalog::REPORTS[$key]), 404);
        $f = ReportFilters::fromRequest($request);
        Audit::log('reports.printed', null, ['description' => ReportCatalog::title($key) . ' — ' . $f->period()]);

        return view('reports.print', $this->viewData($request, $key, $f));
    }

    public function export(Request $request, string $key)
    {
        $this->authorize('reports.export');
        abort_unless(isset(ReportCatalog::REPORTS[$key]), 404);
        $f = ReportFilters::fromRequest($request);
        $report = (new ReportCatalog($request->user(), $f))->build($key);
        Audit::log('reports.exported', null, ['description' => ReportCatalog::title($key) . ' — ' . $f->period(), 'after' => array_filter($request->query())]);

        return response()->streamDownload(function () use ($report, $key, $f, $request) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Directorate of Youth Affairs, Khyber Pakhtunkhwa']);
            fputcsv($out, [ReportCatalog::title($key)]);
            fputcsv($out, ['Reporting period', $report['period'] ?? $f->period()]);
            fputcsv($out, ['Generated', now()->format('d M Y H:i') . ' by ' . $request->user()->name]);
            fputcsv($out, []);
            foreach ($report['summary'] as [$label, $value]) {
                fputcsv($out, [$label, is_float($value) ? number_format($value, 2, '.', '') : $value]);
            }
            foreach ($report['sections'] as $section) {
                fputcsv($out, []);
                fputcsv($out, [$section['title']]);
                fputcsv($out, array_column($section['columns'], 0));
                foreach ($section['rows'] as $row) {
                    fputcsv($out, array_map(fn ($v) => AuditLogController::csvSafe(is_float($v) ? number_format($v, 2, '.', '') : $v), $row));
                }
                if (! empty($section['totals']) && $section['rows']) {
                    fputcsv($out, self::totals($section));
                }
            }
            fclose($out);
        }, $key . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Totals row: integer and money columns summed; others blank. */
    public static function totals(array $section): array
    {
        $out = [];
        foreach ($section['columns'] as $i => [, $kind]) {
            $out[$i] = in_array($kind, ['int', 'money'], true) ? array_sum(array_column($section['rows'], $i)) : '';
        }
        $out[0] = 'Total';

        return $out;
    }

    protected function viewData(Request $request, string $key, ReportFilters $f): array
    {
        $user = $request->user();
        [$category, $title, $purpose, $filters] = ReportCatalog::REPORTS[$key];

        return [
            'key' => $key,
            'title' => $title,
            'purpose' => $purpose,
            'filters' => $filters,
            'f' => $f,
            'report' => (new ReportCatalog($user, $f))->build($key),
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'districts' => District::whereIn('id', $this->districtIds($user))->orderBy('name')->get(),
            'centers' => Center::withinCoverageOf($user)->orderBy('name')->get(),
            'hostels' => Hostel::withinCoverageOf($user)->orderBy('name')->get(),
            'categories' => $category === 'jawan_markaz' ? config('jawan_markaz.expense_categories') : config('activity.categories'),
            'statuses' => match ($category) {
                'jawan_markaz' => collect(config('jawan_markaz.expense_statuses'))->except('draft')->map(fn ($s) => $s[0])->all(),
                'hostel' => collect(config('hostel.booking_statuses'))->except('draft')->map(fn ($s) => $s[0])->all(),
                default => collect(config('activity.proposal_statuses'))->except('draft')->map(fn ($s) => $s[0])->all(),
            },
        ];
    }

    /** Districts the user can report on: their districts plus those of their offices, centers and hostels. */
    protected function districtIds($user): array
    {
        $c = $user->scopeCoverage();
        if ($c->global) {
            return District::pluck('id')->all();
        }

        return array_values(array_unique(array_merge(
            $c->ids('district'),
            \App\Models\Office::whereIn('id', $c->ids('office'))->pluck('district_id')->all(),
            Center::whereIn('id', $c->ids('center'))->pluck('district_id')->all(),
            Hostel::whereIn('id', $c->ids('hostel'))->pluck('district_id')->all(),
        )));
    }
}
