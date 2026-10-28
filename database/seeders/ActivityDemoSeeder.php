<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\BudgetCommitment;
use App\Models\FinancialTransaction;
use App\Support\BudgetSummary;
use App\Support\ReferenceNumber;
use App\Models\ActivityProposal;
use App\Models\District;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * LOCAL DEMO DATA ONLY — sample proposals at every workflow stage, following
 * the same separation of duties the application enforces:
 *   registered by Ahmed Khan (Peshawar) or Syed Awais, reviewed by Sana Ullah / Asma Riaz,
 *   decided by Asma Riaz, completion verified by Asma Riaz.
 * Skipped when any proposal already exists.
 */
class ActivityDemoSeeder extends Seeder
{
    protected array $users = [];

    public function run()
    {
        if (ActivityProposal::exists()) {
            return;
        }

        foreach (['ali' => 'alsyedk123@gmail.com', 'ahmed' => 'ahmed.khan@dya.test', 'sana' => 'sana.ullah@dya.test', 'asma' => 'asma.riaz@dya.test'] as $k => $email) {
            $this->users[$k] = User::where('email', $email)->firstOrFail();
        }
        $d = District::pluck('id', 'name');

        // [applicant, title, category, district, registered by, received, male, female, budget, final stage]
        $rows = [
            ['Youth Welfare Organization', 'Youth Seminar', 'seminar', 'Mardan', 'ali', '2026-10-01', 80, 40, 150000, 'under_review'],
            ['Pakistan Youth Forum', 'Inter-College Sports Gala', 'sports', 'Peshawar', 'ahmed', '2026-09-08', 150, 50, 450000, 'in_progress'],
            ['Bright Future Trust', 'IT Workshop for Youth', 'workshop', 'Swat', 'ali', '2026-10-15', 30, 30, 120000, 'received'],
            ['Green Youth Organization', 'Clean & Green Drive', 'community', 'Charsadda', 'ali', '2026-09-20', 60, 20, 80000, 'rejected'],
            ['Women Empowerment Society', 'Leadership Training', 'training', 'Mardan', 'ali', '2026-10-06', 0, 45, 200000, 'incomplete'],
            ['Youth United', 'Career Guidance Session', 'career', 'Peshawar', 'ahmed', '2026-09-29', 70, 70, 95000, 'recommended'],
            ['Sports Club Peshawar', 'Cricket Tournament', 'sports', 'Peshawar', 'ahmed', '2026-08-18', 120, 0, 300000, 'completed'],
            ['Education Forum', 'Digital Skills Bootcamp', 'training', 'Peshawar', 'ahmed', '2026-08-25', 40, 35, 175000, 'completion_submitted'],
            ['Health Awareness Society', 'Youth Health Camp', 'health', 'Peshawar', 'ahmed', '2026-10-02', 90, 90, 140000, 'returned'],
            ['Community Development Org', 'Youth Leadership Dialogue', 'seminar', 'Abbottabad', 'ali', '2026-10-17', 50, 50, 110000, 'draft'],
        ];

        foreach ($rows as [$applicant, $title, $cat, $district, $by, $received, $m, $f, $budget, $stage]) {
            $this->at($received);
            $p = new ActivityProposal([
                'received_on' => $received, 'applicant_name' => $applicant, 'contact_person' => 'Focal Person',
                'phone' => '+92 300 ' . random_int(1000000, 9999999), 'submission_method' => 'physical',
                'title' => $title, 'category' => $cat, 'district_id' => $d[$district], 'venue' => "{$district} Youth Center",
                'description' => "{$title} for young people of {$district} district.",
                'proposed_start' => Carbon::parse($received)->addDays(21), 'proposed_end' => Carbon::parse($received)->addDays(22),
                'participants_male' => $m, 'participants_female' => $f, 'estimated_budget' => $budget,
            ]);
            $p->status = $stage === 'draft' ? 'draft' : 'received';
            $p->submitted_by = $p->created_by = $p->updated_by = $this->users[$by]->id;
            $p->saveWithTrackingNo();
            $p->actions()->create(['action' => $stage === 'draft' ? 'drafted' : 'registered', 'to_status' => $p->status, 'user_id' => $p->submitted_by, 'action_date' => $received]);

            $this->advance($p, $stage, Carbon::parse($received), $by);
        }

        Carbon::setTestNow();
    }

