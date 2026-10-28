<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetCommitment;
use App\Models\BudgetPlan;
use App\Models\BudgetRevision;
use App\Models\BudgetTransfer;
use App\Models\District;
use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Office;
use App\Models\PettyCashFund;
use App\Models\PettyCashVoucher;
use App\Models\User;
use App\Support\ReferenceNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * LOCAL DEMO DATA ONLY — a fictional 2026-27 budget (amounts are illustrative,
 * not real KP figures) with allocations, transfers, plans, commitments,
 * expenditure and petty cash. Finance (Ayesha Tariq) records and submits;
 * the approver (Asma Riaz) approves and verifies — the same separation of
 * duties the application enforces. Skipped when any financial year exists.
 */
class BudgetDemoSeeder extends Seeder
{
    protected User $finance;
    protected User $approver;

    public function run()
    {
        if (FinancialYear::exists()) {
            return;
        }
        $this->finance = User::where('email', 'ayesha.tariq@dya.test')->firstOrFail();
        $this->approver = User::where('email', 'asma.riaz@dya.test')->firstOrFail();

        $hq = Office::firstOrCreate(['district_id' => District::where('name', 'Peshawar')->value('id'), 'name' => 'Directorate Headquarters']);
        $office = fn ($name) => Office::where('name', $name)->firstOrFail();

        // ---- Form A: Directorate budget + an approved revision ------------------
        $this->at('2026-07-05');
        $fy = FinancialYear::create(['label' => '2026-27', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);
        $budget = new Budget([
            'original_amount' => 50000000, 'funding_source' => 'government_grant', 'budget_head' => 'youth_development',
            'approving_authority' => 'Finance Department, Government of Khyber Pakhtunkhwa', 'effective_on' => '2026-07-01',
            'remarks' => 'Illustrative demo figures only.',
        ]);
        $budget->financial_year_id = $fy->id;
        $this->create($budget, 'DB', submit: true);
        $this->at('2026-07-10');
        $budget->approved_on = now();
        $this->decide($budget, 'approved', 'Budget approved as notified.');

        $this->at('2026-09-15');
        $rev = new BudgetRevision(['direction' => 'increase', 'amount' => 2000000, 'effective_on' => '2026-09-15', 'reason' => 'Supplementary grant for youth sports week (demo).']);
        $rev->budget_id = $budget->id;
        $this->create($rev, 'BR', pending: true);
        $this->at('2026-09-18');
        $this->decide($rev, 'approved', 'Supplementary grant confirmed.');

        // ---- Form B: allocations -------------------------------------------------
        $rows = [
            ['Peshawar District Office', 'events', 3000000, 2800000],
            ['Peshawar District Office', 'sports', 2000000, 2000000],
            ['Peshawar District Office', 'training', 1500000, 1500000],
            ['Peshawar District Office', 'youth_development', 1200000, 1200000],
            ['Mardan Office', 'events', 2000000, 2000000],
            ['Mardan Office', 'training', 1000000, 1000000],
            ['Mardan Office', 'youth_development', 800000, 800000],
            ['Swat Office', 'events', 1500000, 1500000],
            ['Swat Office', 'training', 700000, 700000],
            ['Charsadda Office', 'events', 1000000, 1000000],
            ['Charsadda Office', 'youth_development', 600000, 600000],
            ['Abbottabad Office', 'events', 1000000, 1000000],
            ['Directorate Headquarters', 'administration', 6000000, 6000000],
            ['Kohat Office', 'events', 800000, null],       // submitted, awaiting approval
            ['Swat Office', 'sports', 500000, false],       // draft
        ];
        $alloc = [];
        foreach ($rows as [$officeName, $head, $planned, $approved]) {
            $this->at('2026-07-15');
            $a = new BudgetAllocation(['financial_year_id' => $fy->id, 'office_id' => $office($officeName)->id, 'budget_head' => $head,
                'allocation_date' => '2026-07-15', 'planned_amount' => $planned]);
            $this->create($a, 'AL', submit: $approved !== false);
            if ($approved) {
                $this->at('2026-07-18');
                $a->approved_amount = $approved;
                $this->decide($a, 'approved', $approved < $planned ? 'Approved at a reduced amount.' : null);
            }
            $alloc["{$officeName}|{$head}"] = $a;
        }

        // Transfers: one approved, one awaiting approval.
        $this->transfer($alloc['Peshawar District Office|sports'], $alloc['Peshawar District Office|events'], 200000, 'Additional seminars planned in Q2.', '2026-09-05', true);
        $this->transfer($alloc['Mardan Office|training'], $alloc['Mardan Office|events'], 100000, 'Shift to district youth festival.', '2026-10-10', false);

        // ---- Form D: budget plans ------------------------------------------------
        foreach ([
            ['Peshawar District Office', 1, 'seminar', 'events', 600000, '2026-08-20', 'approved'],
            ['Peshawar District Office', 2, 'sports', 'sports', 900000, '2026-10-25', 'approved'],
            ['Peshawar District Office', 3, 'training', 'training', 700000, '2027-02-10', 'submitted'],
            ['Peshawar District Office', 4, 'cultural', 'events', 500000, '2027-04-15', 'draft'],
            ['Mardan Office', 2, 'seminar', 'events', 400000, '2026-11-12', 'approved'],
            ['Mardan Office', 3, 'training', 'training', 350000, '2027-01-20', 'submitted'],
            ['Directorate Headquarters', 1, 'other', 'administration', 1500000, '2026-09-30', 'approved'],
            ['Directorate Headquarters', 2, 'other', 'administration', 1500000, '2026-12-15', 'approved'],
            ['Swat Office', 2, 'workshop', 'training', 250000, '2026-11-25', 'approved'],
        ] as [$officeName, $q, $cat, $head, $amount, $expected, $status]) {
            $this->at('2026-07-25');
            $p = new BudgetPlan(['financial_year_id' => $fy->id, 'quarter' => $q, 'office_id' => $office($officeName)->id, 'category' => $cat,
                'budget_head' => $head, 'planned_amount' => $amount, 'expected_on' => $expected, 'justification' => 'Annual work plan 2026-27 (demo).']);
            $this->create($p, 'BP', submit: $status !== 'draft');
            if ($status === 'approved') {
                $this->at('2026-07-28');
                $p->approval_reference = 'AWP-2026-27';
                $this->decide($p, 'approved');
            }
        }

        // ---- Commitment + expenditure at headquarters -----------------------------
        $this->at('2026-07-20');
        $amc = $this->commit($alloc['Directorate Headquarters|administration'], 'Annual maintenance contract — office equipment', 600000, 'AMC sanction DYA/ADM/2026/14 (demo)');
        foreach ([
            ['2026-07-31', 'services', 'Electricity & utilities — July', 'PESCO', 185000, 'verified', null],
            ['2026-08-31', 'services', 'Electricity & utilities — August', 'PESCO', 192000, 'verified', null],
            ['2026-08-14', 'materials', 'Printing of annual youth calendar', 'Khyber Printers', 98000, 'verified', null],
            ['2026-09-10', 'equipment', 'AMC first quarterly instalment', 'TechServe Pvt Ltd', 150000, 'verified', $amc],
            ['2026-09-30', 'services', 'Electricity & utilities — September', 'PESCO', 176000, 'verified', null],
            ['2026-10-12', 'transport', 'Vehicle fuel — October', 'PSO Station', 64000, 'pending', null],
            ['2026-10-05', 'materials', 'Duplicate invoice — stationery', 'City Stationers', 22000, 'rejected', null],
        ] as [$date, $cat, $desc, $payee, $amount, $status, $commitment]) {
            $this->at($date);
            $t = new FinancialTransaction(['txn_date' => $date, 'category' => $cat, 'description' => $desc, 'payee' => $payee, 'amount' => $amount, 'payment_reference' => 'CHQ-' . random_int(100000, 999999)]);
            $this->book($t, $alloc['Directorate Headquarters|administration'], $commitment);
            if ($status !== 'pending') {
                $this->at(Carbon::parse($date)->addDays(3));
                $this->decide($t, $status, $status === 'rejected' ? 'Duplicate of an invoice already paid.' : null);
            }
        }

        // ---- Form C: petty cash ---------------------------------------------------
        $ahmed = User::where('email', 'ahmed.khan@dya.test')->firstOrFail();
        $this->at('2026-07-01');
        $fund = $this->fund($office('Peshawar District Office'), $ahmed, 20000, 25000);
        foreach ([
            ['2026-07-14', 'expense', 'stationery', 'Stationery and printing supplies', 'ABC Stationers', 3000, null, 'verified'],
            ['2026-08-02', 'replenishment', null, 'Authorized replenishment', null, 10000, 'CHQ-552310', 'verified'],
            ['2026-09-03', 'expense', 'refreshments', 'Tea for district coordination meeting', 'Green Café', 1200, null, 'rejected'],
            ['2026-10-08', 'expense', 'printing', 'Photocopies of registration forms', 'Copy Centre', 2500, null, 'pending'],
        ] as [$date, $type, $cat, $desc, $payee, $amount, $ref, $status]) {
            $this->at($date);
            $v = new PettyCashVoucher(['type' => $type, 'voucher_date' => $date, 'category' => $cat, 'description' => $desc, 'payee' => $payee, 'amount' => $amount, 'replenishment_reference' => $ref]);
            $v->fund_id = $fund->id;
            $v->office_id = $fund->office_id;
            $this->create($v, 'PC', pending: true, column: 'voucher_no');
            if ($status !== 'pending') {
                $this->at(Carbon::parse($date)->addDay());
                $this->decide($v, $status, $status === 'rejected' ? 'Not an eligible petty cash expense.' : null);
            }
        }
        $this->at('2026-09-30');
        $book = $fund->balance();
        $rec = $fund->reconciliations()->make(['reconciled_on' => '2026-09-30', 'book_balance' => $book, 'physical_cash' => $book, 'difference' => 0, 'status' => 'balanced', 'remarks' => 'Quarter-end cash count.']);
        $rec->reconciled_by = $this->finance->id;
        $rec->save();

        $this->at('2026-07-01');
        $this->fund($hq, $this->finance, 30000, 50000);

        Carbon::setTestNow();
    }

    protected function create(Model $record, string $prefix, bool $submit = false, bool $pending = false, string $column = 'reference_no'): void
    {
        $record->status = $pending ? 'pending' : 'draft';
        $record->created_by = $record->updated_by = $record->submitted_by = $this->finance->id;
        ReferenceNumber::saveWith($record, $column, $prefix);
        $record->recordAction('created', null, $this->finance);
        if ($submit) {
            $record->recordAction('submitted', 'submitted', $this->finance);
        }
    }

    protected function decide(Model $record, string $to, ?string $remarks = null): void
    {
        $record->recordAction($to, $to, $this->approver, $remarks);
    }

    protected function transfer(BudgetAllocation $from, BudgetAllocation $to, float $amount, string $reason, string $date, bool $approve): void
    {
        $this->at($date);
        $t = new BudgetTransfer(['amount' => $amount, 'effective_on' => $date, 'reason' => $reason]);
        $t->from_allocation_id = $from->id;
        $t->to_allocation_id = $to->id;
        $t->office_id = $from->office_id;
        $this->create($t, 'TR', pending: true);
        if ($approve) {
            $this->at(Carbon::parse($date)->addDays(2));
            $this->decide($t, 'approved');
        }
    }

    protected function commit(BudgetAllocation $allocation, string $description, float $amount, string $authority): BudgetCommitment
    {
        $c = new BudgetCommitment(['description' => $description, 'amount' => $amount, 'committed_on' => now(), 'authority_reference' => $authority]);
        $c->allocation_id = $allocation->id;
        $c->financial_year_id = $allocation->financial_year_id;
        $c->office_id = $allocation->office_id;
        $c->status = 'open';
        $c->created_by = $c->updated_by = $c->submitted_by = $this->finance->id;
        ReferenceNumber::saveWith($c, 'reference_no', 'CM');
        $c->recordAction('created', null, $this->finance, $authority);

        return $c;
    }

    protected function book(FinancialTransaction $t, BudgetAllocation $allocation, ?BudgetCommitment $commitment): void
    {
        $t->allocation_id = $allocation->id;
        $t->financial_year_id = $allocation->financial_year_id;
        $t->office_id = $allocation->office_id;
        $t->commitment_id = $commitment?->id;
        $this->create($t, 'EX', pending: true);
    }

    protected function fund(Office $office, User $custodian, float $opening, float $limit): PettyCashFund
    {
        $fund = new PettyCashFund(['office_id' => $office->id, 'custodian_id' => $custodian->id, 'opening_balance' => $opening, 'limit' => $limit, 'opened_on' => now()]);
        $fund->status = 'active';
        $fund->created_by = $fund->updated_by = $this->finance->id;
        $fund->save();

        return $fund;
    }

    protected function at($date): void
    {
        Carbon::setTestNow(Carbon::parse($date)->setTime(random_int(9, 16), random_int(0, 59)));
    }
}
