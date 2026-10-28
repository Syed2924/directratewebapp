<?php

namespace App\Models;

use App\Models\Concerns\HasFinancialWorkflow;
use Illuminate\Database\Eloquent\Model;

/**
 * Form A — the Directorate's overall budget for one financial year.
 * Revised budget = original approved budget ± approved revisions.
 */
class Budget extends Model
{
    use HasFinancialWorkflow;

    protected $fillable = [
        'original_amount', 'funding_source', 'budget_head', 'approved_on', 'effective_on', 'approving_authority', 'remarks',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'approved_on' => 'date',
        'effective_on' => 'date',
        'submitted_by' => 'integer',
    ];

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function revisions()
    {
        return $this->hasMany(BudgetRevision::class)->latest('id');
    }

    public function revisionTotal(): float
    {
        return (float) $this->revisions()->reorder()->where('status', 'approved')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'increase' THEN amount ELSE -amount END), 0) as net")
            ->value('net');
    }

    public function revisedAmount(): float
    {
        return (float) $this->original_amount + $this->revisionTotal();
    }

    /** Sum of approved allocations made from this budget. */
    public function allocatedAmount(?int $exceptAllocationId = null): float
    {
        return (float) BudgetAllocation::where('financial_year_id', $this->financial_year_id)
            ->where('status', 'approved')
            ->when($exceptAllocationId, fn ($q) => $q->whereKeyNot($exceptAllocationId))
            ->sum('approved_amount');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /** Directorate-level records: only users with Directorate-wide (global) scope may change them. */
    public function isWithinScopeOf(User $user): bool
    {
        return $user->scopeCoverage()->global;
    }

    public function checkDecision(string $decision, array $data): ?string
    {
        if ($decision === 'approve' && $this->original_amount <= 0) {
            return 'The original approved budget must be greater than zero.';
        }

        return null;
    }

    public function applyDecision(string $decision, array $data): void
    {
        if ($decision === 'approve' && ! $this->approved_on) {
            $this->approved_on = now();
        }
    }
}
