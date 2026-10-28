<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // A proposal received at the Directorate, on paper or as digital documents.
        Schema::create('activity_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_no', 20)->unique();
            $table->date('received_on');
            $table->string('applicant_name', 150);
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('submission_method', 20);              // physical | digital
            $table->string('title', 200);
            $table->string('category', 40);
            $table->text('description')->nullable();
            $table->date('proposed_start')->nullable();
            $table->date('proposed_end')->nullable();
            $table->foreignId('district_id')->constrained()->restrictOnDelete();
            $table->string('venue', 200)->nullable();
            $table->unsignedInteger('participants_male')->default(0);
            $table->unsignedInteger('participants_female')->default(0);
            $table->decimal('estimated_budget', 14, 2)->default(0);
            $table->string('status', 30)->default('draft');
            $table->text('missing_information')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->date('decided_on')->nullable();
            // Staff member who registered the proposal (used for self-approval checks).
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'district_id']);
            $table->index('received_on');
        });

        // The implementation record created when a proposal is approved.
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->unique()->constrained('activity_proposals')->restrictOnDelete();
            $table->foreignId('district_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('in_progress');
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->string('location', 200)->nullable();
            $table->unsignedInteger('actual_male')->default(0);
            $table->unsignedInteger('actual_female')->default(0);
            $table->text('summary')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('completion_submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completion_submitted_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'district_id']);
        });

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

        // Scanned proposals, attachments and completion evidence (stored on the private disk).
        Schema::create('activity_documents', function (Blueprint $table) {
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

        // Every workflow step for a tracking number: who did what, when, and why.
        Schema::create('activity_proposal_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('activity_proposals')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->date('action_date')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['proposal_id', 'id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_proposal_actions');
        Schema::dropIfExists('activity_documents');
        Schema::dropIfExists('activity_expenses');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('activity_proposals');
    }
};
