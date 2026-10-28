<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityProposal;
use Illuminate\Http\Request;

/**
 * The signed-in user's work queue: everything in the activity workflow that
 * is waiting for an action they are allowed to take.
 */
class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ActivityProposal::class);
        $user = $request->user();
        $proposals = fn () => ActivityProposal::visibleTo($user)->with('district', 'assignee')->orderBy('updated_at');
        $queues = [];

        if ($user->hasPermission('activity.review')) {
            $queues['review'] = ['Assigned to me for review', 'bi-person-check', 'Proposals assigned to you. Recommend, return or reject each one.',
                $proposals()->where('status', 'under_review')->where('assigned_to', $user->id)->get()];
        }
        if ($user->hasPermission('activity.approve')) {
            $queues['decision'] = ['Awaiting my decision', 'bi-patch-check', 'Recommended proposals waiting for the approving authority.',
                $proposals()->where('status', 'recommended')
                    ->where(fn ($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', '!=', $user->id))
                    ->where(fn ($q) => $q->whereNull('submitted_by')->orWhere('submitted_by', '!=', $user->id))->get()];
        }
        if ($user->hasPermission('activity.submit')) {
            $queues['intake'] = ['New & incomplete proposals', 'bi-inbox', 'Check completeness and assign a reviewing officer.',
                $proposals()->whereIn('status', ['received', 'incomplete'])->get()];
            $queues['returned'] = ['Returned for correction', 'bi-arrow-return-left', 'Update these proposals and resubmit them to the officer.',
                $proposals()->where('status', 'returned')->get()];
        }
        if ($user->hasPermission('activity.edit')) {
            $queues['drafts'] = ['My drafts', 'bi-pencil-square', 'Proposals you saved but have not registered yet.',
                $proposals()->where('status', 'draft')->where('created_by', $user->id)->get()];
        }

        $activities = fn () => Activity::visibleTo($user)->with('proposal', 'district')->orderBy('updated_at');
        $activityQueues = [];
        if ($user->hasPermission('activity.review')) {
            $activityQueues['verify'] = ['Completion reports to verify', 'bi-clipboard-check', 'Check the evidence and finalize, or return for correction.',
                $activities()->where('status', 'completion_submitted')
                    ->where(fn ($q) => $q->whereNull('completion_submitted_by')->orWhere('completion_submitted_by', '!=', $user->id))->get()];
        }
        if ($user->hasPermission('activity.edit')) {
            $activityQueues['execute'] = ['Activities in implementation', 'bi-calendar-event', 'Record participants, expenses and evidence, then submit completion.',
                $activities()->whereIn('status', ['in_progress', 'returned'])->get()];
        }

        return view('activity.assignments.index', ['queues' => $queues, 'activityQueues' => $activityQueues]);
    }
}
