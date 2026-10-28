<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Center budgets and their revisions are financial records (created by finance
 * with budget.create, decided with budget.approve), but center staff may read
 * the budget of their own center.
 */
class JawanMarkazBudgetPolicy extends FinancialRecordPolicy
{
    public function view(User $user, Model $record): bool
    {
        return ($user->hasPermission('budget.view') || $user->hasPermission('jawan_markaz.view')) && $record->isWithinScopeOf($user);
    }
}
