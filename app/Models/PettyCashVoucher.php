<?php

namespace App\Models;

use App\Models\Concerns\HasFinancialWorkflow;
use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Form C — a petty cash payment (expense) or cash received (replenishment).
 * Vouchers are never edited or deleted; a wrong voucher is rejected and re-entered.
 */
class PettyCashVoucher extends Model
{
    use HasFinancialWorkflow, HasOrganizationalScope;

    protected string $scopeUnit = 'office';
    protected string $scopeColumn = 'office_id';

    protected $fillable = [
        'type', 'voucher_date', 'category', 'description', 'payee', 'amount',
        'payment_reference', 'replenishment_reference', 'remarks',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'amount' => 'decimal:2',
        'office_id' => 'integer',
        'submitted_by' => 'integer',
    ];

    public function fund()
    {
        return $this->belongsTo(PettyCashFund::class, 'fund_id');
    }

    public function decisionAbility(): string
    {
        return 'review';
    }

    public function isEditable(): bool
    {
        return false;
    }

    public function categoryLabel(): string
    {
        return $this->type === 'replenishment'
            ? 'Replenishment'
            : config("budget.petty_cash_categories.{$this->category}", ucfirst((string) $this->category));
    }
}
