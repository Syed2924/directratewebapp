<?php

namespace App\Models;

use App\Models\Concerns\HasFinancialWorkflow;
use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Hostel expenditure: recorded (pending approval) → approved | rejected → partially paid → paid.
 * Approval status and payment status are both derived from `status`.
 */
class HostelExpense extends Model
{
    use HasFinancialWorkflow, HasOrganizationalScope;

    protected string $scopeUnit = 'hostel';
    protected string $scopeColumn = 'hostel_id';

    protected $fillable = ['expense_date', 'category', 'description', 'supplier_name', 'invoice_number', 'amount', 'remarks'];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'decided_at' => 'datetime',
        'hostel_id' => 'integer',
        'submitted_by' => 'integer',
        'reviewed_by' => 'integer',
    ];

    public function hostel()
    {
        return $this->belongsTo(Hostel::class);
    }

    public function budget()
    {
        return $this->belongsTo(HostelBudget::class, 'hostel_budget_id');
    }

    public function payments()
    {
        return $this->hasMany(HostelPayment::class)->orderBy('payment_date')->orderBy('id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function statusLabel(): string
    {
        return config("hostel.expense_statuses.{$this->status}.0", ucfirst($this->status));
    }

    public function approvalStatus(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'rejected' => 'Rejected',
            default => 'Approved',
        };
    }

    public function paymentStatus(): string
    {
        return match ($this->status) {
            'paid' => 'Paid',
            'partially_paid' => 'Partially Paid',
            'approved' => 'Unpaid',
            default => '—',
        };
    }

    public function categoryLabel(): string
    {
        return config("hostel.expense_categories.{$this->category}", ucfirst($this->category));
    }

    public function referenceLabel(): string
    {
        return $this->expense_no;
    }

    public function awaitingDecision(): bool
    {
        return $this->status === 'pending';
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
        return in_array($this->status, HostelBudget::SPENDING, true) ? max(0, (float) $this->amount - $this->paidAmount()) : 0.0;
    }

    public function possibleDuplicates()
    {
        return static::query()->whereKeyNot($this->id ?? 0)->where('status', '!=', 'rejected')
            ->whereRaw('LOWER(TRIM(invoice_number)) = ?', [mb_strtolower(trim((string) $this->invoice_number))])
            ->whereRaw('LOWER(TRIM(supplier_name)) = ?', [mb_strtolower(trim((string) $this->supplier_name))])
            ->get();
    }
}
