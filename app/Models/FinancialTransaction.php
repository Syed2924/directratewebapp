<?php

namespace App\Models;

use App\Models\Concerns\HasFinancialWorkflow;
use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Expense register entry: actual expenditure against an allocation, optionally
 * against a commitment and an activity. Recorded as pending, then verified or
 * rejected by someone else. Rejected entries stay on record but count nowhere.
 */
class FinancialTransaction extends Model
{
    use HasFinancialWorkflow, HasOrganizationalScope;

    protected string $scopeUnit = 'office';
    protected string $scopeColumn = 'office_id';

    protected $fillable = ['txn_date', 'category', 'description', 'payee', 'amount', 'payment_reference'];

    protected $casts = [
        'txn_date' => 'date',
        'amount' => 'decimal:2',
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

    public function commitment()
    {
        return $this->belongsTo(BudgetCommitment::class, 'commitment_id');
    }

    public function proposal()
    {
        return $this->belongsTo(ActivityProposal::class, 'proposal_id');
    }

    public function decisionAbility(): string
    {
        return 'review';
    }

    public function categoryLabel(): string
    {
        return config("budget.expense_categories.{$this->category}", ucfirst($this->category));
    }

    /**
     * The most a new expense may be: what is free in the allocation plus, when
     * booked against a commitment, what that commitment still reserves.
     */
    public static function headroom(BudgetAllocation $allocation, ?BudgetCommitment $commitment): float
    {
        return $allocation->loadLedger()->available() + ($commitment ? $commitment->outstanding() : 0);
    }
}
