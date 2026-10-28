<?php

namespace Database\Seeders;

use App\Models\BudgetAllocation;
use App\Models\BudgetCommitment;
use App\Models\Center;
use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\JawanMarkazBudget;
use App\Models\JawanMarkazBudgetRevision;
use App\Models\JawanMarkazExpense;
use App\Models\JawanMarkazPayment;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Support\ReferenceNumber;
use App\Support\ScopeAssignment;
use App\Support\ScopeCoverage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * LOCAL DEMO DATA ONLY — center profiles, center staff, a central "Jawan Markaz
 * Operations" allocation, center budgets and expenses at every stage.
 * Staff submit, Asma Riaz (approver) decides, Ayesha Tariq (finance) pays —
 * the separation of duties the application enforces. Skipped if any center budget exists.
 */
class JawanMarkazDemoSeeder extends Seeder
{
    protected User $finance;
    protected User $approver;

    public function run()
    {
        if (JawanMarkazBudget::exists() || ! ($fy = FinancialYear::where('label', '2026-27')->first())) {
            return;
        }
        $this->finance = User::where('email', 'ayesha.tariq@dya.test')->firstOrFail();
        $this->approver = User::where('email', 'asma.riaz@dya.test')->firstOrFail();
        $admin = User::where('email', 'alsyedk123@gmail.com')->firstOrFail();
        $center = fn ($n) => Center::where('name', $n)->firstOrFail();

        // ---- Center staff and profiles -------------------------------------------
        $staff = [];
        foreach ([['Abdul Wahab', 'abdul.wahab@dya.test', 'Jawan Markaz Mardan'], ['Bilal Shah', 'bilal.shah@dya.test', 'Jawan Markaz Peshawar']] as [$name, $email, $centerName]) {
            $u = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make(DemoSeeder::DEMO_PASSWORD), 'status' => 'active',
                'must_change_password' => false, 'password_changed_at' => now(), 'designation' => 'Center In-charge']);
            $u->roles()->sync([Role::where('slug', 'jawan_markaz_staff')->value('id')]);
            ScopeAssignment::save($u, new ScopeCoverage(false, ['center' => [$center($centerName)->id]]));
            $staff[$centerName] = $u;
        }
        foreach ([
            'Jawan Markaz Peshawar' => ['University Road, Peshawar', '+92 91 9216543', 'peshawar@jawanmarkaz.pk', $staff['Jawan Markaz Peshawar']],
            'Jawan Markaz Mardan' => ['Main Road, Mardan, Khyber Pakhtunkhwa', '+92 300 1234567', 'mardan@jawanmarkaz.pk', $staff['Jawan Markaz Mardan']],
            'Jawan Markaz Mingora' => ['Saidu Sharif Road, Mingora, Swat', '+92 946 711230', 'mingora@jawanmarkaz.pk', null],
            'Jawan Markaz Charsadda' => ['Nowshera Road, Charsadda', '+92 91 6510234', 'charsadda@jawanmarkaz.pk', null],
            'Jawan Markaz Abbottabad' => ['Mansehra Road, Abbottabad', '+92 992 330145', 'abbottabad@jawanmarkaz.pk', null],
        ] as $name => [$location, $phone, $email, $incharge]) {
            $center($name)->update(['location' => $location, 'phone' => $phone, 'email' => $email, 'incharge_id' => $incharge?->id]);
        }

        // ---- Central funding: "Jawan Markaz Operations" allocation ---------------
        $this->at('2026-07-16');
        $central = new BudgetAllocation(['financial_year_id' => $fy->id, 'office_id' => Office::where('name', 'Directorate Headquarters')->value('id'),
            'budget_head' => config('jawan_markaz.budget_head'), 'allocation_date' => '2026-07-16', 'planned_amount' => 11000000, 'remarks' => 'Funds for all Jawan Markaz centers (demo).']);
        $central->status = 'draft';
        $central->created_by = $central->updated_by = $central->submitted_by = $this->finance->id;
        ReferenceNumber::saveWith($central, 'reference_no', 'AL');
        $central->recordAction('created', null, $this->finance);
        $central->recordAction('submitted', 'submitted', $this->finance);
        $this->at('2026-07-18');
        $central->approved_amount = 11000000;
        $central->recordAction('approved', 'approved', $this->approver);

        // ---- Center budgets --------------------------------------------------------
        $budgets = [];
        foreach ([
            ['Jawan Markaz Peshawar', 3000000, true], ['Jawan Markaz Mardan', 2500000, true], ['Jawan Markaz Mingora', 2000000, true],
            ['Jawan Markaz Abbottabad', 1500000, true], ['Jawan Markaz Charsadda', 1000000, false],
        ] as [$name, $amount, $approve]) {
            $this->at('2026-07-20');
            $b = new JawanMarkazBudget(['center_id' => $center($name)->id, 'financial_year_id' => $fy->id, 'allocated_amount' => $amount,
                'allocation_date' => '2026-07-20', 'authorization_reference' => 'DYA/JM/2026/' . random_int(10, 99)]);
            $b->status = 'draft';
            $b->created_by = $b->updated_by = $b->submitted_by = $this->finance->id;
            ReferenceNumber::saveWith($b, 'reference_no', 'JM-BUD');
            $b->recordAction('created', null, $this->finance);
            $b->recordAction('submitted', 'submitted', $this->finance);
            if ($approve) {
                $this->at('2026-07-22');
                $c = new BudgetCommitment(['description' => "{$name} — center budget {$fy->label}", 'amount' => $amount, 'committed_on' => now(), 'authority_reference' => $b->authorization_reference]);
                $c->allocation_id = $central->id;
                $c->financial_year_id = $fy->id;
                $c->office_id = $central->office_id;
                $c->status = 'open';
                $c->created_by = $c->updated_by = $c->submitted_by = $this->approver->id;
                ReferenceNumber::saveWith($c, 'reference_no', 'CM');
                $c->recordAction('created', null, $this->approver, $b->authorization_reference);
                $b->commitment_id = $c->id;
                $b->recordAction('approved', 'approved', $this->approver, 'Center budget approved.');
            }
            $budgets[$name] = $b;
        }

        // Approved revision: Mardan +200,000 (central commitment follows).
        $this->at('2026-09-20');
        $rev = new JawanMarkazBudgetRevision(['direction' => 'increase', 'amount' => 200000, 'reason' => 'Additional funds for youth sports week (demo).']);
        $rev->jawan_markaz_budget_id = $budgets['Jawan Markaz Mardan']->id;
        $rev->status = 'pending';
        $rev->created_by = $rev->updated_by = $rev->submitted_by = $this->finance->id;
        ReferenceNumber::saveWith($rev, 'reference_no', 'JM-REV');
        $rev->recordAction('created', null, $this->finance, $rev->reason);
        $this->at('2026-09-22');
        $rev->recordAction('approved', 'approved', $this->approver);
        $cm = $budgets['Jawan Markaz Mardan']->commitment;
        $cm->amount = (float) $cm->amount + 200000;
        $cm->recordAction('updated', null, $this->approver, "Adjusted by {$rev->reference_no}: + PKR 200,000");

        // ---- Expenses at every stage ------------------------------------------------
        $by = fn ($name) => $staff[$name] ?? $admin;
        foreach ([
            ['Jawan Markaz Peshawar', '2026-08-05', 'office_supplies', 'Stationery and office supplies for center', 'ABC Stationers', 'INV-2026-015', 25000, 'paid'],
            ['Jawan Markaz Mardan', '2026-08-12', 'travel', 'Travel for district youth coordination', 'Khyber Travels', 'KT-4471', 40000, 'paid'],
            ['Jawan Markaz Mingora', '2026-08-20', 'equipment', 'Sports equipment for indoor hall', 'Swat Sports House', 'SSH-0912', 75000, 'partially_paid'],
            ['Jawan Markaz Abbottabad', '2026-09-03', 'utilities', 'Electricity bill — August', 'PESCO', 'EB-88213', 18500, 'paid'],
            ['Jawan Markaz Peshawar', '2026-09-15', 'training', 'Computer skills training — resource person', 'Dr. Kamran Ali', 'HON-2026-07', 60000, 'approved'],
            ['Jawan Markaz Mardan', '2026-09-28', 'maintenance', 'Repair of water cooler and fans', 'Mardan Electric Works', 'MEW-1203', 22000, 'approved'],
            ['Jawan Markaz Peshawar', '2026-10-06', 'events', 'Youth debate competition — venue decoration', 'Gul Events', 'GE-5520', 48000, 'under_review'],
            ['Jawan Markaz Mardan', '2026-10-10', 'office_supplies', 'Printer toner and paper', 'ABC Stationers', 'INV-2026-015', 25000, 'submitted'],
            ['Jawan Markaz Mardan', '2026-10-02', 'events', 'Refreshments for youth seminar', 'City Caterers', 'CC-301', 35000, 'returned'],
            ['Jawan Markaz Abbottabad', '2026-09-18', 'other', 'Personal mobile top-up', 'Jazz Franchise', 'JZ-77', 3000, 'rejected'],
            ['Jawan Markaz Peshawar', '2026-10-14', 'utilities', 'Internet bill — October', 'PTCL', 'PT-99812', 7500, 'draft'],
        ] as [$name, $date, $cat, $desc, $supplier, $invoice, $amount, $stage]) {
            $this->expense($budgets[$name], $by($name), $date, $cat, $desc, $supplier, $invoice, $amount, $stage);
        }

        Carbon::setTestNow();
    }

    protected function expense(JawanMarkazBudget $budget, User $submitter, string $date, string $cat, string $desc, string $supplier, string $invoice, float $amount, string $stage): void
    {
        $this->at($date);
        $e = new JawanMarkazExpense(['expense_date' => $date, 'category' => $cat, 'description' => $desc, 'supplier_name' => $supplier,
            'invoice_number' => $invoice, 'invoice_date' => $date, 'requested_amount' => $amount]);
        $e->center_id = $budget->center_id;
        $e->jawan_markaz_budget_id = $budget->id;
        $e->status = 'draft';
        $e->created_by = $e->updated_by = $e->submitted_by = $submitter->id;
        ReferenceNumber::saveWith($e, 'expense_number', 'EXP');
        $e->recordAction('created', null, $submitter);
        if ($stage === 'draft') {
            return;
        }

        $this->at(Carbon::parse($date)->addDay());
        $e->submitted_at = now();
        $e->recordAction('submitted', 'submitted', $submitter);
        if ($stage === 'submitted') {
            return;
        }

        $this->at(Carbon::parse($date)->addDays(3));
        if ($stage === 'under_review') {
            $e->recordAction('under_review', 'under_review', $this->approver);

            return;
        }
        $e->reviewed_by = $this->approver->id;
        $e->decided_at = now();
        if ($stage === 'returned') {
            $e->recordAction('returned', 'returned', $this->approver, 'Invoice is not signed by the supplier. Please attach a signed copy.');

            return;
        }
        if ($stage === 'rejected') {
            $e->recordAction('rejected', 'rejected', $this->approver, 'Personal expense — not an eligible center expense.');

            return;
        }
        $e->approved_amount = $amount;
        $e->recordAction('approved', 'approved', $this->approver, 'Invoice checked; within budget.');
        if ($stage === 'approved') {
            return;
        }

        $this->pay($e, $budget, $stage === 'partially_paid' ? round($amount * 0.6) : $amount, Carbon::parse($date)->addDays(7));
    }

    protected function pay(JawanMarkazExpense $e, JawanMarkazBudget $budget, float $amount, Carbon $when): void
    {
        $this->at($when);
        $commitment = $budget->commitment;
        $ref = 'CHQ-' . random_int(100000, 999999);
        $t = new FinancialTransaction(['txn_date' => $when, 'category' => config("jawan_markaz.central_categories.{$e->category}", 'other'),
            'description' => "{$budget->center->name} — {$e->expense_number}: {$e->description}", 'payee' => $e->supplier_name, 'amount' => $amount, 'payment_reference' => $ref]);
        $t->allocation_id = $commitment->allocation_id;
        $t->financial_year_id = $commitment->financial_year_id;
        $t->office_id = $commitment->office_id;
        $t->commitment_id = $commitment->id;
        $t->status = 'pending';
        $t->created_by = $t->updated_by = $t->submitted_by = $this->finance->id;
        ReferenceNumber::saveWith($t, 'reference_no', 'EX');
        $t->recordAction('created', null, $this->finance);
        $t->recordAction('verified', 'verified', $this->approver, "Approved as Jawan Markaz expense {$e->expense_number} by {$this->approver->name}.");

        $p = new JawanMarkazPayment(['amount' => $amount, 'payment_date' => $when, 'method' => 'cheque', 'transaction_reference' => $ref]);
        $p->jawan_markaz_expense_id = $e->id;
        $p->center_id = $e->center_id;
        $p->financial_transaction_id = $t->id;
        $p->recorded_by = $this->finance->id;
        ReferenceNumber::saveWith($p, 'reference_no', 'PAY');

        $to = $amount >= (float) $e->approved_amount ? 'paid' : 'partially_paid';
        $e->recordAction($to, $to, $this->finance, "{$p->reference_no}: PKR " . number_format($amount, 2) . " by Cheque ({$ref})");
    }

    protected function at($date): void
    {
        Carbon::setTestNow(Carbon::parse($date)->setTime(random_int(9, 16), random_int(0, 59)));
    }
}
