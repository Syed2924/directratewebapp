<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Center expenses (and their payments). Every action needs the
 * jawan_markaz.* permission AND the user's center scope:
 *   prepare / edit / submit — create, edit, submit (center staff)
 *   mark under review       — review or approve
 *   approve / return / reject — approve; never on one's own submission
 *   record payment           — record_payment; never the submitter or the approving officer
 */
class JawanMarkazExpensePolicy extends ScopedRecordPolicy
{
    protected string $module = 'jawan_markaz';

    public function update(User $user, Model $expense): bool
    {
        return $expense->isEditable() && $this->allowed($user, $expense, 'edit');
    }

    public function submit(User $user, Model $expense): bool
    {
        return $expense->canSubmit() && $this->allowed($user, $expense, 'submit');
    }

    public function startReview(User $user, Model $expense): bool
    {
        return $expense->status === 'submitted'
            && ($this->allowed($user, $expense, 'review') || $this->allowed($user, $expense, 'approve'));
    }

    public function decide(User $user, Model $expense): Response
    {
        if (! $expense->awaitingDecision()) {
            return Response::deny('This expense is not awaiting a decision.');
        }
        if (! $this->allowed($user, $expense, 'approve')) {
            return Response::deny();
        }

        return $this->submitterId($expense) === $user->id
            ? Response::deny('You cannot decide on an expense you submitted.')
            : Response::allow();
    }

    public function pay(User $user, Model $expense): Response
    {
        if (! $expense->isPayable() || ! $this->allowed($user, $expense, 'record_payment')) {
            return Response::deny();
        }
        if ($this->submitterId($expense) === $user->id) {
            return Response::deny('You cannot record payment for an expense you submitted.');
        }

        return $expense->reviewed_by === $user->id
            ? Response::deny('Approval and payment must be recorded by different people.')
            : Response::allow();
    }
}
