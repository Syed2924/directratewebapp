<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Hostel budgets and revisions: financial records (budget.create / budget.approve),
 * readable by hostel staff for their own hostel.
 */
class HostelBudgetPolicy extends FinancialRecordPolicy
{
    public function view(User $user, Model $record): bool
    {
        return ($user->hasPermission('budget.view') || $user->hasPermission('hostel.view')) && $record->isWithinScopeOf($user);
    }
}
