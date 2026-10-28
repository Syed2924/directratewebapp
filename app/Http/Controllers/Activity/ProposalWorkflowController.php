<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Http\Controllers\Budget\CommitmentController;
use App\Models\BudgetAllocation;
use App\Models\FinancialYear;
use App\Support\ActivityOfficers;
use App\Support\BudgetSummary;
use Illuminate\Validation\ValidationException;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Moves a proposal through its workflow. Each step is authorized by
 * ActivityProposalPolicy, recorded in the proposal history and audited.
 */
class ProposalWorkflowController extends Controller
{
    /** "Information complete?" — No: record what is missing. Yes: assign for internal review. */
    public function completeness(Request $request, ActivityProposal $proposal)
    {
        $this->authorize('checkCompleteness', $proposal);
        $user = $request->user();
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['complete', 'incomplete'])],
            'missing_information' => ['required_if:outcome,incomplete', 'nullable', 'string', 'max:2000'],
            'assigned_to' => ['required_if:outcome,complete', 'nullable', Rule::in($this->officerIds($proposal))],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['assigned_to.required_if' => 'Select the officer who will review this proposal.', 'assigned_to.in' => 'The selected officer cannot review proposals for this district.']);

        DB::transaction(function () use ($proposal, $user, $data) {
            if ($data['outcome'] === 'incomplete') {
                $proposal->missing_information = $data['missing_information'];
                $proposal->transition('info_missing', 'incomplete', $user, ['remarks' => $data['missing_information']]);

                return;
            }

            if ($proposal->status === 'incomplete') {
                $proposal->transition('info_completed', 'received', $user, ['remarks' => 'Requested clarification / documents received.']);
            }
            $proposal->missing_information = null;
            $proposal->assigned_to = (int) $data['assigned_to'];
            $proposal->assigned_at = now();
            $proposal->transition('assigned', 'under_review', $user, ['assigned_to' => $proposal->assigned_to, 'remarks' => $data['remarks'] ?? null]);
        });

        $this->audit($data['outcome'] === 'incomplete' ? 'activity.proposal_incomplete' : 'activity.proposal_assigned', $proposal, $data['missing_information'] ?? $data['remarks'] ?? null);

        return redirect()->route('activity.proposals.show', $proposal)->with('status', $data['outcome'] === 'incomplete'
            ? 'Missing information recorded. Mark it complete once the applicant provides it.'
            : 'Proposal assigned to ' . $proposal->assignee->name . ' for internal review.');
    }

    /** Hand an in-review or returned proposal to a different officer. */
    public function assign(Request $request, ActivityProposal $proposal)
    {
        $this->authorize('assign', $proposal);
        $data = $request->validate([
            'assigned_to' => ['required', Rule::in($this->officerIds($proposal))],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ], ['assigned_to.in' => 'The selected officer cannot review proposals for this district.']);

        $first = $proposal->assigned_to === null;
        DB::transaction(function () use ($proposal, $request, $data, $first) {
            $proposal->assigned_to = (int) $data['assigned_to'];
            $proposal->assigned_at = now();
            $proposal->transition($first ? 'assigned' : 'reassigned', $proposal->status === 'received' ? 'under_review' : null, $request->user(), [
                'assigned_to' => $proposal->assigned_to, 'remarks' => $data['remarks'] ?? null,
            ]);
        });
        $this->audit('activity.proposal_assigned', $proposal, $data['remarks'] ?? null);

        return redirect()->route('activity.proposals.show', $proposal)->with('status', 'Proposal assigned to ' . $proposal->assignee->name . '.');
    }

    /** Reviewing officer: recommend, return for correction, or reject. */
    public function review(Request $request, ActivityProposal $proposal)
    {
        $this->authorize('officerReview', $proposal);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['recommend', 'return', 'reject'])],
            'remarks' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        [$action, $to] = match ($data['decision']) {
            'recommend' => ['recommended', 'recommended'],
            'return' => ['review_returned', 'returned'],
            'reject' => ['review_rejected', 'rejected'],
        };
        DB::transaction(function () use ($proposal, $request, $data, $action, $to) {
            if ($to === 'rejected') {
                $proposal->decided_on = now();
            }
            $proposal->transition($action, $to, $request->user(), ['remarks' => $data['remarks']]);
        });
        $this->audit("activity.proposal_{$action}", $proposal, $data['remarks']);

        return redirect()->route('activity.proposals.show', $proposal)->with('status', match ($data['decision']) {
            'recommend' => 'Proposal recommended and forwarded to the approving authority.',
            'return' => 'Proposal returned for correction.',
            'reject' => 'Proposal rejected.',
        });
    }

    /**
     * Authorized approver: approve, return, or reject.
     * Approval needs authorized funding: an approved allocation in the proposal's
     * district with enough left. The estimated budget is committed against it
     * ("Record Authorized Budget Commitment") and the activity record is created.
     * No funding available → return the proposal (escalate or revise the plan).
     */
    public function decide(Request $request, ActivityProposal $proposal)
    {
        $this->authorize('decide', $proposal);
        $needsFunding = (float) $proposal->estimated_budget > 0;
        $funding = $needsFunding ? BudgetSummary::fundingOptions($proposal->district_id, (float) $proposal->estimated_budget)->pluck('id')->all() : [];
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return', 'reject'])],
            'decision_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $proposal->received_on->toDateString()],
            'remarks' => ['required', 'string', 'min:3', 'max:2000'],
            'allocation_id' => [$needsFunding ? 'required_if:decision,approve' : 'nullable', 'nullable', Rule::in($funding)],
        ], [
            'allocation_id.required_if' => 'Select the allocation that funds this activity. If none has enough available, return the proposal or ask Finance to allocate or transfer funds.',
            'allocation_id.in' => 'That allocation no longer has enough funds available.',
        ]);
        $user = $request->user();

        DB::transaction(function () use ($proposal, $user, $data, $needsFunding) {
            if ($data['decision'] === 'return') {
                $proposal->transition('decision_returned', 'returned', $user, ['remarks' => $data['remarks'], 'action_date' => $data['decision_date']]);

                return;
            }

            $proposal->decided_on = $data['decision_date'];
            if ($data['decision'] === 'reject') {
                $proposal->transition('decision_rejected', 'rejected', $user, ['remarks' => $data['remarks'], 'action_date' => $data['decision_date']]);

                return;
            }

            if ($needsFunding) {
                $allocation = BudgetAllocation::findOrFail($data['allocation_id']);
                FinancialYear::whereKey($allocation->financial_year_id)->lockForUpdate()->first();
                if ($allocation->loadLedger()->available() < (float) $proposal->estimated_budget) {
                    throw ValidationException::withMessages(['allocation_id' => 'That allocation no longer has enough funds available.']);
                }
                CommitmentController::commit($allocation, [
                    'description' => "Activity {$proposal->tracking_no}: {$proposal->title}",
                    'amount' => $proposal->estimated_budget,
                    'committed_on' => $data['decision_date'],
                    'authority_reference' => "Approval of {$proposal->tracking_no}",
                ], $user, $proposal->id);
            }

            $proposal->transition('approved', 'approved', $user, ['remarks' => $data['remarks'], 'action_date' => $data['decision_date']]);
            $activity = new Activity(['location' => $proposal->venue]);
            $activity->proposal_id = $proposal->id;
            $activity->district_id = $proposal->district_id;
            $activity->status = 'in_progress';
            $activity->created_by = $activity->updated_by = $user->id;
            $activity->save();
            $proposal->transition('activity_created', null, $user, ['activity_id' => $activity->id]);
        });

        $event = ['approve' => 'approved', 'return' => 'decision_returned', 'reject' => 'decision_rejected'][$data['decision']];
        $this->audit("activity.proposal_{$event}", $proposal, $data['remarks']);

        if ($data['decision'] === 'approve') {
            return redirect()->route('activity.activities.show', $proposal->activity()->first())
                ->with('status', "Proposal {$proposal->tracking_no} approved. The activity record is ready for implementation.");
        }

        return redirect()->route('activity.proposals.show', $proposal)->with('status', $data['decision'] === 'return' ? 'Proposal returned for correction.' : 'Proposal rejected.');
    }

    /** Staff: send a corrected proposal back to the reviewing officer. */
    public function resubmit(Request $request, ActivityProposal $proposal)
    {
        $this->authorize('resubmit', $proposal);
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(fn () => $proposal->transition('resubmitted', 'under_review', $request->user(), ['assigned_to' => $proposal->assigned_to, 'remarks' => $data['remarks'] ?? null]));
        $this->audit('activity.proposal_resubmitted', $proposal, $data['remarks'] ?? null);

        return redirect()->route('activity.proposals.show', $proposal)->with('status', 'Corrected proposal sent back to ' . $proposal->assignee->name . ' for review.');
    }

    protected function officerIds(ActivityProposal $proposal): array
    {
        return ActivityOfficers::reviewersFor($proposal)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    protected function audit(string $event, ActivityProposal $proposal, ?string $remarks): void
    {
        Audit::log($event, $proposal, [
            'entity_label' => $proposal->tracking_no,
            'description' => "{$proposal->tracking_no}: status {$proposal->statusLabel()}" . ($proposal->assigned_to ? ', officer ' . optional($proposal->assignee)->name : ''),
            'reason' => $remarks,
        ]);
    }
}
