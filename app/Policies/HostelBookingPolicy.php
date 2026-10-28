<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Hostel bookings and hostel expenses/payments. Every action needs the
 * hostel.* permission AND the user's hostel scope:
 *   prepare / edit / submit booking   — create, edit, submit
 *   approve / reject / return         — approve; never on one's own request
 *   cancel                            — approve, or the staff member who created it (before check-in)
 *   check-in / check-out              — edit (hostel staff)
 *   see full CNIC / passport          — edit, review or approve (reveal is audited)
 *   approve expense                   — approve; never one's own entry
 *   record payment                    — record_payment; never the recorder or the approver
 */
class HostelBookingPolicy extends ScopedRecordPolicy
{
    protected string $module = 'hostel';

    public function update(User $user, Model $record): bool
    {
        return method_exists($record, 'isEditable') && $record->isEditable() && $this->allowed($user, $record, 'edit');
    }

    public function submit(User $user, Model $booking): bool
    {
        return $booking->canSubmit() && $this->allowed($user, $booking, 'submit');
    }

    public function decide(User $user, Model $record): Response
    {
        if (! $record->awaitingDecision()) {
            return Response::deny('This entry is not awaiting a decision.');
        }
        if (! $this->allowed($user, $record, 'approve')) {
            return Response::deny();
        }

        return $this->submitterId($record) === $user->id
            ? Response::deny('You cannot decide on an entry you submitted yourself.')
            : Response::allow();
    }

    public function cancel(User $user, Model $booking): bool
    {
        return $booking->isCancellable()
            && ($this->allowed($user, $booking, 'approve') || ($this->allowed($user, $booking, 'edit') && $booking->created_by === $user->id));
    }

    public function checkIn(User $user, Model $booking): bool
    {
        return $booking->status === 'approved' && $this->allowed($user, $booking, 'edit');
    }

    public function checkOut(User $user, Model $booking): bool
    {
        return $booking->status === 'checked_in' && $this->allowed($user, $booking, 'edit');
    }

    public function viewIdentity(User $user, Model $booking): bool
    {
        return $this->allowed($user, $booking, 'edit') || $this->allowed($user, $booking, 'review') || $this->allowed($user, $booking, 'approve');
    }

    public function pay(User $user, Model $expense): Response
    {
        if (! $expense->isPayable() || ! $this->allowed($user, $expense, 'record_payment')) {
            return Response::deny();
        }
        if ($this->submitterId($expense) === $user->id) {
            return Response::deny('You cannot record payment for an expense you recorded.');
        }

        return $expense->reviewed_by === $user->id
            ? Response::deny('Approval and payment must be recorded by different people.')
            : Response::allow();
    }
}
