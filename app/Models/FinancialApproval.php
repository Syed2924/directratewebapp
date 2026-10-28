<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One decision or workflow step on a financial record. Append-only.
 */
class FinancialApproval extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    public const LABELS = [
        'created' => ['Created', 'bi-plus-circle'],
        'updated' => ['Updated', 'bi-pencil'],
        'submitted' => ['Submitted for approval', 'bi-send'],
        'approved' => ['Approved', 'bi-patch-check'],
        'returned' => ['Returned for correction', 'bi-arrow-return-left'],
        'rejected' => ['Rejected', 'bi-x-octagon'],
        'verified' => ['Verified', 'bi-clipboard-check'],
        'closed' => ['Closed — unspent balance released', 'bi-lock'],
        'cancelled' => ['Cancelled', 'bi-x-circle'],
        'under_review' => ['Review started', 'bi-search'],
        'resubmitted' => ['Corrected and resubmitted', 'bi-send'],
        'payment_recorded' => ['Payment recorded', 'bi-cash-coin'],
        'partially_paid' => ['Partially paid', 'bi-cash-coin'],
        'paid' => ['Fully paid', 'bi-check2-all'],
        'checked_in' => ['Guest checked in', 'bi-box-arrow-in-right'],
        'checked_out' => ['Guest checked out', 'bi-box-arrow-right'],
    ];

    public function approvable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->action][0] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    public function icon(): string
    {
        return self::LABELS[$this->action][1] ?? 'bi-dot';
    }

    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new \LogicException('Financial approval records are immutable.');
        }

        return parent::save($options);
    }
}
