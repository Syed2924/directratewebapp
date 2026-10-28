<?php

namespace App\Models;

use App\Models\Concerns\HasFinancialWorkflow;
use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;

/**
 * An authorized movement of funds between two approved allocations of the same
 * financial year. Moves money; never adds to the Directorate total.
 */
class BudgetTransfer extends Model
{
    use HasFinancialWorkflow, HasOrganizationalScope;

    protected string $scopeUnit = 'office';
    protected string $scopeColumn = 'office_id';

    protected $fillable = ['amount', 'effective_on', 'reason'];

    protected $casts = ['amount' => 'decimal:2', 'effective_on' => 'date', 'office_id' => 'integer', 'submitted_by' => 'integer'];

    public function from()
    {
        return $this->belongsTo(BudgetAllocation::class, 'from_allocation_id');
    }

    public function to()
    {
        return $this->belongsTo(BudgetAllocation::class, 'to_allocation_id');
    }

    public function usesSubmission(): bool
    {
        return false;
    }

    /** Both sides must be in the user's scope. */
    public function isWithinScopeOf(User $user): bool
    {
        $coverage = $user->scopeCoverage();

        return $coverage->covers('office', $this->from->office_id) && $coverage->covers('office', $this->to->office_id);
    }

    public function checkDecision(string $decision, array $data): ?string
    {
        if ($decision !== 'approve') {
            return null;
        }
        $from = $this->from->loadLedger();
        if ($from->status !== 'approved' || $this->to->status !== 'approved') {
            return 'Both allocations must be approved.';
        }

        return (float) $this->amount > $from->available()
            ? 'The source allocation only has PKR ' . number_format($from->available()) . ' available.'
            : null;
    }
}