    protected function advance(ActivityProposal $p, string $stage, Carbon $day, string $by): void
    {
        if (in_array($stage, ['draft', 'received'], true)) {
            return;
        }
        $clerk = $this->users[$by];
        $officer = in_array($p->district->name, ['Peshawar', 'Charsadda'], true) ? $this->users['sana'] : $this->users['asma'];
        $approver = $this->users['asma'];

        if ($stage === 'incomplete') {
            $this->at($day->addDay());
            $p->missing_information = 'Budget breakdown and NOC from district administration are missing.';
            $p->transition('info_missing', 'incomplete', $clerk, ['remarks' => $p->missing_information]);

            return;
        }

        $this->at($day->addDay());
        $p->assigned_to = $officer->id;
        $p->assigned_at = now();
        $p->transition('assigned', 'under_review', $clerk, ['assigned_to' => $officer->id]);
        if ($stage === 'under_review') {
            return;
        }

        $this->at($day->addDays(2));
        if ($stage === 'rejected') {
            $p->decided_on = now();
            $p->transition('review_rejected', 'rejected', $officer, ['remarks' => 'Activity duplicates an ongoing district programme; not eligible for funding this year.']);

            return;
        }
        if ($stage === 'returned') {
            $p->transition('review_returned', 'returned', $officer, ['remarks' => 'Please attach the list of medical volunteers and a revised budget.']);

            return;
        }
        $p->transition('recommended', 'recommended', $officer, ['remarks' => 'The proposal is well planned and aligns with department objectives.']);
        if ($stage === 'recommended') {
            return;
        }

        $this->at($day->addDays(2));
        // Authorized funding: commit the estimated budget against an allocation in the district.
        $allocation = BudgetSummary::fundingOptions($p->district_id, (float) $p->estimated_budget, $p->budgetHead())->first();
        $commitment = null;
        if ($allocation) {
            $commitment = new BudgetCommitment(['description' => "Activity {$p->tracking_no}: {$p->title}", 'amount' => $p->estimated_budget,
                'committed_on' => now(), 'authority_reference' => "Approval of {$p->tracking_no}"]);
            $commitment->allocation_id = $allocation->id;
            $commitment->financial_year_id = $allocation->financial_year_id;
            $commitment->office_id = $allocation->office_id;
            $commitment->proposal_id = $p->id;
            $commitment->status = 'open';
            $commitment->created_by = $commitment->updated_by = $commitment->submitted_by = $approver->id;
            ReferenceNumber::saveWith($commitment, 'reference_no', 'CM');
            $commitment->recordAction('created', null, $approver, "Approval of {$p->tracking_no}");
        }
        $p->decided_on = now();
        $p->transition('approved', 'approved', $approver, ['remarks' => 'Approved for implementation. Please proceed with next steps.']);
        $a = new Activity(['location' => $p->venue]);
        $a->proposal_id = $p->id;
        $a->district_id = $p->district_id;
        $a->status = 'in_progress';
        $a->created_by = $a->updated_by = $approver->id;
        $a->save();
        $p->transition('activity_created', null, $approver, ['activity_id' => $a->id]);
        if ($stage === 'in_progress') {
            return;
        }

        $start = $p->proposed_start->copy();
        $a->fill([
            'actual_start' => $start, 'actual_end' => $start->copy()->addDay(),
            'actual_male' => (int) round($p->participants_male * 0.9), 'actual_female' => (int) round($p->participants_female * 1.1),
            'summary' => "The {$p->title} was held as planned with active participation from local youth.",
            'remarks' => 'Activity completed successfully.',
        ])->save();
        // Expenses are recorded once, in the expense register, against the activity's commitment.
        $expenses = [];
        foreach ($commitment ? [['venue', 'Venue and arrangements', 0.3], ['refreshments', 'Refreshments for participants', 0.35], ['prizes', 'Certificates and prizes', 0.2]] : [] as [$cat, $desc, $share]) {
            $this->at($start);
            $t = new FinancialTransaction(['txn_date' => $start, 'category' => $cat, 'description' => $desc, 'amount' => round($p->estimated_budget * $share)]);
            $t->allocation_id = $commitment->allocation_id;
            $t->financial_year_id = $commitment->financial_year_id;
            $t->office_id = $commitment->office_id;
            $t->commitment_id = $commitment->id;
            $t->proposal_id = $p->id;
            $t->status = 'pending';
            $t->created_by = $t->updated_by = $t->submitted_by = $clerk->id;
            ReferenceNumber::saveWith($t, 'reference_no', 'EX');
            $t->recordAction('created', null, $clerk);
            $expenses[] = $t;
        }

        $this->at($start->copy()->addDays(3));
        $a->status = 'completion_submitted';
        $a->completion_submitted_by = $clerk->id;
        $a->completion_submitted_at = now();
        $a->save();
        $p->transition('completion_submitted', null, $clerk, ['activity_id' => $a->id]);
        if ($stage === 'completion_submitted') {
            return;
        }

        $this->at($start->copy()->addDays(5));
        $a->status = 'completed';
        $a->verified_by = $approver->id;
        $a->verified_at = now();
        $a->save();
        $p->transition('completed', null, $approver, ['activity_id' => $a->id, 'remarks' => 'Evidence and expenses verified.']);
        foreach ($expenses as $t) {
            $t->recordAction('verified', 'verified', $approver);
        }
        $commitment?->close($approver, "Activity {$p->tracking_no} completed and verified.");
    }

    protected function at($date): void
    {
        Carbon::setTestNow(Carbon::parse($date)->setTime(random_int(9, 16), random_int(0, 59)));
    }
}
