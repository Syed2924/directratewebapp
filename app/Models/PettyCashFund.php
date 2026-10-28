<?php

namespace App\Models;

use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A small cash fund held by a custodian at an office.
 * Book balance = opening + cash received − cash paid (rejected vouchers excluded).
 * The balance is never typed in; it is always the result of the vouchers.
 */
class PettyCashFund extends Model
{
    use HasOrganizationalScope;

    protected string $scopeUnit = 'office';
    protected string $scopeColumn = 'office_id';

    protected $fillable = ['office_id', 'custodian_id', 'opening_balance', 'limit', 'opened_on'];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'limit' => 'decimal:2',
        'opened_on' => 'date',
        'office_id' => 'integer',
    ];

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function vouchers()
    {
        return $this->hasMany(PettyCashVoucher::class, 'fund_id')->latest('voucher_date')->latest('id');
    }

    public function reconciliations()
    {
        return $this->hasMany(PettyCashReconciliation::class, 'fund_id')->latest('reconciled_on')->latest('id');
    }

    public function totals(): array
    {
        $rows = $this->vouchers()->reorder()->where('status', '!=', 'rejected')
            ->selectRaw('type, SUM(amount) as total')->groupBy('type')->pluck('total', 'type');
        $received = (float) ($rows['replenishment'] ?? 0);
        $paid = (float) ($rows['expense'] ?? 0);

        return [
            'opening' => (float) $this->opening_balance,
            'received' => $received,
            'paid' => $paid,
            'balance' => (float) $this->opening_balance + $received - $paid,
        ];
    }

    public function balance(): float
    {
        return $this->totals()['balance'];
    }
}
