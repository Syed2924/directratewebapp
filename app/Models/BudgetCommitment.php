<?php

namespace App\Models;

use App\Models\Concerns\HasFinancialWorkflow;
use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Funds reserved for an authorized obligation (e.g. an approved activity).
 * Spending against it consumes the reservation, so nothing is counted twice;
 * closing it releases whatever was not spent.
 */
class BudgetCommitment extends Model
{
    use HasFinancialWorkflow, HasOrganizationalScope;

    protected string $scopeUnit = 'office';
    protected string $scopeColumn = 'office_id';

    protected $fillable = ['description', 'amount', 'committed_on', 'authority_reference'];

    protected $casts = [
        'amount' => 'decimal:2',
        'committed_on' => 'date',
        'closed_at' => 'datetime',
        'office_id' => 'integer',
        'submitted_by' => 'integer',
    ];

    public function allocation()
    {
        return $this->belongsTo(BudgetAllocation::class, 'allocation_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function proposal()
    {
        return $this->belongsTo(ActivityProposal::class, 'proposal_id');
    }

    public function transactions()
    {
        return $this->hasMany(FinancialTransaction::class, 'commitment_id');
    }

    public function spent(): float
    {
        if (array_key_exists('spent_sum', $this->attributes)) {
            return (float) $this->spent_sum;
        }

        return (float) $this->transactions()->where('status', '!=', 'rejected')->sum('amount');
    }

    /** Still reserved: the unspent part of an open commitment. */
    public function outstanding(): float
    {
        return $this->status === 'open' ? max(0, (float) $this->amount - $this->spent()) : 0.0;
    }

    /** Commitments are authorized on entry (they carry the authority reference); they are closed, not approved. */
    public function awaitingDecision(): bool
    {
        return false;
    }

    public function canSubmit(): bool
    {
        return false;
    }

    public function isEditable(): bool
    {
        return false;
    }

    /** Release the unspent balance back to the allocation. */
    public function close(User $actor, string $remarks): void
    {
        $this->closed_at = now();
        $this->recordAction('closed', 'closed', $actor, $remarks . ' Unspent PKR ' . number_format($this->outstanding()) . ' released.');
    }
}
