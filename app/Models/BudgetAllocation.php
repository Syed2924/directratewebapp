<?php

namespace App\Models;

use App\Models\Concerns\HasFinancialWorkflow;
use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Form B — part of the Directorate budget assigned to an office for a budget head.
 *
 *   revised    = approved amount + approved transfers in − approved transfers out
 *   spent      = recorded expenditure (pending + verified; rejected excluded)
 *   committed  = unspent part of open commitments
 *   available  = revised − committed − spent
 */
class BudgetAllocation extends Model
{
    use HasFinancialWorkflow, HasOrganizationalScope;

    protected string $scopeUnit = 'office';
    protected string $scopeColumn = 'office_id';

    protected $fillable = ['financial_year_id', 'office_id', 'budget_head', 'allocation_date', 'planned_amount', 'remarks'];

    protected $casts = [
        'allocation_date' => 'date',
        'planned_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'office_id' => 'integer',
        'submitted_by' => 'integer',
    ];

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function transfersIn()
    {
        return $this->hasMany(BudgetTransfer::class, 'to_allocation_id');
    }

    public function transfersOut()
    {
        return $this->hasMany(BudgetTransfer::class, 'from_allocation_id');
    }

    public function commitments()
    {
        return $this->hasMany(BudgetCommitment::class, 'allocation_id');
    }

    public function openCommitments()
    {
        return $this->hasMany(BudgetCommitment::class, 'allocation_id')->where('status', 'open');
    }

    public function transactions()
    {
        return $this->hasMany(FinancialTransaction::class, 'allocation_id');
    }

    /** Eager-load everything the balance methods need, in a fixed number of queries. */
    public function scopeWithLedger(Builder $query): Builder
    {
        return $query->withSum(self::ledgerAggregates()[0], 'amount')
            ->withSum(self::ledgerAggregates()[1], 'amount')
            ->withSum(self::ledgerAggregates()[2], 'amount')
            ->with(['openCommitments' => fn ($c) => $c->withSum(['transactions as spent_sum' => fn ($t) => $t->where('status', '!=', 'rejected')], 'amount')]);
    }

    protected static function ledgerAggregates(): array
    {
        return [
            ['transfersIn as transfers_in_sum' => fn ($t) => $t->where('status', 'approved')],
            ['transfersOut as transfers_out_sum' => fn ($t) => $t->where('status', 'approved')],
            ['transactions as spent_sum' => fn ($t) => $t->where('status', '!=', 'rejected')],
        ];
    }

    public function loadLedger(): self
    {
        foreach (self::ledgerAggregates() as $aggregate) {
            $this->loadSum($aggregate, 'amount');
        }
        $this->load(['openCommitments' => fn ($c) => $c->withSum(['transactions as spent_sum' => fn ($t) => $t->where('status', '!=', 'rejected')], 'amount')]);

        return $this;
    }

    protected function ensureLedger(): void
    {
        if (! array_key_exists('spent_sum', $this->attributes)) {
            $this->loadLedger();
        }
    }

    public function transfersInTotal(): float
    {
        $this->ensureLedger();

        return (float) $this->transfers_in_sum;
    }

    public function transfersOutTotal(): float
    {
        $this->ensureLedger();

        return (float) $this->transfers_out_sum;
    }

    public function revised(): float
    {
        return $this->status === 'approved'
            ? (float) $this->approved_amount + $this->transfersInTotal() - $this->transfersOutTotal()
            : 0.0;
    }

    public function spent(): float
    {
        $this->ensureLedger();

        return (float) $this->spent_sum;
    }

    public function committed(): float
    {
        $this->ensureLedger();

        return (float) $this->openCommitments->sum(fn (BudgetCommitment $c) => $c->outstanding());
    }

    public function available(): float
    {
        return $this->revised() - $this->committed() - $this->spent();
    }

    public function label(): string
    {
        return $this->office->name . ' · ' . $this->headLabel();
    }

    public function headLabel(): string
    {
        return config("budget.budget_heads.{$this->budget_head}", $this->budget_head);
    }

    public function decisionRules(): array
    {
        return ['approved_amount' => ['required_if:decision,approve', 'nullable', 'numeric', 'min:1', 'max:99999999999999']];
    }

    public function checkDecision(string $decision, array $data): ?string
    {
        if ($decision !== 'approve') {
            return null;
        }
        $budget = Budget::where('financial_year_id', $this->financial_year_id)->first();
        if (! $budget || ! $budget->isApproved()) {
            return 'The Directorate budget for this financial year must be approved before allocations.';
        }
        $unallocated = $budget->revisedAmount() - $budget->allocatedAmount($this->id);
        if ((float) $data['approved_amount'] > $unallocated) {
            return 'Only PKR ' . number_format($unallocated) . ' of the ' . $budget->financialYear->label . ' budget is still unallocated.';
        }

        return null;
    }

    public function applyDecision(string $decision, array $data): void
    {
        if ($decision === 'approve') {
            $this->approved_amount = $data['approved_amount'];
        }
    }
}
