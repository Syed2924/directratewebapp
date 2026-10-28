<?php

namespace App\Models;

use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;

/**
 * An actual payment against an approved hostel expense; also booked once in
 * the central expense register (financial_transaction_id). Never edited.
 */
class HostelPayment extends Model
{
    use HasOrganizationalScope;

    protected string $scopeUnit = 'hostel';
    protected string $scopeColumn = 'hostel_id';

    protected $fillable = ['amount', 'payment_date', 'method', 'transaction_reference', 'remarks'];

    protected $casts = ['amount' => 'decimal:2', 'payment_date' => 'date', 'hostel_id' => 'integer'];

    public function expense()
    {
        return $this->belongsTo(HostelExpense::class, 'hostel_expense_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function transaction()
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function documents()
    {
        return $this->morphMany(FinancialDocument::class, 'documentable')->latest('id');
    }

    public function methodLabel(): string
    {
        return config("jawan_markaz.payment_methods.{$this->method}", $this->method);
    }

    public function referenceLabel(): string
    {
        return $this->reference_no;
    }
}
