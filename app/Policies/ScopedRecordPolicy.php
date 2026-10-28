<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy for business-module records (activity proposals, expenses, bookings…).
 * Every decision requires BOTH the role permission and the organizational scope
 * (spec §5, §10). Subclasses set $module, e.g. 'jawan_markaz'.
 *
 * Records are expected to use HasOrganizationalScope and to store the submitter
 * in `submitted_by` (override submitterId() otherwise).
 */
abstract class ScopedRecordPolicy
{
    protected string $module;

    /** Set true only where the Directorate has approved self-approval (spec §10). */
    protected bool $allowSelfApproval = false;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission("{$this->module}.view");
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allowed($user, $record, 'view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission("{$this->module}.create");
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allowed($user, $record, 'edit');
    }

    public function submit(User $user, Model $record): bool
    {
        return $this->allowed($user, $record, 'submit');
    }

    public function review(User $user, Model $record): bool
    {
        return $this->allowed($user, $record, 'review');
    }

    public function approve(User $user, Model $record): Response
    {
        if (! $this->allowed($user, $record, 'approve')) {
            return Response::deny();
        }
        if (! $this->allowSelfApproval && $this->submitterId($record) === $user->id) {
            return Response::deny('You cannot approve your own submission.');
        }

        return Response::allow();
    }

    public function recordPayment(User $user, Model $record): Response
    {
        if (! $this->allowed($user, $record, 'record_payment')) {
            return Response::deny();
        }
        if ($this->submitterId($record) === $user->id) {
            return Response::deny('You cannot record payment against your own submission.');
        }

        return Response::allow();
    }

    public function export(User $user): bool
    {
        return $user->hasPermission("{$this->module}.export");
    }

    protected function allowed(User $user, Model $record, string $action): bool
    {
        return $user->hasPermission("{$this->module}.{$action}") && $record->isWithinScopeOf($user);
    }

    protected function submitterId(Model $record): ?int
    {
        $id = $record->getAttribute('submitted_by');

        return $id === null ? null : (int) $id;
    }
}
