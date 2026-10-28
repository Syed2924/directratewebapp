<?php

namespace App\Models;

use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;

/**
 * An actual payment against an approved center expense. Never edited; it is
 * also booked in the central expense register (financial_transaction_id).
 */
class JawanMarkazPayment extends Model
{
    use HasOrganizationalScope;

    protected string $scopeUnit = 'center';
    protected string $scopeColumn = 'center_id';

    protected $fillable = ['amount', 'payment_date', 'method', 'transaction_reference', 'remarks'];

    protected $casts = ['amount' => 'decimal:2', 'payment_date' => 'date', 'center_id' => 'integer'];

    public function expense()
    {
        return $this->belongsTo(JawanMarkazExpense::class, 'jawan_markaz_expense_id');
    }

    public function center()
    {
        return $this->belongsTo(Center::class);
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
