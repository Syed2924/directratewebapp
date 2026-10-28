<?php

namespace App\Models\Concerns;

use App\Models\FinancialApproval;
use App\Models\FinancialDocument;
use App\Models\User;

/**
 * Status workflow shared by every financial record.
 *
 * A record is either an "approval" record (draft → submitted → approved /
 * returned / rejected, decided with budget.approve) or a "verification"
 * record (pending → verified / rejected, decided with budget.review).
 * Every step is written to financial_approvals and never edited.
 */
trait HasFinancialWorkflow
{
    public function approvals()
    {
        return $this->morphMany(FinancialApproval::class, 'approvable')->orderBy('id');
    }

    public function documents()
    {
        return $this->morphMany(FinancialDocument::class, 'documentable')->latest('id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return config("budget.statuses.{$this->status}.0", ucfirst((string) $this->status));
    }

    /** 'approve' for approval records, 'review' for verification records. */
    public function decisionAbility(): string
    {
        return 'approve';
    }

    /** Whether this record goes through draft → submitted before a decision (otherwise it starts as pending). */
    public function usesSubmission(): bool
    {
        return $this->decisionAbility() === 'approve';
    }

    public function isEditable(): bool
    {
        return in_array($this->status, $this->usesSubmission() ? ['draft', 'returned'] : ['pending'], true);
    }

    public function canSubmit(): bool
    {
        return $this->usesSubmission() && in_array($this->status, ['draft', 'returned'], true);
    }

    public function awaitingDecision(): bool
    {
        return $this->status === ($this->usesSubmission() ? 'submitted' : 'pending');
    }

    /** decision key => [button label, resulting status] */
    public function decisionOptions(): array
    {
        if ($this->decisionAbility() === 'review') {
            return ['verify' => ['Verified', 'verified'], 'reject' => ['Rejected', 'rejected']];
        }
        $options = ['approve' => ['Approve', 'approved']];
        if ($this->usesSubmission()) {
            $options['return'] = ['Return for Correction', 'returned'];
        }

        return $options + ['reject' => ['Reject', 'rejected']];
    }

    /** Extra validation rules shown on the decision form (e.g. approved amount). */
    public function decisionRules(): array
    {
        return [];
    }

    /** Business checks before a decision is applied; return an error message to block it. */
    public function checkDecision(string $decision, array $data): ?string
    {
        return null;
    }

    /** Apply decision-specific fields before the status changes. */
    public function applyDecision(string $decision, array $data): void
    {
    }

    public function recordAction(string $action, ?string $to, User $actor, ?string $remarks = null): FinancialApproval
    {
        $from = $this->status;
        if ($to !== null) {
            $this->status = $to;
        }
        $this->updated_by = $actor->id;
        $this->save();

        return $this->approvals()->create([
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to ?? $from,
            'user_id' => $actor->id,
            'remarks' => $remarks,
        ]);
    }

    /** Short human label for history, audit and notifications. */
    public function referenceLabel(): string
    {
        return $this->reference_no ?? $this->voucher_no ?? (class_basename($this) . ' #' . $this->getKey());
    }
}
