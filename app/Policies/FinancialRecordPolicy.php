<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\BudgetRevision;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * One policy for every Budget & Planning record. Each action needs the
 * budget.* permission AND the organizational scope of the record:
 *   create / edit / submit — budget.create / edit / submit
 *   approve allocations, transfers, revisions, plans, budgets — budget.approve
 *   verify transactions and petty cash vouchers — budget.review
 *   record transactions and vouchers — budget.record_payment
 * Nobody decides on an entry they submitted themselves.
 */
class FinancialRecordPolicy extends ScopedRecordPolicy
{
    protected string $module = 'budget';

    public function view(User $user, Model $record): bool
    {
        // The Directorate budget itself is visible to everyone in the module.
        if ($record instanceof Budget || $record instanceof BudgetRevision) {
            return $user->hasPermission('budget.view');
        }

        return $this->allowed($user, $record, 'view');
    }

    public function update(User $user, Model $record): bool
    {
        return $record->isEditable() && $this->allowed($user, $record, 'edit');
    }

    public function submit(User $user, Model $record): bool
    {
        return $record->canSubmit() && $this->allowed($user, $record, 'submit');
    }

    public function decide(User $user, Model $record): Response
    {
        if (! $record->awaitingDecision()) {
            return Response::deny('This entry is not awaiting a decision.');
        }
        if (! $this->allowed($user, $record, $record->decisionAbility())) {
            return Response::deny();
        }
        if ($this->submitterId($record) === $user->id) {
            return Response::deny($record->decisionAbility() === 'review'
                ? 'You cannot verify an entry you recorded yourself.'
                : 'You cannot approve an entry you submitted yourself.');
        }

        return Response::allow();
    }

    public function recordPayment(User $user, Model $record): Response
    {
        return $this->allowed($user, $record, 'record_payment') ? Response::allow() : Response::deny();
    }

    /** Close a commitment and release its unspent balance. */
    public function close(User $user, Model $record): bool
    {
        return $record->status === 'open' && $this->allowed($user, $record, 'edit');
    }
}
