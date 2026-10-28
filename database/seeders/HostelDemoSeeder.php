<?php

namespace Database\Seeders;

use App\Models\BudgetAllocation;
use App\Models\BudgetCommitment;
use App\Models\District;
use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Hostel;
use App\Models\HostelBooking;
use App\Models\HostelBudget;
use App\Models\HostelBudgetRevision;
use App\Models\HostelExpense;
use App\Models\HostelPayment;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\HostelAvailability;
use App\Support\ReferenceNumber;
use App\Support\ScopeAssignment;
use App\Support\ScopeCoverage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * LOCAL DEMO DATA ONLY — hostel profiles, rooms, bookings at every stage around
 * today, and hostel budgets/expenses funded by a central "Hostel Operations"
 * allocation. Hostel staff request, Asma Riaz approves, Ayesha Tariq pays.
 * Skipped if any booking exists.
 */
class HostelDemoSeeder extends Seeder
{
    protected User $finance;
    protected User $approver;

    public function run()
    {
        if (HostelBooking::exists() || ! ($fy = FinancialYear::where('label', '2026-27')->first())) {
            return;
        }
        $this->finance = User::where('email', 'ayesha.tariq@dya.test')->firstOrFail();
        $this->approver = User::where('email', 'asma.riaz@dya.test')->firstOrFail();
        $admin = User::where('email', 'alsyedk123@gmail.com')->firstOrFail();

        // Finance records hostel payments (proposed default, also in config/access.php).
        Role::where('slug', 'finance_staff')->first()?->permissions()->syncWithoutDetaching([Permission::where('key', 'hostel.record_payment')->value('id')]);

        // ---- Hostels, rooms, staff -----------------------------------------------
        $mardan = Hostel::firstOrCreate(['district_id' => District::where('name', 'Mardan')->value('id'), 'name' => 'Mardan Youth Hostel']);
        $h = fn ($n) => Hostel::where('name', $n)->firstOrFail();
        $layout = [
            'Peshawar Youth Hostel' => ['Khyber Road, Peshawar Cantt', '+92 91 9211450', [['single', 1, 12], ['double', 2, 10], ['triple', 3, 6], ['dormitory', 8, 2]]],
            'Mardan Youth Hostel' => ['Shamsi Road, Mardan', '+92 937 862210', [['single', 1, 8], ['double', 2, 8], ['triple', 3, 4]]],
            'Swat Youth Hostel' => ['Fizagat, Mingora, Swat', '+92 946 720031', [['single', 1, 6], ['double', 2, 6], ['dormitory', 8, 1]]],
            'Abbottabad Youth Hostel' => ['Jinnahabad, Abbottabad', '+92 992 385510', [['single', 1, 6], ['double', 2, 4]]],
            'Kohat Youth Hostel' => ['Hangu Road, Kohat', '+92 922 512040', [['single', 1, 4], ['double', 2, 4]]],
        ];
        foreach ($layout as $name => [$address, $phone, $types]) {
            $hostel = $h($name);
            $hostel->update(['address' => $address, 'phone' => $phone, 'email' => strtolower(strtok($name, ' ')) . '@youthhostels.kp.gov.pk']);
            $floor = 1;
            foreach ($types as [$type, $cap, $count]) {
                for ($i = 1; $i <= $count; $i++) {
                    $hostel->rooms()->firstOrCreate(['room_number' => $type === 'dormitory' ? "D{$i}" : $floor . str_pad($i, 2, '0', STR_PAD_LEFT)], ['room_type' => $type, 'capacity' => $cap, 'status' => 'available']);
                }
                $floor++;
            }
        }
        $h('Peshawar Youth Hostel')->rooms()->where('room_number', '112')->update(['status' => 'maintenance']);

        $usman = User::updateOrCreate(['email' => 'usman.khan@dya.test'], ['name' => 'Usman Khan', 'password' => Hash::make(DemoSeeder::DEMO_PASSWORD), 'status' => 'active',
            'must_change_password' => false, 'password_changed_at' => now(), 'designation' => 'Hostel Warden']);
        $usman->roles()->sync([Role::where('slug', 'hostel_staff')->value('id')]);
        ScopeAssignment::save($usman, new ScopeCoverage(false, ['hostel' => [$h('Peshawar Youth Hostel')->id, $mardan->id]]));
        $faisal = User::where('email', 'faisal.rehman@dya.test')->first() ?? $admin;
        $staffFor = fn ($name) => in_array($name, ['Peshawar Youth Hostel', 'Mardan Youth Hostel'], true) ? $usman : ($name === 'Swat Youth Hostel' ? $faisal : $admin);

        // ---- Bookings around today -------------------------------------------------
        $today = Carbon::today();
        $districts = District::pluck('id', 'name');
        foreach ([
            // hostel, guest, org, purpose, check-in offset, nights, guests, rooms, stage
            ['Peshawar Youth Hostel', 'Ahmed Khan', 'Youth Development Organization', 'training', -14, 3, 6, 3, 'checked_out'],
            ['Mardan Youth Hostel', 'Ayesha Tariq', 'Women Empowerment Society', 'seminar', -2, 4, 5, 2, 'checked_in'],
            ['Peshawar Youth Hostel', 'Bilal Hussain', 'Pakistan Youth Forum', 'sports', -1, 3, 16, 2, 'checked_in'],
            ['Swat Youth Hostel', 'Sana Ullah', 'Bright Future Trust', 'education', 0, 2, 4, 2, 'approved'],
            ['Peshawar Youth Hostel', 'Zainab Ali', 'Provincial Debate Society', 'cultural', 12, 2, 10, 4, 'approved'],
            ['Mardan Youth Hostel', 'Usman Ghani', 'Sports Club Mardan', 'sports', 20, 3, 12, 5, 'pending'],
            ['Abbottabad Youth Hostel', 'Fatima Noor', 'Health Awareness Society', 'official', 25, 2, 3, 2, 'pending'],
            ['Peshawar Youth Hostel', 'Abdul Rehman', 'Education Forum', 'training', 30, 5, 8, 4, 'returned'],
            ['Kohat Youth Hostel', 'Waqas Ahmad', 'Kohat Youth Council', 'official', 8, 1, 2, 1, 'rejected'],
            ['Swat Youth Hostel', 'Hira Shah', 'Community Dev Org', 'cultural', 15, 2, 6, 3, 'cancelled'],
            ['Peshawar Youth Hostel', 'Kamran Afridi', 'Inter-College Sports Board', 'sports', 40, 3, 20, 3, 'draft'],
        ] as [$hostelName, $guest, $org, $purpose, $offset, $nights, $guests, $rooms, $stage]) {
            $hostel = $h($hostelName);
            $staff = $staffFor($hostelName);
            $checkIn = $today->copy()->addDays($offset);
            $this->at($checkIn->copy()->subDays(10));
            $b = new HostelBooking([
                'hostel_id' => $hostel->id, 'district_id' => $districts[$hostel->district->name], 'request_date' => now(), 'requested_by_email' => strtolower(str_replace(' ', '.', $guest)) . '@example.org',
                'guest_name' => $guest, 'cnic_passport' => '17301-' . random_int(1000000, 9999999) . '-' . random_int(1, 9), 'mobile' => '+92 3' . random_int(10, 49) . ' ' . random_int(1000000, 9999999),
                'organization' => $org, 'referred_by' => 'Deputy Director (Youth)', 'referrer_designation' => 'Directorate of Youth Affairs', 'purpose' => $purpose,
                'check_in' => $checkIn, 'check_out' => $checkIn->copy()->addDays($nights), 'guest_count' => $guests, 'room_count' => $rooms,
            ]);
            $b->status = 'draft';
            $b->created_by = $b->updated_by = $b->submitted_by = $staff->id;
            ReferenceNumber::saveWith($b, 'booking_no', 'HB');
            $b->recordAction('created', null, $staff);
            if ($stage === 'draft') {
                continue;
            }
            $this->at($checkIn->copy()->subDays(9));
            $b->submitted_at = now();
            $b->recordAction('submitted', 'pending', $staff);
            if ($stage === 'pending') {
                continue;
            }
            $this->at($checkIn->copy()->subDays(7));
            $b->reviewed_by = $this->approver->id;
            $b->decided_at = now();
            if (in_array($stage, ['returned', 'rejected'], true)) {
                $b->recordAction($stage, $stage, $this->approver, $stage === 'returned' ? 'Please attach the referral letter on official letterhead.' : 'Purpose is not eligible under the hostel booking policy.');
                continue;
            }
            $free = HostelAvailability::freeRooms($hostel->id, $b->check_in, $b->check_out, $b->id);
            $pick = HostelAvailability::suggest($free, $rooms, $guests);
            $b->rooms()->sync($pick->mapWithKeys(fn ($r) => [$r->id => ['assigned_by' => $this->approver->id, 'assigned_at' => now()]])->all());
            $b->recordAction('approved', 'approved', $this->approver, 'Approved; rooms assigned.');
            if ($stage === 'cancelled') {
                $this->at($checkIn->copy()->subDays(3));
                $b->recordAction('cancelled', 'cancelled', $staff, 'Event postponed by the organization.');
                continue;
            }
            if (in_array($stage, ['checked_in', 'checked_out'], true)) {
                $this->at($checkIn->copy()->setTime(14, 30));
                $b->forceFill(['actual_check_in_at' => now(), 'arrived_guests' => $guests, 'occupied_rooms' => $rooms]);
                $b->recordAction('checked_in', 'checked_in', $staff, "{$guests} guest(s), {$rooms} room(s)");
            }
            if ($stage === 'checked_out') {
                $this->at($b->check_out->copy()->setTime(11, 0));
                $b->forceFill(['actual_check_out_at' => now()]);
                $b->recordAction('checked_out', 'checked_out', $staff);
            }
        }

        // ---- Finance: central allocation, hostel budgets, expenses -----------------
        $this->at('2026-07-16');
        $central = new BudgetAllocation(['financial_year_id' => $fy->id, 'office_id' => Office::where('name', 'Directorate Headquarters')->value('id'),
            'budget_head' => config('hostel.budget_head'), 'allocation_date' => '2026-07-16', 'planned_amount' => 6000000, 'remarks' => 'Funds for youth hostel operations (demo).']);
        $central->status = 'draft';
        $central->created_by = $central->updated_by = $central->submitted_by = $this->finance->id;
        ReferenceNumber::saveWith($central, 'reference_no', 'AL');
        $central->recordAction('created', null, $this->finance);
        $central->recordAction('submitted', 'submitted', $this->finance);
        $this->at('2026-07-18');
        $central->approved_amount = 6000000;
        $central->recordAction('approved', 'approved', $this->approver);

        $budgets = [];
        foreach ([['Peshawar Youth Hostel', 1500000, true], ['Mardan Youth Hostel', 1200000, true], ['Swat Youth Hostel', 1000000, true], ['Abbottabad Youth Hostel', 800000, true], ['Kohat Youth Hostel', 600000, false]] as [$name, $amount, $approve]) {
            $this->at('2026-07-20');
            $b = new HostelBudget(['hostel_id' => $h($name)->id, 'financial_year_id' => $fy->id, 'original_amount' => $amount, 'issue_date' => '2026-07-20', 'authorization_reference' => 'DYA/HST/2026/' . random_int(10, 99)]);
            $b->status = 'draft';
            $b->created_by = $b->updated_by = $b->submitted_by = $this->finance->id;
            ReferenceNumber::saveWith($b, 'reference_no', 'HBUD');
            $b->recordAction('created', null, $this->finance);
            $b->recordAction('submitted', 'submitted', $this->finance);
            if ($approve) {
                $this->at('2026-07-22');
                $c = new BudgetCommitment(['description' => "{$name} — hostel budget {$fy->label}", 'amount' => $amount, 'committed_on' => now(), 'authority_reference' => $b->authorization_reference]);
                $c->allocation_id = $central->id;
                $c->financial_year_id = $fy->id;
                $c->office_id = $central->office_id;
                $c->status = 'open';
                $c->created_by = $c->updated_by = $c->submitted_by = $this->approver->id;
                ReferenceNumber::saveWith($c, 'reference_no', 'CM');
                $c->recordAction('created', null, $this->approver, $b->authorization_reference);
                $b->commitment_id = $c->id;
                $b->recordAction('approved', 'approved', $this->approver, 'Hostel budget approved.');
            }
            $budgets[$name] = $b;
        }

        $this->at('2026-09-10');
        $rev = new HostelBudgetRevision(['direction' => 'increase', 'amount' => 200000, 'reason' => 'Roof repair after monsoon damage (demo).']);
        $rev->hostel_budget_id = $budgets['Peshawar Youth Hostel']->id;
        $rev->status = 'pending';
        $rev->created_by = $rev->updated_by = $rev->submitted_by = $this->finance->id;
        ReferenceNumber::saveWith($rev, 'reference_no', 'HREV');
        $rev->recordAction('created', null, $this->finance, $rev->reason);
        $this->at('2026-09-12');
        $rev->recordAction('approved', 'approved', $this->approver);
        $cm = $budgets['Peshawar Youth Hostel']->commitment;
        $cm->amount = (float) $cm->amount + 200000;
        $cm->recordAction('updated', null, $this->approver, "Adjusted by {$rev->reference_no}: + PKR 200,000");

        foreach ([
            ['Peshawar Youth Hostel', '2026-08-04', 'utilities', 'Electricity bill — July', 'PESCO', 'EB-77120', 165000, 'paid'],
            ['Peshawar Youth Hostel', '2026-09-15', 'maintenance', 'Roof repair after monsoon', 'Khyber Builders', 'KB-2201', 380000, 'partially_paid'],
            ['Mardan Youth Hostel', '2026-08-20', 'cleaning', 'Monthly cleaning contract — August', 'CleanPro Services', 'CP-0820', 45000, 'paid'],
            ['Mardan Youth Hostel', '2026-10-05', 'supplies', 'Bed linen and towels', 'Mardan Textiles', 'MT-5512', 120000, 'approved'],
            ['Swat Youth Hostel', '2026-09-02', 'utilities', 'Gas and water bills — August', 'SNGPL', 'GW-3301', 38000, 'paid'],
            ['Swat Youth Hostel', '2026-10-12', 'furniture', 'Study tables for common room', 'Swat Furniture House', 'SFH-118', 95000, 'pending'],
            ['Abbottabad Youth Hostel', '2026-09-20', 'security', 'Security guard services — September', 'Shield Security', 'SS-0920', 60000, 'paid'],
            ['Abbottabad Youth Hostel', '2026-10-08', 'other', 'Staff personal travel', 'Daewoo Express', 'DW-881', 7500, 'rejected'],
        ] as [$name, $date, $cat, $desc, $supplier, $invoice, $amount, $stage]) {
            $this->expense($budgets[$name], $staffFor($name), $date, $cat, $desc, $supplier, $invoice, $amount, $stage);
        }

        Carbon::setTestNow();
    }

