<?php

namespace App\Support\Reports;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\BudgetPlan;
use App\Models\BudgetRevision;
use App\Models\BudgetTransfer;
use App\Models\District;
use App\Models\FinancialTransaction;
use App\Models\Hostel;
use App\Models\HostelBooking;
use App\Models\HostelBudget;
use App\Models\HostelPayment;
use App\Models\HostelRoom;
use App\Models\JawanMarkazExpense;
use App\Models\JawanMarkazPayment;
use App\Models\User;
use App\Support\BudgetSummary;
use App\Support\HostelAvailability;
use App\Support\JawanMarkazSummary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every report in the Report Management module. Reports read the source
 * module records (never copies), always through the user's organizational
 * scope, so a report can never show more than its reader may see.
 *
 * Expenditure is always taken from the central expense register
 * (financial_transactions, rejected excluded), where activity expenses,
 * Jawan Markaz payments and hostel payments are each recorded exactly once.
 *
 * A built report: ['summary' => [[label, value, kind]], 'sections' => [[title, columns => [[label, kind]], rows, totals?]], 'chart' => ?]
 * kinds: text | int | money | pct
 */
class ReportCatalog
{
    public const CATEGORIES = [
        'activity' => ['Activity Reports', 'bi-calendar-event', 'tint-blue', 'Proposals, activities and completion reports'],
        'budget' => ['Budget & Planning Reports', 'bi-wallet2', 'tint-green', 'Budget, allocations, utilization and planning'],
        'jawan_markaz' => ['Jawan Markaz Reports', 'bi-building', 'tint-purple', 'Center-wise budgets, expenses and payments'],
        'hostel' => ['Hostel Booking Reports', 'bi-house-door', 'tint-amber', 'Bookings, occupancy and hostel finances'],
        'consolidated' => ['Consolidated Reports', 'bi-diagram-3', 'tint-rose', 'Directorate-wide and periodic performance'],
    ];

    /** key => [category, title, purpose, filters] */
    public const REPORTS = [
        'activity_summary' => ['activity', 'Activity Summary Report', 'Summarizes proposals and activities', ['fy', 'dates', 'district', 'category', 'status', 'q']],
        'activity_district' => ['activity', 'District-wise Activity Report', 'Compares activities across districts', ['fy', 'dates', 'category']],
        'activity_completion' => ['activity', 'Activity Completion Report', 'Completed activities, participants, expenditure and evidence', ['fy', 'dates', 'district', 'category']],
        'budget_directorate' => ['budget', 'Directorate Budget Report', 'Approved budget, allocations, expenditure and balances', ['fy', 'district']],
        'budget_utilization' => ['budget', 'Budget Utilization Report', 'Expenditure against allocations, by office', ['fy', 'district']],
        'budget_planning' => ['budget', 'Budget Planning Report', 'Planned versus actual spending by quarter', ['fy', 'district']],
        'jm_financial' => ['jawan_markaz', 'Jawan Markaz Financial Report', 'Budgets, expenses and payments by center', ['fy', 'district', 'center']],
        'jm_expenses' => ['jawan_markaz', 'Expense Approval Report', 'Pending, approved, returned and rejected center expenses', ['fy', 'dates', 'district', 'center', 'category', 'status', 'payment']],
        'hostel_register' => ['hostel', 'Hostel Booking Register', 'Hostel reservations and their statuses', ['dates', 'district', 'hostel', 'status']],
        'hostel_occupancy' => ['hostel', 'Hostel Occupancy Report', 'Room occupancy and availability', ['dates', 'district', 'hostel']],
        'hostel_financial' => ['hostel', 'Hostel Financial Report', 'Hostel budget, expenditure and remaining balance', ['fy', 'district', 'hostel']],
        'monthly' => ['consolidated', 'Monthly Performance Report', 'Departmental activity for one month', ['month', 'district']],
        'annual' => ['consolidated', 'Annual Performance Report', 'Yearly overview, month by month', ['fy', 'district']],
        'consolidated' => ['consolidated', 'Consolidated Directorate Report', 'Authorized information from all modules combined', ['fy', 'district']],
    ];

    public static function title(string $key): string
    {
        return self::REPORTS[$key][1];
    }

    public function __construct(protected User $user, protected ReportFilters $f)
    {
    }

    public function build(string $key): array
    {
        return $this->{'build' . str_replace('_', '', ucwords($key, '_'))}();
    }

    // ===================================================================== Activity

    protected function proposals()
    {
        return ActivityProposal::visibleTo($this->user)->where('status', '!=', 'draft')
            ->whereBetween('received_on', $this->f->dates())
            ->when($this->f->district, fn ($q) => $q->where('district_id', $this->f->district))
            ->when($this->f->category, fn ($q) => $q->where('category', $this->f->category));
    }

    protected function spentByProposal(array $ids): Collection
    {
        return FinancialTransaction::whereIn('proposal_id', $ids)->where('status', '!=', 'rejected')
            ->selectRaw('proposal_id, SUM(amount) total')->groupBy('proposal_id')->pluck('total', 'proposal_id');
    }

