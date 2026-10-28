<?php

namespace App\Models;

use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasOrganizationalScope;

    protected string $scopeUnit = 'district';
    protected string $scopeColumn = 'district_id';

    /** Statuses in which implementation details can still be recorded. */
    public const EDITABLE = ['in_progress', 'returned'];

    protected $fillable = [
        'actual_start', 'actual_end', 'location', 'actual_male', 'actual_female', 'summary', 'remarks',
    ];

    protected $casts = [
        'actual_start' => 'date',
        'actual_end' => 'date',
        'completion_submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'actual_male' => 'integer',
        'actual_female' => 'integer',
        'district_id' => 'integer',
        'completion_submitted_by' => 'integer',
    ];

    public function proposal()
    {
        return $this->belongsTo(ActivityProposal::class, 'proposal_id');
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Actual expenditure lives in the Budget module's expense register (one record,
     * counted once). Rejected entries are kept there but are not expenses.
     */
    public function expenses()
    {
        return $this->hasMany(FinancialTransaction::class, 'proposal_id', 'proposal_id')
            ->where('status', '!=', 'rejected')->orderBy('txn_date')->orderBy('id');
    }

    /** Every expense entry including rejected ones, for the activity's own history. */
    public function expenseEntries()
    {
        return $this->hasMany(FinancialTransaction::class, 'proposal_id', 'proposal_id')->orderBy('txn_date')->orderBy('id');
    }

    public function documents()
    {
        return $this->morphMany(ActivityDocument::class, 'documentable')->latest('id');
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function completionSubmitter()
    {
        return $this->belongsTo(User::class, 'completion_submitted_by');
    }

    public function participantsTotal(): int
    {
        return $this->actual_male + $this->actual_female;
    }

    public function statusLabel(): string
    {
        return config("activity.activity_statuses.{$this->status}.0", ucfirst($this->status));
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }
}
