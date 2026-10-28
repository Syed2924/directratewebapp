<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Budget & Planning. Balances are never stored: they are always derived from
 * approved records (budgets, revisions, allocations, transfers) and from
 * commitments and transactions, so every figure can be traced to its entries.
 */
return new class extends Migration
{
    public function up()
    {
        $people = function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        };

        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->string('label', 9)->unique();               // 2026-27
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });

        // Form A — overall Directorate budget for a financial year.
        Schema::create('budgets', function (Blueprint $table) use ($people) {
            $table->id();
            $table->foreignId('financial_year_id')->unique()->constrained()->restrictOnDelete();
            $table->string('reference_no', 20)->unique();
            $table->string('status', 20)->default('draft');
            $table->decimal('original_amount', 16, 2);
            $table->string('funding_source', 40);
            $table->string('budget_head', 40);
            $table->date('approved_on')->nullable();
            $table->date('effective_on')->nullable();
            $table->string('approving_authority', 150)->nullable();
            $table->text('remarks')->nullable();
            $people($table);
            $table->timestamps();
        });

        Schema::create('budget_revisions', function (Blueprint $table) use ($people) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no', 20)->unique();
            $table->string('direction', 10);                     // increase | decrease
            $table->decimal('amount', 16, 2);
            $table->date('effective_on');
            $table->string('reason', 1000);
            $table->string('status', 20)->default('pending');
            $people($table);
            $table->timestamps();
        });

        // Form B — office / district allocation out of the Directorate budget.
        Schema::create('budget_allocations', function (Blueprint $table) use ($people) {
            $table->id();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->string('budget_head', 40);
            $table->string('reference_no', 20)->unique();
            $table->date('allocation_date');
            $table->decimal('planned_amount', 16, 2)->default(0);
            $table->decimal('approved_amount', 16, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->text('remarks')->nullable();
            $people($table);
            $table->timestamps();
            $table->unique(['financial_year_id', 'office_id', 'budget_head'], 'alloc_year_office_head_unique');
        });

        Schema::create('budget_transfers', function (Blueprint $table) use ($people) {
            $table->id();
            $table->string('reference_no', 20)->unique();
            $table->foreignId('from_allocation_id')->constrained('budget_allocations')->restrictOnDelete();
            $table->foreignId('to_allocation_id')->constrained('budget_allocations')->restrictOnDelete();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();      // source office (scope)
            $table->decimal('amount', 16, 2);
            $table->date('effective_on');
            $table->string('reason', 1000);
            $table->string('status', 20)->default('pending');
            $people($table);
            $table->timestamps();
        });

        // Form D — planned spending by quarter, office and category.
        Schema::create('budget_plans', function (Blueprint $table) use ($people) {
            $table->id();
            $table->string('reference_no', 20)->unique();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('quarter');
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->string('category', 40);
            $table->string('budget_head', 40);
            $table->decimal('planned_amount', 16, 2);
            $table->date('expected_on')->nullable();
            $table->foreignId('proposal_id')->nullable()->constrained('activity_proposals')->nullOnDelete();
            $table->text('justification')->nullable();
            $table->string('approval_reference', 60)->nullable();
            $table->string('status', 20)->default('draft');
            $people($table);
            $table->timestamps();
        });

        Schema::create('budget_commitments', function (Blueprint $table) use ($people) {
            $table->id();
            $table->string('reference_no', 20)->unique();
            $table->foreignId('allocation_id')->constrained('budget_allocations')->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposal_id')->nullable()->unique()->constrained('activity_proposals')->nullOnDelete();
            $table->string('description', 255);
            $table->decimal('amount', 16, 2);
            $table->date('committed_on');
            $table->string('authority_reference', 100)->nullable();
            $table->string('status', 20)->default('open');       // open | closed | cancelled
            $table->timestamp('closed_at')->nullable();
            $people($table);
            $table->timestamps();
        });

        // Expense register — actual expenditure against allocations.
        Schema::create('financial_transactions', function (Blueprint $table) use ($people) {
            $table->id();
            $table->string('reference_no', 20)->unique();
            $table->foreignId('allocation_id')->constrained('budget_allocations')->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->foreignId('commitment_id')->nullable()->constrained('budget_commitments')->nullOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained('activity_proposals')->nullOnDelete();
            $table->date('txn_date');
            $table->string('category', 40);
            $table->string('description', 255);
            $table->string('payee', 150)->nullable();
            $table->decimal('amount', 16, 2);
            $table->string('payment_reference', 100)->nullable();
            $table->string('status', 20)->default('pending');    // pending | verified | rejected
            $people($table);
            $table->timestamps();
            $table->index(['financial_year_id', 'office_id', 'status'], 'txn_year_office_status_index');
            $table->index('proposal_id');
        });

        Schema::create('petty_cash_funds', function (Blueprint $table) use ($people) {
            $table->id();
            $table->foreignId('office_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('opening_balance', 14, 2);
            $table->decimal('limit', 14, 2)->nullable();
            $table->date('opened_on');
            $table->string('status', 20)->default('active');
            $people($table);
            $table->timestamps();
        });

        // Form C — petty cash payments (expense) and receipts (replenishment).
        Schema::create('petty_cash_vouchers', function (Blueprint $table) use ($people) {
            $table->id();
            $table->foreignId('fund_id')->constrained('petty_cash_funds')->restrictOnDelete();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->string('voucher_no', 20)->unique();
            $table->string('type', 20);                          // expense | replenishment
            $table->date('voucher_date');
            $table->string('category', 40)->nullable();
            $table->string('description', 255);
            $table->string('payee', 150)->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('payment_reference', 100)->nullable();
            $table->string('replenishment_reference', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 20)->default('pending');
            $people($table);
            $table->timestamps();
        });

        Schema::create('petty_cash_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_id')->constrained('petty_cash_funds')->cascadeOnDelete();
            $table->date('reconciled_on');
            $table->decimal('book_balance', 14, 2);
            $table->decimal('physical_cash', 14, 2);
            $table->decimal('difference', 14, 2);
            $table->string('status', 20);                        // balanced | difference
            $table->text('remarks')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Decision history for every financial record (submit, approve, verify, return, reject…).
        Schema::create('financial_approvals', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable');
            $table->string('action', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('financial_documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->string('kind', 30);
            $table->string('original_name', 255);
            $table->string('path', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Activity expenses now live in the expense register (financial_transactions,
        // linked by proposal_id) so the same expenditure is never counted twice.
        Schema::dropIfExists('activity_expenses');
    }

    public function down()
    {
        Schema::create('activity_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->date('spent_on');
            $table->string('head', 40);
            $table->string('description', 255);
            $table->decimal('amount', 14, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        foreach (['financial_documents', 'financial_approvals', 'petty_cash_reconciliations', 'petty_cash_vouchers', 'petty_cash_funds',
                  'financial_transactions', 'budget_commitments', 'budget_plans', 'budget_transfers', 'budget_allocations',
                  'budget_revisions', 'budgets', 'financial_years'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
