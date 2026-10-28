<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Proposal workflow (see config/activity.php for statuses):
 *   create/edit  — activity.create / activity.edit, while the proposal is editable
 *   completeness check, assignment, resubmission — activity.submit
 *   officer review (recommend / return / reject) — activity.review, assigned officer only
 *   final decision — activity.approve; never the registering staff or the reviewing officer
 */
class ActivityProposalPolicy extends ScopedRecordPolicy
{
    protected string $module = 'activity';

    public function update(User $user, Model $proposal): bool
    {
        return $proposal->isEditable() && $this->allowed($user, $proposal, 'edit');
    }

    public function register(User $user, Model $proposal): bool
    {
        return $proposal->status === 'draft' && $this->allowed($user, $proposal, 'create');
    }

    public function checkCompleteness(User $user, Model $proposal): bool
    {
        return in_array($proposal->status, ['received', 'incomplete'], true) && $this->allowed($user, $proposal, 'submit');
    }

    public function assign(User $user, Model $proposal): bool
    {
        return in_array($proposal->status, ['received', 'under_review', 'returned'], true) && $this->allowed($user, $proposal, 'submit');
    }

    public function resubmit(User $user, Model $proposal): bool
    {
        return $proposal->status === 'returned' && $proposal->assigned_to && $this->allowed($user, $proposal, 'submit');
    }

    public function officerReview(User $user, Model $proposal): bool
    {
        return $proposal->status === 'under_review'
            && $proposal->assigned_to === $user->id
            && $this->allowed($user, $proposal, 'review');
    }

    public function decide(User $user, Model $proposal): Response
    {
        if ($proposal->status !== 'recommended') {
            return Response::deny('Only recommended proposals can be decided.');
        }
        if ($proposal->assigned_to === $user->id) {
            return Response::deny('The reviewing officer cannot also make the final decision.');
        }

        return $this->approve($user, $proposal);
    }
}