    protected function buildActivitySummary(): array
    {
        $all = $this->proposals()->with('district', 'activity')->get();
        $rows = $all->when($this->f->status, fn ($c) => $c->where('status', $this->f->status))
            ->when($this->f->q, fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p->title . ' ' . $p->tracking_no . ' ' . $p->applicant_name), mb_strtolower($this->f->q))));
        $spent = $this->spentByProposal($rows->pluck('id')->all());
        $pending = ['received', 'incomplete', 'under_review', 'returned', 'recommended'];

        return [
            'summary' => [
                ['Total Proposals', $all->count(), 'int'], ['Pending', $all->whereIn('status', $pending)->count(), 'int'],
                ['Approved', $all->where('status', 'approved')->count(), 'int'], ['Rejected', $all->where('status', 'rejected')->count(), 'int'],
                ['In Progress', $all->filter(fn ($p) => $p->activity && $p->activity->status !== 'completed')->count(), 'int'],
                ['Completed', $all->filter(fn ($p) => $p->activity?->status === 'completed')->count(), 'int'],
            ],
            'sections' => [[
                'title' => 'Proposals and Activities',
                'columns' => [['Tracking No.', 'text'], ['Title', 'text'], ['Category', 'text'], ['District', 'text'], ['Received', 'text'], ['Status', 'text'],
                    ['Expected Participants', 'int'], ['Actual Participants', 'int'], ['Estimated Budget', 'money'], ['Actual Expenditure', 'money']],
                'rows' => $rows->sortBy('received_on')->map(fn ($p) => [
                    $p->tracking_no, $p->title, $p->categoryLabel(), $p->district->name, $p->received_on->format('d M Y'),
                    $p->activity ? $p->activity->statusLabel() : $p->statusLabel(), $p->participantsTotal(), $p->activity?->participantsTotal() ?? 0,
                    (float) $p->estimated_budget, (float) ($spent[$p->id] ?? 0),
                ])->values()->all(),
                'totals' => true,
            ]],
            'chart' => $this->chart('Proposals by category', $all->groupBy('category')->map(fn ($g, $k) => [config("activity.categories.$k", $k), [$g->count(), $g->whereIn('status', ['approved'])->count()]])->values(), ['Received', 'Approved']),
        ];
    }

    protected function buildActivityDistrict(): array
    {
        $all = $this->proposals()->with('district', 'activity')->get();
        $spent = $this->spentByProposal($all->pluck('id')->all());
        $rows = $all->groupBy(fn ($p) => $p->district->name)->map(function ($g, $name) use ($spent) {
            $acts = $g->pluck('activity')->filter();

            return [$name, $g->count(), $g->where('status', 'approved')->count(), $g->where('status', 'rejected')->count(),
                $acts->where('status', 'completed')->count(), $g->sum(fn ($p) => $p->participantsTotal()), $acts->sum(fn ($a) => $a->participantsTotal()),
                (float) $g->where('status', 'approved')->sum('estimated_budget'), (float) $g->sum(fn ($p) => $spent[$p->id] ?? 0)];
        })->sortKeys()->values();

        return [
            'summary' => [['Districts', $rows->count(), 'int'], ['Proposals', $all->count(), 'int'], ['Completed Activities', $rows->sum(4), 'int'], ['Actual Participants', $rows->sum(6), 'int'], ['Actual Expenditure', $rows->sum(8), 'money']],
            'sections' => [[
                'title' => 'District-wise Activities',
                'columns' => [['District', 'text'], ['Proposals', 'int'], ['Approved', 'int'], ['Rejected', 'int'], ['Completed', 'int'], ['Expected Participants', 'int'], ['Actual Participants', 'int'], ['Approved Budget', 'money'], ['Actual Expenditure', 'money']],
                'rows' => $rows->all(), 'totals' => true,
            ]],
            'chart' => $this->chart('Proposals and completed activities by district', $rows->map(fn ($r) => [$r[0], [$r[1], $r[4]]]), ['Proposals', 'Completed']),
        ];
    }

    protected function buildActivityCompletion(): array
    {
        $acts = Activity::visibleTo($this->user)->where('status', 'completed')->whereBetween('verified_at', [$this->f->from, $this->f->to])
            ->when($this->f->district, fn ($q) => $q->where('district_id', $this->f->district))
            ->when($this->f->category, fn ($q) => $q->whereHas('proposal', fn ($p) => $p->where('category', $this->f->category)))
            ->with('proposal', 'district', 'verifier')->withCount('documents')->withSum('expenses', 'amount')->orderBy('verified_at')->get();

        return [
            'summary' => [['Completed Activities', $acts->count(), 'int'], ['Participants', $acts->sum(fn ($a) => $a->participantsTotal()), 'int'],
                ['Female Participants', $acts->sum('actual_female'), 'int'], ['Approved Budget', (float) $acts->sum(fn ($a) => $a->proposal->estimated_budget), 'money'],
                ['Actual Expenditure', (float) $acts->sum('expenses_sum_amount'), 'money']],
            'sections' => [[
                'title' => 'Completed Activities',
                'columns' => [['Tracking No.', 'text'], ['Activity', 'text'], ['District', 'text'], ['Held', 'text'], ['Expected', 'int'], ['Actual', 'int'],
                    ['Approved Budget', 'money'], ['Actual Expenditure', 'money'], ['Evidence Files', 'int'], ['Verified By', 'text'], ['Verified On', 'text']],
                'rows' => $acts->map(fn ($a) => [$a->proposal->tracking_no, $a->proposal->title, $a->district->name, $a->actual_start?->format('d M Y') ?? '—',
                    $a->proposal->participantsTotal(), $a->participantsTotal(), (float) $a->proposal->estimated_budget, (float) $a->expenses_sum_amount,
                    $a->documents_count, $a->verifier->name ?? '—', $a->verified_at?->format('d M Y')])->all(),
                'totals' => true,
            ]],
        ];
    }

    // ===================================================================== Budget & Planning

    protected function allocations(): Collection
    {
        return $this->f->fy ? BudgetSummary::allocations($this->f->fy, $this->user)
            ->when($this->f->district, fn ($c) => $c->filter(fn ($a) => $a->office->district_id === $this->f->district))->values() : collect();
    }

    protected function buildBudgetDirectorate(): array
    {
        $s = BudgetSummary::for($this->f->fy, $this->user);
        $allocs = $this->allocations();
        $summary = [];
        if ($s['global'] && $s['budget']) {
            $summary[] = ['Original Budget', (float) $s['budget']->original_amount, 'money'];
            $summary[] = ['Revised Budget', (float) $s['revised'], 'money'];
        }
        $ok = $allocs->where('status', 'approved');
        $summary = array_merge($summary, [
            ['Allocated', $ok->sum(fn ($a) => $a->revised()), 'money'], ['Committed', $ok->sum(fn ($a) => $a->committed()), 'money'],
            ['Actual Expenditure', $allocs->sum(fn ($a) => $a->spent()), 'money'], ['Remaining in Allocations', $ok->sum(fn ($a) => $a->available()), 'money'],
        ]);
        if ($s['global'] && $s['unallocated'] !== null && ! $this->f->district) {
            $summary[] = ['Not Yet Allocated', (float) $s['unallocated'], 'money'];
        }

        $sections = [[
            'title' => 'Allocations by Office',
            'columns' => [['Reference', 'text'], ['Office', 'text'], ['Budget Head', 'text'], ['Status', 'text'], ['Approved', 'money'], ['Transfers In', 'money'], ['Transfers Out', 'money'],
                ['Revised', 'money'], ['Committed', 'money'], ['Spent', 'money'], ['Remaining', 'money']],
            'rows' => $allocs->map(fn ($a) => [$a->reference_no, $a->office->name, $a->headLabel(), $a->statusLabel(), $a->status === 'approved' ? (float) $a->approved_amount : 0.0,
                $a->transfersInTotal(), $a->transfersOutTotal(), $a->revised(), $a->committed(), $a->spent(), $a->status === 'approved' ? $a->available() : 0.0])->all(),
            'totals' => true,
        ]];
        if ($s['global'] && $s['budget']) {
            $sections[] = ['title' => 'Budget Revisions', 'columns' => [['Reference', 'text'], ['Effective', 'text'], ['Change', 'money'], ['Reason', 'text'], ['Status', 'text']],
                'rows' => BudgetRevision::where('budget_id', $s['budget']->id)->orderBy('effective_on')->get()
                    ->map(fn ($r) => [$r->reference_no, $r->effective_on->format('d M Y'), $r->signedAmount(), $r->reason, $r->statusLabel()])->all()];
        }
        $ids = $allocs->pluck('id');
        $sections[] = ['title' => 'Budget Transfers', 'columns' => [['Reference', 'text'], ['From', 'text'], ['To', 'text'], ['Amount', 'money'], ['Effective', 'text'], ['Status', 'text']],
            'rows' => BudgetTransfer::whereIn('from_allocation_id', $ids)->orWhereIn('to_allocation_id', $ids)->with('from.office', 'to.office')->orderBy('effective_on')->get()
                ->map(fn ($t) => [$t->reference_no, $t->from->label(), $t->to->label(), (float) $t->amount, $t->effective_on->format('d M Y'), $t->statusLabel()])->all()];

        return ['summary' => $summary, 'sections' => $sections];
    }

    protected function buildBudgetUtilization(): array
    {
        $rows = $this->allocations()->groupBy('office_id')->map(function ($g) {
            $ok = $g->where('status', 'approved');
            $alloc = $ok->sum(fn ($a) => $a->revised());
            $spent = $g->sum(fn ($a) => $a->spent());

            return [$g->first()->office->name, $g->first()->office->district->name, $alloc, $ok->sum(fn ($a) => $a->committed()), $spent,
                $ok->sum(fn ($a) => $a->available()), $alloc > 0 ? round($spent / $alloc * 100, 1) : 0.0];
        })->sortBy(0)->values();
        $alloc = $rows->sum(2);

        return [
            'summary' => [['Allocated', $alloc, 'money'], ['Committed', $rows->sum(3), 'money'], ['Spent', $rows->sum(4), 'money'], ['Remaining', $rows->sum(5), 'money'],
                ['Utilization', $alloc > 0 ? round($rows->sum(4) / $alloc * 100, 1) : 0, 'pct']],
            'sections' => [['title' => 'Utilization by Office', 'columns' => [['Office', 'text'], ['District', 'text'], ['Allocated', 'money'], ['Committed', 'money'], ['Spent', 'money'], ['Remaining', 'money'], ['Utilization', 'pct']],
                'rows' => $rows->all(), 'totals' => true]],
            'chart' => $this->chart('Allocated vs spent by office', $rows->map(fn ($r) => [$r[0], [$r[2], $r[4]]]), ['Allocated', 'Spent'], true),
        ];
    }

    protected function buildBudgetPlanning(): array
    {
        $fy = $this->f->fy;
        if (! $fy) {
            return ['summary' => [], 'sections' => []];
        }
        $inDistrict = fn ($q) => $this->f->district ? $q->whereHas('office', fn ($o) => $o->where('district_id', $this->f->district)) : $q;
        $planned = $inDistrict(BudgetPlan::visibleTo($this->user)->where('financial_year_id', $fy->id)->where('status', '!=', 'rejected'))->get()
            ->groupBy(fn ($p) => $p->quarter . '|' . $p->budget_head)->map->sum('planned_amount');
        $quarter = fn ($d) => intdiv($fy->starts_on->copy()->startOfMonth()->diffInMonths(Carbon::parse($d)->startOfMonth()), 3) + 1;
        $actual = $inDistrict(FinancialTransaction::visibleTo($this->user)->where('financial_year_id', $fy->id)->where('status', '!=', 'rejected'))
            ->with('allocation:id,budget_head')->get()->groupBy(fn ($t) => $quarter($t->txn_date) . '|' . $t->allocation->budget_head)->map->sum('amount');
        $rows = $planned->keys()->merge($actual->keys())->unique()->sort()->map(function ($k) use ($planned, $actual) {
            [$q, $head] = explode('|', $k);
            $p = (float) ($planned[$k] ?? 0);
            $a = (float) ($actual[$k] ?? 0);

            return [config("budget.quarters.$q"), config("budget.budget_heads.$head", $head), $p, $a, $p - $a, $p > 0 ? round($a / $p * 100, 1) : 0.0];
        })->values();
        $byQ = collect(config('budget.quarters'))->map(fn ($label) => [$label, [$rows->where(0, $label)->sum(2), $rows->where(0, $label)->sum(3)]])->values();

        return [
            'summary' => [['Planned', $rows->sum(2), 'money'], ['Actual', $rows->sum(3), 'money'], ['Variance', $rows->sum(4), 'money']],
            'sections' => [['title' => 'Planned vs Actual by Quarter and Budget Head', 'columns' => [['Quarter', 'text'], ['Budget Head', 'text'], ['Planned', 'money'], ['Actual', 'money'], ['Variance', 'money'], ['Actual / Planned', 'pct']],
                'rows' => $rows->all(), 'totals' => true]],
            'chart' => $this->chart('Planned vs actual by quarter', $byQ, ['Planned', 'Actual'], true),
        ];
    }

    // ===================================================================== Jawan Markaz

    protected function buildJmFinancial(): array
    {
        $s = JawanMarkazSummary::for($this->f->fy, $this->user, $this->f->center, $this->f->district);
        $t = $s['totals'];
        $centerIds = $s['rows']->pluck('center.id');
        $byCat = JawanMarkazExpense::visibleTo($this->user)->whereIn('center_id', $centerIds)->where('status', '!=', 'draft')
            ->when($this->f->fy, fn ($q) => $q->whereHas('budget', fn ($b) => $b->where('financial_year_id', $this->f->fy->id)))
            ->with('payments')->get()->groupBy('category')->map(fn ($g, $k) => [
                config("jawan_markaz.expense_categories.$k", $k), $g->count(), (float) $g->sum('requested_amount'),
                (float) $g->whereIn('status', ['approved', 'partially_paid', 'paid'])->sum('approved_amount'), (float) $g->sum(fn ($e) => $e->payments->sum('amount')),
            ])->sortByDesc(2)->values();

        return [
            'summary' => [['Centers', $s['rows']->count(), 'int'], ['Allocated', $t['allocated'], 'money'], ['Pending Review', $t['pending'], 'money'], ['Approved', $t['approved'], 'money'],
                ['Paid', $t['paid'], 'money'], ['Approved Unpaid', $t['unpaid'], 'money'], ['Remaining', $t['available'], 'money']],
            'sections' => [
                ['title' => 'Center-wise Summary', 'columns' => [['Center', 'text'], ['District', 'text'], ['Allocated', 'money'], ['Pending Review', 'money'], ['Approved', 'money'], ['Rejected', 'money'], ['Paid', 'money'], ['Approved Unpaid', 'money'], ['Remaining', 'money']],
                    'rows' => $s['rows']->map(fn ($r) => [$r['center']->name, $r['center']->district->name, $r['allocated'], $r['pending'], $r['approved'], $r['rejected'], $r['paid'], $r['unpaid'], $r['available']])->all(), 'totals' => true],
                ['title' => 'Expense Category Summary', 'columns' => [['Category', 'text'], ['Expenses', 'int'], ['Requested', 'money'], ['Approved', 'money'], ['Paid', 'money']], 'rows' => $byCat->all(), 'totals' => true],
            ],
            'chart' => $this->chart('Allocated vs paid by center', $s['rows']->map(fn ($r) => [str_replace('Jawan Markaz ', '', $r['center']->name), [$r['allocated'], $r['paid']]]), ['Allocated', 'Paid'], true),
        ];
    }

    protected function buildJmExpenses(): array
    {
        $all = JawanMarkazExpense::visibleTo($this->user)->where('status', '!=', 'draft')
            ->whereBetween('expense_date', $this->f->dates())
            ->when($this->f->center, fn ($q) => $q->where('center_id', $this->f->center))
            ->when($this->f->district, fn ($q) => $q->whereHas('center', fn ($c) => $c->where('district_id', $this->f->district)))
            ->when($this->f->category, fn ($q) => $q->where('category', $this->f->category))
            ->with('center', 'submitter', 'reviewer', 'payments')->orderBy('expense_date')->get();
        $rows = $all->when($this->f->status, fn ($c) => $c->where('status', $this->f->status))
            ->when($this->f->payment, fn ($c) => $c->where('status', $this->f->payment === 'unpaid' ? 'approved' : $this->f->payment));
        $count = fn ($s) => $all->whereIn('status', (array) $s)->count();

        return [
            'summary' => [['Submitted', $all->count(), 'int'], ['Pending', $count(['submitted', 'under_review']), 'int'], ['Approved', $count(['approved', 'partially_paid', 'paid']), 'int'],
                ['Returned', $count('returned'), 'int'], ['Rejected', $count('rejected'), 'int'], ['Paid', (float) $all->sum(fn ($e) => $e->payments->sum('amount')), 'money']],
            'sections' => [['title' => 'Expenses', 'columns' => [['Expense ID', 'text'], ['Center', 'text'], ['Date', 'text'], ['Category', 'text'], ['Supplier / Invoice', 'text'],
                ['Requested', 'money'], ['Approved', 'money'], ['Paid', 'money'], ['Status', 'text'], ['Decided By', 'text']],
                'rows' => $rows->map(fn ($e) => [$e->expense_number, $e->center->name, $e->expense_date->format('d M Y'), $e->categoryLabel(), "{$e->supplier_name} / {$e->invoice_number}",
                    (float) $e->requested_amount, (float) $e->approved_amount, (float) $e->payments->sum('amount'), $e->statusLabel(), $e->reviewer->name ?? '—'])->values()->all(),
                'totals' => true]],
        ];
    }

    // ===================================================================== Hostel

    protected function hostels(): Collection
    {
        return Hostel::withinCoverageOf($this->user)->with('district')
            ->when($this->f->hostel, fn ($q) => $q->whereKey($this->f->hostel))
            ->when($this->f->district, fn ($q) => $q->where('district_id', $this->f->district))->orderBy('name')->get();
    }

    protected function buildHostelRegister(): array
    {
        $hostels = $this->hostels();
        $all = HostelBooking::visibleTo($this->user)->whereIn('hostel_id', $hostels->pluck('id'))->where('status', '!=', 'draft')
            ->where('check_in', '<=', $this->f->to->toDateString())->where('check_out', '>', $this->f->from->toDateString())
            ->with('hostel', 'district')->withCount('rooms')->orderBy('check_in')->get();
        $rows = $all->when($this->f->status, fn ($c) => $c->where('status', $this->f->status));
        $confirmed = $all->whereIn('status', ['approved', 'checked_in', 'checked_out']);

        return [
            'summary' => [['Booking Requests', $all->count(), 'int'], ['Approved', $confirmed->count(), 'int'], ['Pending', $all->where('status', 'pending')->count(), 'int'],
                ['Rejected', $all->where('status', 'rejected')->count(), 'int'], ['Cancelled', $all->where('status', 'cancelled')->count(), 'int'],
                ['Rooms Requested', $all->sum('room_count'), 'int'], ['Rooms Confirmed', $confirmed->sum('rooms_count'), 'int'],
                ['Rooms Occupied', (int) $all->whereIn('status', ['checked_in', 'checked_out'])->sum('occupied_rooms'), 'int']],
            'sections' => [['title' => 'Booking Register', 'columns' => [['Booking ID', 'text'], ['Request Date', 'text'], ['Hostel', 'text'], ['District/HQ', 'text'], ['Guest', 'text'], ['CNIC/Passport', 'text'],
                ['Organization', 'text'], ['Purpose', 'text'], ['Check-in', 'text'], ['Check-out', 'text'], ['Guests', 'int'], ['Rooms', 'int'], ['Status', 'text']],
                'rows' => $rows->map(fn ($b) => [$b->booking_no, $b->request_date->format('d M Y'), $b->hostel->name, $b->district->name, $b->guest_name, $b->maskedCnic(),
                    $b->organization ?? '—', $b->purposeLabel(), $b->check_in->format('d M Y'), $b->check_out->format('d M Y'), $b->guest_count, $b->room_count, $b->statusLabel()])->values()->all(),
                'totals' => true]],
            'chart' => $this->chart('Bookings by hostel', $hostels->map(fn ($h) => [str_replace(' Youth Hostel', '', $h->name), [$all->where('hostel_id', $h->id)->count(), $confirmed->where('hostel_id', $h->id)->count()]]), ['Requested', 'Approved']),
        ];
    }

    protected function buildHostelOccupancy(): array
    {
        $hostels = $this->hostels();
        $from = $this->f->from->copy()->startOfDay();
        $end = $this->f->to->copy()->addDay()->startOfDay();
        $nights = max(1, $from->diffInDays($end));
        $today = HostelAvailability::today($hostels);
        $rows = $hostels->map(function ($h) use ($from, $end, $nights, $today) {
            $bookable = HostelRoom::where('hostel_id', $h->id)->where('status', 'available')->count();
            $used = HostelBooking::where('hostel_id', $h->id)->whereIn('status', ['approved', 'checked_in', 'checked_out'])
                ->where('check_in', '<', $end->toDateString())->where('check_out', '>', $from->toDateString())->withCount('rooms')->get()
                ->sum(function ($b) use ($from, $end) {
                    $s = $b->check_in->gt($from) ? $b->check_in : $from;
                    $e = $b->check_out->lt($end) ? $b->check_out : $end;

                    return $b->rooms_count * max(0, $s->diffInDays($e, false));
                });
            $cap = $bookable * $nights;
            $t = $today['by_hostel'][$h->id];

            return [$h->name, $h->district->name, $bookable, $t['occupied'], $t['reserved'], $t['free'], $cap, $used, $cap > 0 ? round($used / $cap * 100, 1) : 0.0];
        })->values();

        return [
            'summary' => [['Hostels', $hostels->count(), 'int'], ['Bookable Rooms', $today['rooms'], 'int'], ['Occupied Today', $today['occupied'], 'int'], ['Reserved Today', $today['reserved'], 'int'],
                ['Available Today', $today['free'], 'int'], ['Guests Staying', $today['guests'], 'int'], ['Period Occupancy', $rows->sum(6) > 0 ? round($rows->sum(7) / $rows->sum(6) * 100, 1) : 0, 'pct']],
            'sections' => [['title' => 'Occupancy by Hostel', 'columns' => [['Hostel', 'text'], ['District', 'text'], ['Bookable Rooms', 'int'], ['Occupied Today', 'int'], ['Reserved Today', 'int'],
                ['Available Today', 'int'], ['Room-Nights in Period', 'int'], ['Room-Nights Booked', 'int'], ['Occupancy', 'pct']], 'rows' => $rows->all(), 'totals' => true]],
            'chart' => $this->chart('Room-nights available vs booked', $rows->map(fn ($r) => [str_replace(' Youth Hostel', '', $r[0]), [$r[6], $r[7]]]), ['Available', 'Booked']),
        ];
    }

    protected function buildHostelFinancial(): array
    {
        $rows = $this->hostels()->map(function ($h) {
            $b = $this->f->fy ? HostelBudget::where('hostel_id', $h->id)->where('financial_year_id', $this->f->fy->id)->first() : null;
            $f = $b?->figures();

            return [$h->name, $h->district->name, $b?->statusLabel() ?? 'No budget', (float) ($f['original'] ?? 0), (float) ($f['revisions'] ?? 0), (float) ($f['allocated'] ?? 0),
                (float) ($f['paid'] ?? 0), (float) ($f['unpaid'] ?? 0), (float) ($f['remaining'] ?? 0), (float) ($f['available'] ?? 0)];
        })->values();

        return [
            'summary' => [['Original Budget', $rows->sum(3), 'money'], ['Revised Budget', $rows->sum(5), 'money'], ['Expenditure', $rows->sum(6), 'money'],
                ['Outstanding Commitments', $rows->sum(7), 'money'], ['Remaining', $rows->sum(8), 'money'], ['Available to Commit', $rows->sum(9), 'money']],
            'sections' => [['title' => 'Hostel Budgets', 'columns' => [['Hostel', 'text'], ['District', 'text'], ['Status', 'text'], ['Original', 'money'], ['Revisions', 'money'], ['Revised', 'money'],
                ['Expenditure', 'money'], ['Commitments', 'money'], ['Remaining', 'money'], ['Available to Commit', 'money']], 'rows' => $rows->all(), 'totals' => true]],
            'chart' => $this->chart('Revised budget vs expenditure by hostel', $rows->map(fn ($r) => [str_replace(' Youth Hostel', '', $r[0]), [$r[5], $r[6]]]), ['Revised', 'Spent'], true),
        ];
    }

    // ===================================================================== Consolidated / periodic

    /** Departmental figures for a date range, each from its source records (expenditure counted once). */
    public function metrics(Carbon $from, Carbon $to): array
    {
        $d = [$from->toDateString(), $to->toDateString()];
        $district = $this->f->district;
        $proposals = ActivityProposal::visibleTo($this->user)->where('status', '!=', 'draft')->whereBetween('received_on', $d)
            ->when($district, fn ($q) => $q->where('district_id', $district));
        $approved = ActivityProposal::visibleTo($this->user)->where('status', 'approved')->whereBetween('decided_on', $d)
            ->when($district, fn ($q) => $q->where('district_id', $district));
        $completed = Activity::visibleTo($this->user)->where('status', 'completed')->whereBetween('verified_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($district, fn ($q) => $q->where('district_id', $district))->get();
        $spent = FinancialTransaction::visibleTo($this->user)->where('status', '!=', 'rejected')->whereBetween('txn_date', $d)
            ->when($district, fn ($q) => $q->whereHas('office', fn ($o) => $o->where('district_id', $district)));
        $jm = JawanMarkazPayment::visibleTo($this->user)->whereBetween('payment_date', $d)->when($district, fn ($q) => $q->whereHas('center', fn ($c) => $c->where('district_id', $district)));
        $jmSubmitted = JawanMarkazExpense::visibleTo($this->user)->where('status', '!=', 'draft')->whereBetween('expense_date', $d)
            ->when($district, fn ($q) => $q->whereHas('center', fn ($c) => $c->where('district_id', $district)));
        $bookings = HostelBooking::visibleTo($this->user)->where('status', '!=', 'draft')->whereBetween('check_in', $d)
            ->when($district, fn ($q) => $q->whereHas('hostel', fn ($h) => $h->where('district_id', $district)));
        $hostelPaid = HostelPayment::visibleTo($this->user)->whereBetween('payment_date', $d)->when($district, fn ($q) => $q->whereHas('expense.hostel', fn ($h) => $h->where('district_id', $district)));

        return [
            'proposals' => (clone $proposals)->count(),
            'approved' => (clone $approved)->count(),
            'completed' => $completed->count(),
            'participants' => $completed->sum(fn ($a) => $a->participantsTotal()),
            'expenditure' => (float) (clone $spent)->sum('amount'),
            'activity_spent' => (float) (clone $spent)->whereNotNull('proposal_id')->sum('amount'),
            'jm_submitted' => (clone $jmSubmitted)->count(),
            'jm_paid' => (float) (clone $jm)->sum('amount'),
            'bookings' => (clone $bookings)->count(),
            'bookings_confirmed' => (clone $bookings)->whereIn('status', ['approved', 'checked_in', 'checked_out'])->count(),
            'guests' => (int) (clone $bookings)->whereIn('status', ['checked_in', 'checked_out'])->sum('arrived_guests'),
            'hostel_paid' => (float) (clone $hostelPaid)->sum('amount'),
        ];
    }

    protected const METRIC_COLUMNS = [
        ['Proposals Received', 'int', 'proposals'], ['Proposals Approved', 'int', 'approved'], ['Activities Completed', 'int', 'completed'], ['Participants', 'int', 'participants'],
        ['Total Expenditure', 'money', 'expenditure'], ['Activity Expenditure', 'money', 'activity_spent'], ['JM Expenses Submitted', 'int', 'jm_submitted'],
        ['JM Payments', 'money', 'jm_paid'], ['Hostel Bookings', 'int', 'bookings'], ['Bookings Confirmed', 'int', 'bookings_confirmed'], ['Guests', 'int', 'guests'], ['Hostel Payments', 'money', 'hostel_paid'],
    ];

    protected function buildMonthly(): array
    {
        $m = $this->metrics($this->f->month->copy(), $this->f->month->copy()->endOfMonth());
        $groups = ['Activities' => ['proposals', 'approved', 'completed', 'participants', 'activity_spent'], 'Finance' => ['expenditure'],
            'Jawan Markaz' => ['jm_submitted', 'jm_paid'], 'Hostels' => ['bookings', 'bookings_confirmed', 'guests', 'hostel_paid']];
        $labels = collect(self::METRIC_COLUMNS)->keyBy(2);
        $rows = [];
        foreach ($groups as $group => $keys) {
            foreach ($keys as $k) {
                $rows[] = [$group, $labels[$k][0], $labels[$k][1] === 'money' ? 'PKR ' . number_format($m[$k]) : number_format($m[$k])];
            }
        }

        return [
            'period' => $this->f->month->format('F Y'),
            'summary' => [['Proposals Received', $m['proposals'], 'int'], ['Activities Completed', $m['completed'], 'int'], ['Total Expenditure', $m['expenditure'], 'money'], ['Hostel Bookings', $m['bookings'], 'int']],
            'sections' => [['title' => 'Performance for ' . $this->f->month->format('F Y'), 'columns' => [['Area', 'text'], ['Measure', 'text'], ['Value', 'text']], 'rows' => $rows]],
        ];
    }

    protected function buildAnnual(): array
    {
        $fy = $this->f->fy;
        if (! $fy) {
            return ['summary' => [], 'sections' => []];
        }
        $rows = [];
        for ($m = $fy->starts_on->copy()->startOfMonth(); $m->lte($fy->ends_on); $m->addMonth()) {
            $x = $this->metrics($m->copy(), $m->copy()->endOfMonth());
            $rows[] = array_merge([$m->format('M Y')], array_map(fn ($c) => $x[$c[2]], self::METRIC_COLUMNS));
        }
        $col = fn ($i) => array_sum(array_column($rows, $i));

        return [
            'summary' => [['Proposals Received', $col(1), 'int'], ['Activities Completed', $col(3), 'int'], ['Participants', $col(4), 'int'], ['Total Expenditure', $col(5), 'money'],
                ['JM Payments', $col(8), 'money'], ['Hostel Bookings', $col(9), 'int'], ['Guests', $col(11), 'int']],
            'sections' => [['title' => "Month by Month — FY {$fy->label}", 'columns' => array_merge([['Month', 'text']], array_map(fn ($c) => [$c[0], $c[1]], self::METRIC_COLUMNS)), 'rows' => $rows, 'totals' => true]],
            'chart' => $this->chart('Monthly expenditure and proposals', collect($rows)->map(fn ($r) => [substr($r[0], 0, 3), [$r[5], $r[8] + $r[12]]]), ['Total expenditure', 'JM + hostel payments'], true),
        ];
    }

    protected function buildConsolidated(): array
    {
        $fy = $this->f->fy;
        $budget = BudgetSummary::for($fy, $this->user);
        $m = $this->metrics($this->f->from, $this->f->to);
        $jm = JawanMarkazSummary::for($fy, $this->user, null, $this->f->district)['totals'];
        $hostels = $this->hostels();
        $today = HostelAvailability::today($hostels);

        // Module-wise expenditure from the central register: each transaction belongs to exactly one bucket.
        $txns = FinancialTransaction::visibleTo($this->user)->where('status', '!=', 'rejected')->whereBetween('txn_date', $this->f->dates())
            ->when($this->f->district, fn ($q) => $q->whereHas('office', fn ($o) => $o->where('district_id', $this->f->district)))->get(['id', 'amount', 'proposal_id']);
        $jmIds = JawanMarkazPayment::whereIn('financial_transaction_id', $txns->pluck('id'))->pluck('financial_transaction_id')->flip();
        $hIds = HostelPayment::whereIn('financial_transaction_id', $txns->pluck('id'))->pluck('financial_transaction_id')->flip();
        $bucket = $txns->groupBy(fn ($t) => $t->proposal_id ? 'Activities' : ($jmIds->has($t->id) ? 'Jawan Markaz' : ($hIds->has($t->id) ? 'Hostels' : 'Other Directorate spending')))->map->sum('amount');
        $total = (float) $txns->sum('amount');

        $districts = District::whereIn('id', $this->f->district ? [$this->f->district] : ($this->user->scopeCoverage()->ids('district') ?? District::pluck('id')->all()))->orderBy('name')->get();
        $districtRows = $districts->map(function ($d) use ($fy) {
            $props = ActivityProposal::visibleTo($this->user)->where('district_id', $d->id)->where('status', '!=', 'draft')->whereBetween('received_on', $this->f->dates());
            $acts = Activity::visibleTo($this->user)->where('district_id', $d->id)->where('status', 'completed')->get();
            $allocs = $fy ? BudgetSummary::allocations($fy, $this->user)->filter(fn ($a) => $a->office->district_id === $d->id && $a->status === 'approved') : collect();

            return [$d->name, (clone $props)->count(), $acts->count(), $acts->sum(fn ($a) => $a->participantsTotal()),
                (float) $allocs->sum(fn ($a) => $a->revised()), (float) $allocs->sum(fn ($a) => $a->spent()),
                HostelBooking::visibleTo($this->user)->whereHas('hostel', fn ($h) => $h->where('district_id', $d->id))->whereIn('status', ['approved', 'checked_in', 'checked_out'])->whereBetween('check_in', $this->f->dates())->count()];
        })->filter(fn ($r) => array_sum(array_slice($r, 1)) > 0)->values();

        $summary = [['Proposals', $m['proposals'], 'int'], ['Activities Completed', $m['completed'], 'int']];
        if ($budget['revised'] !== null && ! $this->f->district) {
            $summary[] = ['Directorate Budget', (float) $budget['revised'], 'money'];
        }
        $summary = array_merge($summary, [['Total Expenditure', $total, 'money'], ['JM Paid', $jm['paid'], 'money'], ['Hostel Bookings', $m['bookings'], 'int'],
            ['Hostel Occupancy Today', $today['rooms'] ? round($today['occupied'] / $today['rooms'] * 100, 1) : 0, 'pct']]);

        return [
            'summary' => $summary,
            'sections' => [
                ['title' => 'Module-wise Summary', 'columns' => [['Module', 'text'], ['Measure', 'text'], ['Value', 'text']], 'rows' => [
                    ['Activity', 'Proposals received / approved / completed', "{$m['proposals']} / {$m['approved']} / {$m['completed']}"],
                    ['Activity', 'Participants in completed activities', number_format($m['participants'])],
                    ['Budget & Planning', 'Allocated / committed / spent', 'PKR ' . number_format($budget['allocated']) . ' / ' . number_format($budget['committed']) . ' / ' . number_format($budget['spent'])],
                    ['Jawan Markaz', 'Center budgets / approved / paid', 'PKR ' . number_format($jm['allocated']) . ' / ' . number_format($jm['approved']) . ' / ' . number_format($jm['paid'])],
                    ['Hostels', 'Bookings / confirmed / guests', "{$m['bookings']} / {$m['bookings_confirmed']} / {$m['guests']}"],
                    ['Hostels', 'Rooms occupied / available today', "{$today['occupied']} / {$today['free']} of {$today['rooms']}"],
                ]],
                ['title' => 'Expenditure by Module (central register, each transaction counted once)', 'columns' => [['Module', 'text'], ['Expenditure', 'money'], ['Share', 'pct']],
                    'rows' => $bucket->sortDesc()->map(fn ($v, $k) => [$k, (float) $v, $total > 0 ? round($v / $total * 100, 1) : 0.0])->values()->all(), 'totals' => true],
                ['title' => 'District-wise Performance', 'columns' => [['District', 'text'], ['Proposals', 'int'], ['Activities Completed', 'int'], ['Participants', 'int'], ['Allocated', 'money'], ['Spent', 'money'], ['Hostel Bookings', 'int']],
                    'rows' => $districtRows->all(), 'totals' => true],
            ],
            'chart' => $this->chart('Allocated vs spent by district', $districtRows->map(fn ($r) => [$r[0], [$r[4], $r[5]]]), ['Allocated', 'Spent'], true),
        ];
    }

    // ===================================================================== helpers

    /** Two-series grouped bar chart definition: rows of [label, [a, b]]. */
    protected function chart(string $title, $rows, array $series, bool $money = false): ?array
    {
        $rows = collect($rows)->values();

        return $rows->isEmpty() ? null : ['title' => $title, 'series' => $series, 'rows' => $rows->all(), 'money' => $money];
    }
}
