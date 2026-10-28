<?php

namespace App\Models;

use App\Http\Controllers\Budget\CommitmentController;
use App\Models\Concerns\HasFinancialWorkflow;
use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * A center's budget for one financial year (draft → submitted → approved).
 *
 * On approval the amount is committed against a central "Jawan Markaz
 * Operations" allocation in the Budget & Planning module. Payments to the
 * center's suppliers are then booked against that commitment, so the central
 * reports count every rupee exactly once.
 */
class JawanMarkazBudget extends Model
{
    use HasFinancialWorkflow, HasOrganizationalScope;

    protected string $scopeUnit = 'center';
    protected string $scopeColumn = 'center_id';

    protected $fillable = ['center_id', 'financial_year_id', 'authorization_reference', 'allocated_amount', 'allocation_date', 'remarks'];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'allocation_date' => 'date',
        'center_id' => 'integer',
        'submitted_by' => 'integer',
    ];

    /** Expense statuses that use up the budget. */
    public const SPENDING = ['approved', 'partially_paid', 'paid'];

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function revisions()
    {
        return $this->hasMany(JawanMarkazBudgetRevision::class)->latest('id');
    }

    public function expenses()
    {
        return $this->hasMany(JawanMarkazExpense::class);
    }

    public function commitment()
    {
        return $this->belongsTo(BudgetCommitment::class, 'commitment_id');
    }

    public function revisionTotal(): float
    {
        return (float) $this->revisions()->reorder()->where('status', 'approved')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'increase' THEN amount ELSE -amount END), 0) as net")->value('net');
    }

    /** Authorized budget: original allocation ± approved revisions (0 until approved). */
    public function revisedAmount(): float
    {
        return $this->status === 'approved' ? (float) $this->allocated_amount + $this->revisionTotal() : 0.0;
    }

    /**
     * Figures kept apart as the specification requires:
     * submitted (pending) ≠ approved ≠ paid; available = authorized budget − approved.
     */
    public function figures(): array
    {
        $rows = $this->expenses()->selectRaw('status, COUNT(*) as n, SUM(requested_amount) as requested, SUM(COALESCE(approved_amount, 0)) as approved')
            ->groupBy('status')->get()->keyBy('status');
        $paid = (float) JawanMarkazPayment::whereIn('jawan_markaz_expense_id', $this->expenses()->select('id'))->sum('amount');
        $sum = fn (array $statuses, string $col) => (float) collect($statuses)->sum(fn ($s) => $rows[$s]->{$col} ?? 0);
        $count = fn (array $statuses) => (int) collect($statuses)->sum(fn ($s) => $rows[$s]->n ?? 0);
        $allocated = $this->revisedAmount();
        $approved = $sum(self::SPENDING, 'approved');

        return [
            'allocated' => $allocated,
            'pending' => $sum(['submitted', 'under_review'], 'requested'),
            'pending_count' => $count(['submitted', 'under_review']),
            'approved' => $approved,
            'paid' => $paid,
            'unpaid' => $approved - $paid,
            'rejected' => $sum(['rejected'], 'requested'),
            'rejected_count' => $count(['rejected']),
            'returned_count' => $count(['returned']),
            'available' => $allocated - $approved,
        ];
    }

    public function available(): float
    {
        return $this->figures()['available'];
    }

    /** Central allocations that can fund this budget: approved "Jawan Markaz" head, same year, enough left. */
    public static function fundingOptions(int $financialYearId, float $amount): Collection
    {
        return BudgetAllocation::where('financial_year_id', $financialYearId)->where('status', 'approved')
            ->where('budget_head', config('jawan_markaz.budget_head'))
            ->withLedger()->with('office')->get()
            ->filter(fn ($a) => $a->available() >= $amount)->values();
    }

    public function decisionRules(): array
    {
        return ['allocation_id' => ['required_if:decision,approve', 'nullable',
            Rule::in(self::fundingOptions($this->financial_year_id, (float) $this->allocated_amount)->pluck('id')->all())]];
    }

    public function decisionFieldsView(): ?string
    {
        return $this->awaitingDecision() ? 'budget.partials.unit-funding' : null;
    }

    public function fundingOptionsForDecision(): Collection
    {
        return self::fundingOptions($this->financial_year_id, $this->amountToFund());
    }

    public function amountToFund(): float
    {
        return (float) $this->allocated_amount;
    }

    public function fundingHeadLabel(): string
    {
        return config('budget.budget_heads.' . config('jawan_markaz.budget_head'));
    }

    public function unitNoun(): string
    {
        return 'center';
    }

    public function checkDecision(string $decision, array $data): ?string
    {
        if ($decision !== 'approve') {
            return null;
        }
        $allocation = BudgetAllocation::find($data['allocation_id'] ?? null);

        return ! $allocation || $allocation->loadLedger()->available() < (float) $this->allocated_amount
            ? 'The selected central allocation no longer has enough funds available.'
            : null;
    }

    /** Approval reserves the center's budget in the central Budget & Planning module. */
    public function applyDecision(string $decision, array $data): void
    {
        if ($decision !== 'approve') {
            return;
        }
        $this->loadMissing('center', 'financialYear');
        $commitment = CommitmentController::commit(BudgetAllocation::findOrFail($data['allocation_id']), [
            'description' => "{$this->center->name} — center budget {$this->financialYear->label}",
            'amount' => $this->allocated_amount,
            'committed_on' => now()->toDateString(),
            'authority_reference' => $this->authorization_reference ?: $this->reference_no,
        ], auth()->user());
        $this->commitment_id = $commitment->id;
    }
}
