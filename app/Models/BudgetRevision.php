<?php

namespace App\Models;

use App\Models\Concerns\HasFinancialWorkflow;
use Illuminate\Database\Eloquent\Model;

/**
 * An authorized increase or decrease of the Directorate budget. Recorded as
 * its own entry; the original budget amount is never edited.
 */
class BudgetRevision extends Model
{
    use HasFinancialWorkflow;

    protected $fillable = ['direction', 'amount', 'effective_on', 'reason'];

    protected $casts = ['amount' => 'decimal:2', 'effective_on' => 'date', 'submitted_by' => 'integer'];

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function usesSubmission(): bool
    {
        return false;
    }

    public function signedAmount(): float
    {
        return $this->direction === 'increase' ? (float) $this->amount : -(float) $this->amount;
    }

    public function isWithinScopeOf(User $user): bool
    {
        return $user->scopeCoverage()->global;
    }

    public function checkDecision(string $decision, array $data): ?string
    {
        if ($decision !== 'approve' || $this->direction !== 'decrease') {
            return null;
        }
        $after = $this->budget->revisedAmount() - (float) $this->amount;
        $allocated = $this->budget->allocatedAmount();

        return $after < $allocated
            ? 'This decrease would leave the budget (PKR ' . number_format($after) . ') below what is already allocated (PKR ' . number_format($allocated) . ').'
            : null;
    }
}
