<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jawan Markaz Management. Centers stay in the shared `centers` master list
 * (also used for user scope); this adds their profile fields and the
 * center-level budget, expense and payment records. Decision history and
 * invoices use the shared financial_approvals / financial_documents tables.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('centers', function (Blueprint $table) {
            $table->string('location', 255)->nullable()->after('name');
            $table->string('phone', 30)->nullable()->after('location');
            $table->string('email', 150)->nullable()->after('phone');
            $table->foreignId('incharge_id')->nullable()->after('email')->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable()->after('incharge_id');
        });

        $people = function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        };

        // A center's budget for one financial year, funded by a central commitment.
        Schema::create('jawan_markaz_budgets', function (Blueprint $table) use ($people) {
            $table->id();
            $table->foreignId('center_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->string('reference_no', 24)->unique();
            $table->string('authorization_reference', 100)->nullable();
            $table->decimal('allocated_amount', 16, 2);
            $table->date('allocation_date');
            $table->string('status', 20)->default('draft');
            $table->text('remarks')->nullable();
            $table->foreignId('commitment_id')->nullable()->constrained('budget_commitments')->nullOnDelete();
            $people($table);
            $table->timestamps();
            $table->unique(['center_id', 'financial_year_id'], 'jm_budget_center_year_unique');
        });

        Schema::create('jawan_markaz_budget_revisions', function (Blueprint $table) use ($people) {
            $table->id();
            $table->foreignId('jawan_markaz_budget_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no', 24)->unique();
            $table->string('direction', 10);                     // increase | decrease
            $table->decimal('amount', 16, 2);
            $table->string('reason', 1000);
            $table->string('status', 20)->default('pending');
            $people($table);
            $table->timestamps();
        });

        Schema::create('jawan_markaz_expenses', function (Blueprint $table) use ($people) {
            $table->id();
            $table->string('expense_number', 24)->unique();
            $table->foreignId('center_id')->constrained()->restrictOnDelete();
            $table->foreignId('jawan_markaz_budget_id')->constrained()->restrictOnDelete();
            $table->date('expense_date');
            $table->string('category', 40);
            $table->string('description', 500);
            $table->string('supplier_name', 150);
            $table->string('invoice_number', 60);
            $table->date('invoice_date');
            $table->decimal('requested_amount', 14, 2);
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $people($table);
            $table->timestamps();
            $table->index(['center_id', 'status'], 'jm_exp_center_status_index');
            $table->index(['supplier_name', 'invoice_number'], 'jm_exp_invoice_index');
        });

        // Actual payments, recorded separately from approval. Each is also an entry in the central expense register.
        Schema::create('jawan_markaz_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 24)->unique();
            $table->foreignId('jawan_markaz_expense_id')->constrained()->restrictOnDelete();
            $table->foreignId('center_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('method', 30);
            $table->string('transaction_reference', 100);
            $table->text('remarks')->nullable();
            $table->foreignId('financial_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('jawan_markaz_payments');
        Schema::dropIfExists('jawan_markaz_expenses');
        Schema::dropIfExists('jawan_markaz_budget_revisions');
        Schema::dropIfExists('jawan_markaz_budgets');
        Schema::table('centers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('incharge_id');
            $table->dropColumn(['location', 'phone', 'email', 'remarks']);
        });
    }
};
