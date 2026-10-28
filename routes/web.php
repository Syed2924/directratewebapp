<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Activity\ActivityController;
use App\Http\Controllers\Activity\AssignmentController;
use App\Http\Controllers\Activity\DocumentController;
use App\Http\Controllers\Activity\ProposalController;
use App\Http\Controllers\Activity\ProposalWorkflowController;
use App\Http\Controllers\Activity\ReportController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SecuritySettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Budget;
use App\Http\Controllers\Hostel;
use App\Http\Controllers\JawanMarkaz;
use App\Http\Controllers\Reports;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// ---- Guests -------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:30,1');

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

// ---- Authenticated ------------------------------------------------------
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    // Always reachable so a forced password change can be completed.
    Route::get('account/password', [AccountController::class, 'editPassword'])->name('account.password');
    Route::put('account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:10,1')->name('account.password.update');

    Route::middleware('password.fresh')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('account', [AccountController::class, 'show'])->name('account.show');
        Route::put('account', [AccountController::class, 'update'])->name('account.update');

        // Business modules: permission is checked in the controller ("{module}.view").
        Route::get('modules/{module}', [ModuleController::class, 'show'])->name('modules.show');

        // Activity Management — policies enforce permission, district scope and workflow state.
        Route::prefix('activity')->name('activity.')->group(function () {
            Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');

            Route::get('proposals', [ProposalController::class, 'index'])->name('proposals.index');
            Route::get('proposals/create', [ProposalController::class, 'create'])->name('proposals.create');
            Route::post('proposals', [ProposalController::class, 'store'])->name('proposals.store');
            Route::get('proposals/{proposal}', [ProposalController::class, 'show'])->name('proposals.show');
            Route::get('proposals/{proposal}/edit', [ProposalController::class, 'edit'])->name('proposals.edit');
            Route::put('proposals/{proposal}', [ProposalController::class, 'update'])->name('proposals.update');
            Route::delete('proposals/{proposal}', [ProposalController::class, 'destroy'])->name('proposals.destroy');
            Route::post('proposals/{proposal}/completeness', [ProposalWorkflowController::class, 'completeness'])->name('proposals.completeness');
            Route::post('proposals/{proposal}/assign', [ProposalWorkflowController::class, 'assign'])->name('proposals.assign');
            Route::post('proposals/{proposal}/review', [ProposalWorkflowController::class, 'review'])->name('proposals.review');
            Route::post('proposals/{proposal}/decision', [ProposalWorkflowController::class, 'decide'])->name('proposals.decide');
            Route::post('proposals/{proposal}/resubmit', [ProposalWorkflowController::class, 'resubmit'])->name('proposals.resubmit');

            Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');
            Route::get('activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');
            Route::put('activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
            Route::post('activities/{activity}/expenses', [ActivityController::class, 'storeExpense'])->name('activities.expenses.store');
            Route::post('activities/{activity}/documents', [ActivityController::class, 'storeDocuments'])->name('activities.documents.store');
            Route::post('activities/{activity}/submit', [ActivityController::class, 'submitCompletion'])->name('activities.submit');
            Route::post('activities/{activity}/verify', [ActivityController::class, 'verify'])->name('activities.verify');
            Route::get('activities/{activity}/report', [ActivityController::class, 'report'])->name('activities.report');

            Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
            Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        });

        // Jawan Markaz Management — policies enforce permission, center scope and workflow state.
        Route::prefix('jawan-markaz')->name('jawan-markaz.')->group(function () {
            Route::get('/', JawanMarkaz\DashboardController::class)->name('dashboard');

            Route::get('centers', [JawanMarkaz\CenterController::class, 'index'])->name('centers.index');
            Route::get('centers/{center}', [JawanMarkaz\CenterController::class, 'show'])->name('centers.show');
            Route::get('centers/{center}/edit', [JawanMarkaz\CenterController::class, 'edit'])->name('centers.edit');
            Route::put('centers/{center}', [JawanMarkaz\CenterController::class, 'update'])->name('centers.update');

            Route::get('budgets', [JawanMarkaz\BudgetController::class, 'index'])->name('budgets.index');
            Route::get('budgets/create', [JawanMarkaz\BudgetController::class, 'create'])->name('budgets.create');
            Route::post('budgets', [JawanMarkaz\BudgetController::class, 'store'])->name('budgets.store');
            Route::get('budgets/{budget}', [JawanMarkaz\BudgetController::class, 'show'])->name('budgets.show');
            Route::get('budgets/{budget}/edit', [JawanMarkaz\BudgetController::class, 'edit'])->name('budgets.edit');
            Route::put('budgets/{budget}', [JawanMarkaz\BudgetController::class, 'update'])->name('budgets.update');
            Route::post('budgets/{budget}/revisions', [JawanMarkaz\BudgetController::class, 'storeRevision'])->name('budgets.revisions.store');

            Route::get('expenses', [JawanMarkaz\ExpenseController::class, 'index'])->name('expenses.index');
            Route::get('approvals', [JawanMarkaz\ExpenseController::class, 'queue'])->name('approvals');
            Route::get('expenses/create', [JawanMarkaz\ExpenseController::class, 'create'])->name('expenses.create');
            Route::post('expenses', [JawanMarkaz\ExpenseController::class, 'store'])->name('expenses.store');
            Route::get('expenses/{expense}', [JawanMarkaz\ExpenseController::class, 'show'])->name('expenses.show');
            Route::get('expenses/{expense}/edit', [JawanMarkaz\ExpenseController::class, 'edit'])->name('expenses.edit');
            Route::put('expenses/{expense}', [JawanMarkaz\ExpenseController::class, 'update'])->name('expenses.update');
            Route::post('expenses/{expense}/submit', [JawanMarkaz\ExpenseController::class, 'submit'])->name('expenses.submit');
            Route::post('expenses/{expense}/review', [JawanMarkaz\ExpenseController::class, 'startReview'])->name('expenses.review');
            Route::post('expenses/{expense}/decision', [JawanMarkaz\ExpenseController::class, 'decide'])->name('expenses.decide');
            Route::post('expenses/{expense}/documents', [JawanMarkaz\ExpenseController::class, 'storeDocuments'])->name('expenses.documents.store');
            Route::delete('expenses/{expense}/documents/{document}', [JawanMarkaz\ExpenseController::class, 'destroyDocument'])->name('expenses.documents.destroy');
            Route::post('expenses/{expense}/payments', [JawanMarkaz\PaymentController::class, 'store'])->name('expenses.payments.store');

            Route::get('payments', [JawanMarkaz\PaymentController::class, 'index'])->name('payments.index');
            Route::get('reports', [JawanMarkaz\ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export', [JawanMarkaz\ReportController::class, 'export'])->name('reports.export');
        });

        // Report Management — reports.view to read, reports.export to print/export; data always limited to the user's scope.
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [Reports\ReportController::class, 'dashboard'])->name('dashboard');
            Route::get('catalog', [Reports\ReportController::class, 'index'])->name('index');
            Route::get('view/{key}', [Reports\ReportController::class, 'show'])->where('key', '[a-z_]+')->name('show');
            Route::get('view/{key}/print', [Reports\ReportController::class, 'print'])->where('key', '[a-z_]+')->middleware('throttle:30,1')->name('print');
            Route::get('view/{key}/export', [Reports\ReportController::class, 'export'])->where('key', '[a-z_]+')->middleware('throttle:30,1')->name('export');
        });

        // Hostel Booking Management — policies enforce permission, hostel scope and workflow state.
        Route::prefix('hostel')->name('hostel.')->group(function () {
            Route::get('/', Hostel\DashboardController::class)->name('dashboard');

            Route::get('hostels', [Hostel\HostelController::class, 'index'])->name('hostels.index');
            Route::get('hostels/{hostel}', [Hostel\HostelController::class, 'show'])->name('hostels.show');
            Route::get('hostels/{hostel}/edit', [Hostel\HostelController::class, 'edit'])->name('hostels.edit');
            Route::put('hostels/{hostel}', [Hostel\HostelController::class, 'update'])->name('hostels.update');
            Route::post('hostels/{hostel}/rooms', [Hostel\HostelController::class, 'storeRoom'])->name('hostels.rooms.store');
            Route::put('hostels/{hostel}/rooms/{room}', [Hostel\HostelController::class, 'updateRoom'])->name('hostels.rooms.update');

            Route::get('bookings', [Hostel\BookingController::class, 'index'])->name('bookings.index');
            Route::get('bookings/create', [Hostel\BookingController::class, 'create'])->name('bookings.create');
            Route::post('bookings', [Hostel\BookingController::class, 'store'])->name('bookings.store');
            Route::get('bookings/{booking}', [Hostel\BookingController::class, 'show'])->name('bookings.show');
            Route::get('bookings/{booking}/edit', [Hostel\BookingController::class, 'edit'])->name('bookings.edit');
            Route::put('bookings/{booking}', [Hostel\BookingController::class, 'update'])->name('bookings.update');
            Route::post('bookings/{booking}/submit', [Hostel\BookingController::class, 'submit'])->name('bookings.submit');
            Route::post('bookings/{booking}/decision', [Hostel\BookingController::class, 'decide'])->name('bookings.decide');
            Route::post('bookings/{booking}/cancel', [Hostel\BookingController::class, 'cancel'])->name('bookings.cancel');
            Route::post('bookings/{booking}/identity', [Hostel\BookingController::class, 'revealIdentity'])->middleware('throttle:20,1')->name('bookings.identity');
            Route::post('bookings/{booking}/documents', [Hostel\BookingController::class, 'storeDocuments'])->name('bookings.documents.store');
            Route::delete('bookings/{booking}/documents/{document}', [Hostel\BookingController::class, 'destroyDocument'])->name('bookings.documents.destroy');
            Route::post('bookings/{booking}/check-in', [Hostel\StayController::class, 'checkIn'])->name('bookings.check-in');
            Route::post('bookings/{booking}/check-out', [Hostel\StayController::class, 'checkOut'])->name('bookings.check-out');

            Route::get('stays', [Hostel\StayController::class, 'index'])->name('stays.index');

            Route::get('budgets', [Hostel\BudgetController::class, 'index'])->name('budgets.index');
            Route::get('budgets/create', [Hostel\BudgetController::class, 'create'])->name('budgets.create');
            Route::post('budgets', [Hostel\BudgetController::class, 'store'])->name('budgets.store');
            Route::get('budgets/{budget}', [Hostel\BudgetController::class, 'show'])->name('budgets.show');
            Route::get('budgets/{budget}/edit', [Hostel\BudgetController::class, 'edit'])->name('budgets.edit');
            Route::put('budgets/{budget}', [Hostel\BudgetController::class, 'update'])->name('budgets.update');
            Route::post('budgets/{budget}/revisions', [Hostel\BudgetController::class, 'storeRevision'])->name('budgets.revisions.store');

            Route::get('expenses', [Hostel\ExpenseController::class, 'index'])->name('expenses.index');
            Route::get('expenses/create', [Hostel\ExpenseController::class, 'create'])->name('expenses.create');
            Route::post('expenses', [Hostel\ExpenseController::class, 'store'])->name('expenses.store');
            Route::get('expenses/{expense}', [Hostel\ExpenseController::class, 'show'])->name('expenses.show');
            Route::post('expenses/{expense}/decision', [Hostel\ExpenseController::class, 'decide'])->name('expenses.decide');
            Route::post('expenses/{expense}/payments', [Hostel\ExpenseController::class, 'pay'])->name('expenses.payments.store');

            Route::get('reports', [Hostel\ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export', [Hostel\ReportController::class, 'export'])->name('reports.export');
        });

        // Budget & Planning — FinancialRecordPolicy enforces permission, office scope and workflow state.
        Route::prefix('budget')->name('budget.')->group(function () {
            Route::get('/', Budget\OverviewController::class)->name('overview');

            Route::get('budgets', [Budget\BudgetController::class, 'index'])->name('budgets.index');
            Route::get('budgets/create', [Budget\BudgetController::class, 'create'])->name('budgets.create');
            Route::post('budgets', [Budget\BudgetController::class, 'store'])->name('budgets.store');
            Route::get('budgets/{budget}', [Budget\BudgetController::class, 'show'])->name('budgets.show');
            Route::get('budgets/{budget}/edit', [Budget\BudgetController::class, 'edit'])->name('budgets.edit');
            Route::put('budgets/{budget}', [Budget\BudgetController::class, 'update'])->name('budgets.update');
            Route::post('budgets/{budget}/revisions', [Budget\BudgetController::class, 'storeRevision'])->name('budgets.revisions.store');

            Route::get('allocations', [Budget\AllocationController::class, 'index'])->name('allocations.index');
            Route::get('allocations/create', [Budget\AllocationController::class, 'create'])->name('allocations.create');
            Route::post('allocations', [Budget\AllocationController::class, 'store'])->name('allocations.store');
            Route::get('allocations/{allocation}', [Budget\AllocationController::class, 'show'])->name('allocations.show');
            Route::get('allocations/{allocation}/edit', [Budget\AllocationController::class, 'edit'])->name('allocations.edit');
            Route::put('allocations/{allocation}', [Budget\AllocationController::class, 'update'])->name('allocations.update');
            Route::post('allocations/{allocation}/transfers', [Budget\AllocationController::class, 'storeTransfer'])->name('allocations.transfers.store');

            Route::get('plans', [Budget\PlanController::class, 'index'])->name('plans.index');
            Route::get('plans/create', [Budget\PlanController::class, 'create'])->name('plans.create');
            Route::post('plans', [Budget\PlanController::class, 'store'])->name('plans.store');
            Route::get('plans/{plan}', [Budget\PlanController::class, 'show'])->name('plans.show');
            Route::get('plans/{plan}/edit', [Budget\PlanController::class, 'edit'])->name('plans.edit');
            Route::put('plans/{plan}', [Budget\PlanController::class, 'update'])->name('plans.update');

            Route::get('commitments', [Budget\CommitmentController::class, 'index'])->name('commitments.index');
            Route::get('commitments/create', [Budget\CommitmentController::class, 'create'])->name('commitments.create');
            Route::post('commitments', [Budget\CommitmentController::class, 'store'])->name('commitments.store');
            Route::get('commitments/{commitment}', [Budget\CommitmentController::class, 'show'])->name('commitments.show');
            Route::post('commitments/{commitment}/close', [Budget\CommitmentController::class, 'close'])->name('commitments.close');

            Route::get('transactions', [Budget\TransactionController::class, 'index'])->name('transactions.index');
            Route::get('transactions/create', [Budget\TransactionController::class, 'create'])->name('transactions.create');
            Route::get('transactions/export', [Budget\TransactionController::class, 'export'])->name('transactions.export');
            Route::post('transactions', [Budget\TransactionController::class, 'store'])->name('transactions.store');
            Route::get('transactions/{transaction}', [Budget\TransactionController::class, 'show'])->name('transactions.show');

            Route::get('petty-cash', [Budget\PettyCashController::class, 'index'])->name('petty-cash.index');
            Route::post('petty-cash', [Budget\PettyCashController::class, 'storeFund'])->name('petty-cash.store');
            Route::get('petty-cash/{fund}', [Budget\PettyCashController::class, 'show'])->name('petty-cash.show');
            Route::post('petty-cash/{fund}/vouchers', [Budget\PettyCashController::class, 'storeVoucher'])->name('petty-cash.vouchers.store');
            Route::post('petty-cash/{fund}/reconciliations', [Budget\PettyCashController::class, 'storeReconciliation'])->name('petty-cash.reconciliations.store');

            Route::get('reports', [Budget\ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/export', [Budget\ReportController::class, 'export'])->name('reports.export');

            Route::post('{type}/{id}/submit', [Budget\WorkflowController::class, 'submit'])->whereNumber('id')->name('workflow.submit');
            Route::post('{type}/{id}/decide', [Budget\WorkflowController::class, 'decide'])->whereNumber('id')->name('workflow.decide');
            Route::post('{type}/{id}/documents', [Budget\DocumentController::class, 'store'])->whereNumber('id')->name('documents.store');
            Route::get('documents/{document}', [Budget\DocumentController::class, 'show'])->name('documents.show');
            Route::delete('documents/{document}', [Budget\DocumentController::class, 'destroy'])->name('documents.destroy');
        });

        Route::prefix('admin')->name('admin.')->group(function () {
            // Users — policies enforce authority and scope per record.
            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::get('users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
            Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
            Route::post('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
            Route::post('users/{user}/password-link', [UserController::class, 'sendPasswordLink'])->middleware('throttle:6,1')->name('users.password-link');

            // Roles & permissions
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
            Route::get('permissions', [RoleController::class, 'permissions'])->name('roles.permissions');
            Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');

            // Audit log
            Route::middleware('can:audit.view')->group(function () {
                Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
                Route::get('audit/export', [AuditLogController::class, 'export'])->middleware('can:audit.export')->name('audit.export');
                Route::get('audit/{log}', [AuditLogController::class, 'show'])->name('audit.show');
            });

            // Security settings
            Route::middleware('can:security.manage')->group(function () {
                Route::get('security', [SecuritySettingsController::class, 'edit'])->name('security.edit');
                Route::put('security', [SecuritySettingsController::class, 'update'])->name('security.update');
            });

            // Organizational units
            Route::middleware('can:organization.manage')->group(function () {
                Route::get('organization', [OrganizationController::class, 'index'])->name('organization.index');
                Route::post('organization', [OrganizationController::class, 'store'])->name('organization.store');
                Route::post('organization/{type}/{id}/toggle', [OrganizationController::class, 'toggle'])->whereNumber('id')->name('organization.toggle');
            });
        });
    });
});
