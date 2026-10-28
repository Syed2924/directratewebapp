<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Role;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\ActivityProposalPolicy;
use App\Policies\FinancialRecordPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        ActivityProposal::class => ActivityProposalPolicy::class,
        Activity::class => ActivityPolicy::class,
        \App\Models\Budget::class => FinancialRecordPolicy::class,
        \App\Models\BudgetRevision::class => FinancialRecordPolicy::class,
        \App\Models\BudgetAllocation::class => FinancialRecordPolicy::class,
        \App\Models\BudgetTransfer::class => FinancialRecordPolicy::class,
        \App\Models\BudgetPlan::class => FinancialRecordPolicy::class,
        \App\Models\BudgetCommitment::class => FinancialRecordPolicy::class,
        \App\Models\FinancialTransaction::class => FinancialRecordPolicy::class,
        \App\Models\PettyCashFund::class => FinancialRecordPolicy::class,
        \App\Models\PettyCashVoucher::class => FinancialRecordPolicy::class,
        \App\Models\JawanMarkazBudget::class => \App\Policies\JawanMarkazBudgetPolicy::class,
        \App\Models\JawanMarkazBudgetRevision::class => \App\Policies\JawanMarkazBudgetPolicy::class,
        \App\Models\JawanMarkazExpense::class => \App\Policies\JawanMarkazExpensePolicy::class,
        \App\Models\JawanMarkazPayment::class => \App\Policies\JawanMarkazExpensePolicy::class,
        \App\Models\HostelBooking::class => \App\Policies\HostelBookingPolicy::class,
        \App\Models\HostelExpense::class => \App\Policies\HostelBookingPolicy::class,
        \App\Models\HostelPayment::class => \App\Policies\HostelBookingPolicy::class,
        \App\Models\HostelBudget::class => \App\Policies\HostelBudgetPolicy::class,
        \App\Models\HostelBudgetRevision::class => \App\Policies\HostelBudgetPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();

        // Permission keys ("users.manage", "activity.approve", …) are checked
        // directly against the user's active roles. Use them with
        // `can:` middleware, @can in Blade, or Gate::allows(). There is no
        // blanket Super Admin bypass: the Super Admin role simply holds every
        // permission, so the matrix on screen is always the truth.
        Gate::before(function (User $user, string $ability) {
            if (str_contains($ability, '.')) {
                return $user->hasPermission($ability);
            }

            return null;
        });
    }
}
