<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Implementation of an approved proposal:
 *   record details, participants, expenses, evidence — activity.edit while in progress / returned
 *   submit completion report — activity.submit
 *   verify and finalize — activity.review, never the person who submitted the completion report
 */
class ActivityPolicy extends ScopedRecordPolicy
{
    protected string $module = 'activity';

    public function update(User $user, Model $activity): bool
    {
        return $activity->isEditable() && $this->allowed($user, $activity, 'edit');
    }

    public function submitCompletion(User $user, Model $activity): bool
    {
        return $activity->isEditable() && $this->allowed($user, $activity, 'submit');
    }

    public function verify(User $user, Model $activity): Response
    {
        if ($activity->status !== 'completion_submitted' || ! $this->allowed($user, $activity, 'review')) {
            return Response::deny();
        }
        if ($this->submitterId($activity) === $user->id) {
            return Response::deny('You cannot verify a completion report you submitted.');
        }

        return Response::allow();
    }

    protected function submitterId(Model $record): ?int
    {
        return $record->completion_submitted_by;
    }
}