    protected function expense(HostelBudget $budget, User $by, string $date, string $cat, string $desc, string $supplier, string $invoice, float $amount, string $stage): void
    {
        $this->at($date);
        $e = new HostelExpense(['expense_date' => $date, 'category' => $cat, 'description' => $desc, 'supplier_name' => $supplier, 'invoice_number' => $invoice, 'amount' => $amount]);
        $e->hostel_id = $budget->hostel_id;
        $e->hostel_budget_id = $budget->id;
        $e->status = 'pending';
        $e->created_by = $e->updated_by = $e->submitted_by = $by->id;
        ReferenceNumber::saveWith($e, 'expense_no', 'HEXP');
        $e->recordAction('created', null, $by);
        if ($stage === 'pending') {
            return;
        }
        $this->at(Carbon::parse($date)->addDays(2));
        $e->reviewed_by = $this->approver->id;
        $e->decided_at = now();
        $e->recordAction($stage === 'rejected' ? 'rejected' : 'approved', $stage === 'rejected' ? 'rejected' : 'approved', $this->approver, $stage === 'rejected' ? 'Not a hostel operating expense.' : null);
        if (in_array($stage, ['rejected', 'approved'], true)) {
            return;
        }

        $when = Carbon::parse($date)->addDays(6);
        $this->at($when);
        $pay = $stage === 'partially_paid' ? round($amount * 0.5) : $amount;
        $commitment = $budget->commitment;
        $ref = 'CHQ-' . random_int(100000, 999999);
        $t = new FinancialTransaction(['txn_date' => $when, 'category' => config("hostel.central_categories.{$cat}", 'other'),
            'description' => "{$budget->hostel->name} — {$e->expense_no}: {$desc}", 'payee' => $supplier, 'amount' => $pay, 'payment_reference' => $ref]);
        $t->allocation_id = $commitment->allocation_id;
        $t->financial_year_id = $commitment->financial_year_id;
        $t->office_id = $commitment->office_id;
        $t->commitment_id = $commitment->id;
        $t->status = 'pending';
        $t->created_by = $t->updated_by = $t->submitted_by = $this->finance->id;
        ReferenceNumber::saveWith($t, 'reference_no', 'EX');
        $t->recordAction('created', null, $this->finance);
        $t->recordAction('verified', 'verified', $this->approver, "Approved as hostel expense {$e->expense_no} by {$this->approver->name}.");

        $p = new HostelPayment(['amount' => $pay, 'payment_date' => $when, 'method' => 'cheque', 'transaction_reference' => $ref]);
        $p->hostel_expense_id = $e->id;
        $p->hostel_id = $e->hostel_id;
        $p->financial_transaction_id = $t->id;
        $p->recorded_by = $this->finance->id;
        ReferenceNumber::saveWith($p, 'reference_no', 'HPAY');
        $to = $pay >= $amount ? 'paid' : 'partially_paid';
        $e->recordAction($to, $to, $this->finance, "{$p->reference_no}: PKR " . number_format($pay, 2) . " ({$ref})");
    }

    protected function at($date): void
    {
        Carbon::setTestNow(Carbon::parse($date)->setTime(random_int(9, 16), random_int(0, 59)));
    }
}
