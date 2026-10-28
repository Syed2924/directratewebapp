<?php

namespace App\Models;

use App\Http\Controllers\Budget\CommitmentController;
use App\Models\Concerns\HasFinancialWorkflow;
use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * A hostel's budget for one financial year (draft → submitted → approved).
 *
 *   Revised budget     = original + approved increases − approved decreases
 *   Recorded expenditure = payments made
 *   Outstanding commitments = approved expenses not yet paid
 *   Remaining budget   = revised − recorded expenditure
 *   Available to commit = revised − expenditure − outstanding commitments
 *
 * On approval the amount is committed against the central "Hostel Operations"
 * allocation; each payment is booked once in the central expense register.
 */
class HostelBudget extends Model
{
    use HasFinancialWorkflow, HasOrganizationalScope;

    protected string $scopeUnit = 'hostel';
    protected string $scopeColumn = 'hostel_id';

    public const SPENDING = ['approved', 'partially_paid', 'paid'];

    protected $fillable = ['hostel_id', 'financial_year_id', 'authorization_reference', 'original_amount', 'issue_date', 'remarks'];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'issue_date' => 'date',
        'hostel_id' => 'integer',
        'submitted_by' => 'integer',
    ];

    public function hostel()
    {
        return $this->belongsTo(Hostel::class);
    }

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function revisions()
    {
        return $this->hasMany(HostelBudgetRevision::class)->latest('id');
    }

    public function expenses()
    {
        return $this->hasMany(HostelExpense::class);
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

    public function revisedAmount(): float
    {
        return $this->status === 'approved' ? (float) $this->original_amount + $this->revisionTotal() : 0.0;
    }

    public function figures(): array
    {
        $rows = $this->expenses()->selectRaw('status, COUNT(*) as n, SUM(amount) as total')->groupBy('status')->get()->keyBy('status');
        $sum = fn (array $s) => (float) collect($s)->sum(fn ($k) => $rows[$k]->total ?? 0);
        $paid = (float) HostelPayment::whereIn('hostel_expense_id', $this->expenses()->select('id'))->sum('amount');
        $revisions = $this->status === 'approved' ? $this->revisionTotal() : 0.0;
        $revised = $this->revisedAmount();
        $approved = $sum(self::SPENDING);

        return [
            'original' => (float) $this->original_amount,
            'revisions' => $revisions,
            'allocated' => $revised,
            'pending' => $sum(['pending']),
            'pending_count' => (int) ($rows['pending']->n ?? 0),
            'approved' => $approved,
            'paid' => $paid,
            'unpaid' => $approved - $paid,
            'rejected' => $sum(['rejected']),
            'remaining' => $revised - $paid,
            'available' => $revised - $approved,
        ];
    }

    public static function fundingOptions(int $financialYearId, float $amount): Collection
    {
        return BudgetAllocation::where('financial_year_id', $financialYearId)->where('status', 'approved')
            ->where('budget_head', config('hostel.budget_head'))
            ->withLedger()->with('office')->get()
            ->filter(fn ($a) => $a->available() >= $amount)->values();
    }

    public function fundingOptionsForDecision(): Collection
    {
        return self::fundingOptions($this->financial_year_id, $this->amountToFund());
    }

    public function amountToFund(): float
    {
        return (float) $this->original_amount;
    }

    public function fundingHeadLabel(): string
    {
        return config('budget.budget_heads.' . config('hostel.budget_head'));
    }

    public function unitNoun(): string
    {
        return 'hostel';
    }

    public function decisionFieldsView(): ?string
    {
        return $this->awaitingDecision() ? 'budget.partials.unit-funding' : null;
    }

    public function decisionRules(): array
    {
        return ['allocation_id' => ['required_if:decision,approve', 'nullable', Rule::in($this->fundingOptionsForDecision()->pluck('id')->all())]];
    }

    public function checkDecision(string $decision, array $data): ?string
    {
        if ($decision !== 'approve') {
            return null;
        }
        $allocation = BudgetAllocation::find($data['allocation_id'] ?? null);

        return ! $allocation || $allocation->loadLedger()->available() < $this->amountToFund()
            ? 'The selected central allocation no longer has enough funds available.'
            : null;
    }

    public function applyDecision(string $decision, array $data): void
    {
        if ($decision !== 'approve') {
            return;
        }
        $this->loadMissing('hostel', 'financialYear');
        $commitment = CommitmentController::commit(BudgetAllocation::findOrFail($data['allocation_id']), [
            'description' => "{$this->hostel->name} — hostel budget {$this->financialYear->label}",
            'amount' => $this->original_amount,
            'committed_on' => now()->toDateString(),
            'authority_reference' => $this->authorization_reference ?: $this->reference_no,
        ], auth()->user());
        $this->commitment_id = $commitment->id;
    }
}
