<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A cash count compared with the book balance at that moment.
 */
class PettyCashReconciliation extends Model
{
    protected $fillable = ['reconciled_on', 'book_balance', 'physical_cash', 'difference', 'status', 'remarks'];

    protected $casts = [
        'reconciled_on' => 'date',
        'book_balance' => 'decimal:2',
        'physical_cash' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function fund()
    {
        return $this->belongsTo(PettyCashFund::class, 'fund_id');
    }

    public function reconciler()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
