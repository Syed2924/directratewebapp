<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hostel Booking Management. Hostels stay in the shared `hostels` master list
 * (also used for user scope); this adds their profile, room inventory,
 * bookings with room assignment, and hostel budgets/expenses/payments.
 * Decision history and documents use the shared financial_approvals /
 * financial_documents tables.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('hostels', function (Blueprint $table) {
            $table->string('address', 255)->nullable()->after('name');
            $table->string('phone', 30)->nullable()->after('address');
            $table->string('email', 150)->nullable()->after('phone');
            $table->text('remarks')->nullable()->after('email');
        });

        Schema::create('hostel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->string('room_number', 20);
            $table->string('room_type', 20);
            $table->unsignedSmallInteger('capacity');
            $table->string('status', 20)->default('available');      // available | maintenance | inactive
            $table->timestamps();
            $table->unique(['hostel_id', 'room_number']);
        });

        $people = function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        };

        Schema::create('hostel_bookings', function (Blueprint $table) use ($people) {
            $table->id();
            $table->string('booking_no', 20)->unique();
            $table->foreignId('hostel_id')->constrained()->restrictOnDelete();
            $table->foreignId('district_id')->constrained()->restrictOnDelete();   // requesting district / HQ
            $table->date('request_date');
            $table->string('requested_by_email', 150);
            $table->string('guest_name', 150);
            $table->text('cnic_passport')->nullable();                             // encrypted at rest
            $table->string('mobile', 30);
            $table->string('organization', 200)->nullable();
            $table->string('referred_by', 150)->nullable();
            $table->string('referrer_designation', 150)->nullable();
            $table->string('purpose', 30);
            $table->string('purpose_details', 500)->nullable();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedSmallInteger('guest_count');
            $table->unsignedSmallInteger('room_count');
            $table->string('status', 20)->default('draft');
            $table->text('remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('actual_check_in_at')->nullable();
            $table->timestamp('actual_check_out_at')->nullable();
            $table->unsignedSmallInteger('arrived_guests')->nullable();
            $table->unsignedSmallInteger('occupied_rooms')->nullable();
            $table->text('stay_remarks')->nullable();
            $people($table);
            $table->timestamps();
            $table->index(['hostel_id', 'status', 'check_in', 'check_out'], 'hb_hostel_status_dates_index');
        });

        // Rooms assigned to an approved booking (prevents double booking at room level).
        Schema::create('hostel_booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_room_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->unique(['hostel_booking_id', 'hostel_room_id'], 'hb_booking_room_unique');
        });

        Schema::create('hostel_budgets', function (Blueprint $table) use ($people) {
            $table->id();
            $table->foreignId('hostel_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_year_id')->constrained()->restrictOnDelete();
            $table->string('reference_no', 24)->unique();
            $table->string('authorization_reference', 100)->nullable();
            $table->decimal('original_amount', 16, 2);
            $table->date('issue_date');
            $table->string('status', 20)->default('draft');
            $table->text('remarks')->nullable();
            $table->foreignId('commitment_id')->nullable()->constrained('budget_commitments')->nullOnDelete();
            $people($table);
            $table->timestamps();
            $table->unique(['hostel_id', 'financial_year_id'], 'hostel_budget_year_unique');
        });

        Schema::create('hostel_budget_revisions', function (Blueprint $table) use ($people) {
            $table->id();
            $table->foreignId('hostel_budget_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no', 24)->unique();
            $table->string('direction', 10);
            $table->decimal('amount', 16, 2);
            $table->string('reason', 1000);
            $table->string('status', 20)->default('pending');
            $people($table);
            $table->timestamps();
        });

        Schema::create('hostel_expenses', function (Blueprint $table) use ($people) {
            $table->id();
            $table->string('expense_no', 24)->unique();
            $table->foreignId('hostel_id')->constrained()->restrictOnDelete();
            $table->foreignId('hostel_budget_id')->constrained()->restrictOnDelete();
            $table->date('expense_date');
            $table->string('category', 40);
            $table->string('description', 500);
            $table->string('supplier_name', 150);
            $table->string('invoice_number', 60);
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('pending');
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $people($table);
            $table->timestamps();
            $table->index(['hostel_id', 'status'], 'he_hostel_status_index');
        });

        Schema::create('hostel_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 24)->unique();
            $table->foreignId('hostel_expense_id')->constrained()->restrictOnDelete();
            $table->foreignId('hostel_id')->constrained()->restrictOnDelete();
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
        foreach (['hostel_payments', 'hostel_expenses', 'hostel_budget_revisions', 'hostel_budgets', 'hostel_booking_rooms', 'hostel_bookings', 'hostel_rooms'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('hostels', fn (Blueprint $table) => $table->dropColumn(['address', 'phone', 'email', 'remarks']));
    }
};
