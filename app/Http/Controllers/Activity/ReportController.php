<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\District;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Activity reports: proposal pipeline, implementation summary and CSV export.
 * Everything is limited to the user's organizational scope.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Activity::class);
        $user = $request->user();

        $rows = $this->filtered($request)->latest('id')->paginate(20)->withQueryString();
        $all = $this->filtered($request)->get();

        $proposalCounts = ActivityProposal::visibleTo($user)
            ->when($request->filled('district'), fn ($q) => $q->where('district_id', (int) $request->input('district')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('activity.reports.index', [
            'rows' => $rows,
            'districts' => District::withinCoverageOf($user)->orderBy('name')->get(),
            'proposalCounts' => $proposalCounts,
            'totals' => [
                'activities' => $all->count(),
                'completed' => $all->where('status', 'completed')->count(),
                'participants' => $all->sum(fn ($a) => $a->participantsTotal()),
                'female' => $all->sum('actual_female'),
                'budget' => $all->sum(fn ($a) => (float) $a->proposal->estimated_budget),
                'spent' => $all->sum(fn ($a) => (float) $a->expenses_sum_amount),
            ],
            'byDistrict' => $all->groupBy(fn ($a) => $a->district->name)->map(fn ($g) => [
                'count' => $g->count(),
                'participants' => $g->sum(fn ($a) => $a->participantsTotal()),
                'spent' => $g->sum(fn ($a) => (float) $a->expenses_sum_amount),
            ])->sortKeys(),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('export', Activity::class);
        $query = $this->filtered($request)->latest('id');
        Audit::log('activity.report_exported', null, ['description' => 'Exported activity report (' . $query->count() . ' rows)', 'after' => array_filter($request->only('q', 'district', 'category', 'status', 'from', 'to'))]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Tracking No.', 'Activity Title', 'Category', 'Applicant / Organization', 'District', 'Status',
                'Approved On', 'Actual Start', 'Actual End', 'Location', 'Male', 'Female', 'Total Participants',
                'Estimated Budget (PKR)', 'Actual Expenses (PKR)', 'Verified On']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $a) {
                    $p = $a->proposal;
                    fputcsv($out, array_map([AuditLogController::class, 'csvSafe'], [
                        $p->tracking_no, $p->title, $p->categoryLabel(), $p->applicant_name, $a->district->name, $a->statusLabel(),
                        $p->decided_on?->format('Y-m-d'), $a->actual_start?->format('Y-m-d'), $a->actual_end?->format('Y-m-d'), $a->location,
                        $a->actual_male, $a->actual_female, $a->participantsTotal(),
                        number_format((float) $p->estimated_budget, 2, '.', ''), number_format((float) $a->expenses_sum_amount, 2, '.', ''),
                        $a->verified_at?->format('Y-m-d'),
                    ]));
                }
            });
            fclose($out);
        }, 'activity-report-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function filtered(Request $request)
    {
        return Activity::visibleTo($request->user())
            ->with('proposal', 'district')
            ->withSum('expenses', 'amount')
            ->when($request->filled('q'), fn ($q) => $q->whereHas('proposal', fn ($p) => $p->search($request->input('q'))))
            ->when($request->filled('district'), fn ($q) => $q->where('district_id', (int) $request->input('district')))
            ->when($request->filled('category'), fn ($q) => $q->whereHas('proposal', fn ($p) => $p->where('category', $request->input('category'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('actual_start', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('actual_start', '<=', $request->input('to')));
    }
}
