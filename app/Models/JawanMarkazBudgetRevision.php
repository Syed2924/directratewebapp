<?php

namespace App\Models;

use App\Models\Concerns\HasFinancialWorkflow;
use Illuminate\Database\Eloquent\Model;

/**
 * An authorized increase or decrease of a center budget. The original
 * allocation is never edited; on approval the central commitment that funds
 * the center is adjusted by the same amount.
 */
class JawanMarkazBudgetRevision extends Model
{
    use HasFinancialWorkflow;

    protected $fillable = ['direction', 'amount', 'reason'];

    protected $casts = ['amount' => 'decimal:2', 'submitted_by' => 'integer'];

    public function budget()
    {
        return $this->belongsTo(JawanMarkazBudget::class, 'jawan_markaz_budget_id');
    }

    public function usesSubmission(): bool
    {
        return false;
    }

    public function isWithinScopeOf(User $user): bool
    {
        return $this->budget->isWithinScopeOf($user);
    }

    public function signedAmount(): float
    {
        return $this->direction === 'increase' ? (float) $this->amount : -(float) $this->amount;
    }

    public function checkDecision(string $decision, array $data): ?string
    {
        if ($decision !== 'approve') {
            return null;
        }
        $budget = $this->budget;
        if ($this->direction === 'decrease') {
            $f = $budget->figures();

            return $f['allocated'] - (float) $this->amount < $f['approved']
                ? 'This decrease would leave the ' . $budget->unitNoun() . ' budget below the PKR ' . number_format($f['approved']) . ' already approved for expenses.'
                : null;
        }
        $allocation = $budget->commitment?->allocation;
        if (! $allocation) {
            return 'The ' . $budget->unitNoun() . ' budget has no central funding commitment to increase.';
        }

        return $allocation->loadLedger()->available() < (float) $this->amount
            ? 'The central allocation ' . $allocation->reference_no . ' only has PKR ' . number_format($allocation->available()) . ' available.'
            : null;
    }

    /** Keep the central commitment equal to the authorized center budget. */
    public function applyDecision(string $decision, array $data): void
    {
        $commitment = $this->budget->commitment;
        if ($decision !== 'approve' || ! $commitment) {
            return;
        }
        $commitment->amount = (float) $commitment->amount + $this->signedAmount();
        $commitment->recordAction('updated', null, auth()->user(),
            "Adjusted by {$this->reference_no}: " . ($this->direction === 'increase' ? '+' : '−') . ' PKR ' . number_format($this->amount));
    }
}
