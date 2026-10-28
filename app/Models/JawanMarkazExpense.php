<?php

namespace App\Models;

use App\Models\Concerns\HasFinancialWorkflow;
use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;

/**
 * An expense submitted by a Jawan Markaz.
 * draft → submitted → under_review → approved | returned | rejected → partially_paid → paid
 * Approval authorizes payment; it is not a payment. Rejected expenses are kept for audit.
 */
class JawanMarkazExpense extends Model
{
    use HasFinancialWorkflow, HasOrganizationalScope;

    protected string $scopeUnit = 'center';
    protected string $scopeColumn = 'center_id';

    protected $fillable = [
        'expense_date', 'category', 'description', 'supplier_name', 'invoice_number', 'invoice_date', 'requested_amount',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'invoice_date' => 'date',
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'center_id' => 'integer',
        'submitted_by' => 'integer',
        'reviewed_by' => 'integer',
    ];

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function budget()
    {
        return $this->belongsTo(JawanMarkazBudget::class, 'jawan_markaz_budget_id');
    }

    public function payments()
    {
        return $this->hasMany(JawanMarkazPayment::class)->orderBy('payment_date')->orderBy('id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return config("jawan_markaz.expense_statuses.{$this->status}.0", ucfirst($this->status));
    }

    public function categoryLabel(): string
    {
        return config("jawan_markaz.expense_categories.{$this->category}", ucfirst($this->category));
    }

    public function referenceLabel(): string
    {
        return $this->expense_number;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'returned'], true);
    }

    public function canSubmit(): bool
    {
        return $this->isEditable();
    }

    public function awaitingDecision(): bool
    {
        return in_array($this->status, ['submitted', 'under_review'], true);
    }

    public function isPayable(): bool
    {
        return in_array($this->status, ['approved', 'partially_paid'], true);
    }

    public function paidAmount(): float
    {
        return $this->relationLoaded('payments') ? (float) $this->payments->sum('amount') : (float) $this->payments()->sum('amount');
    }

    public function outstanding(): float
    {
        return in_array($this->status, JawanMarkazBudget::SPENDING, true) ? max(0, (float) $this->approved_amount - $this->paidAmount()) : 0.0;
    }

    /** Other non-rejected expenses with the same supplier and invoice number (flagged for checking, not blocked). */
    public function possibleDuplicates()
    {
        return static::query()
            ->whereKeyNot($this->id ?? 0)
            ->where('status', '!=', 'rejected')
            ->whereRaw('LOWER(TRIM(invoice_number)) = ?', [mb_strtolower(trim((string) $this->invoice_number))])
            ->whereRaw('LOWER(TRIM(supplier_name)) = ?', [mb_strtolower(trim((string) $this->supplier_name))])
            ->with('center')->get();
    }
}
