<?php

namespace App\Models;

use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ActivityProposal extends Model
{
    use HasOrganizationalScope;

    protected string $scopeUnit = 'district';
    protected string $scopeColumn = 'district_id';

    /** Statuses in which registering staff may still change the proposal. */
    public const EDITABLE = ['draft', 'received', 'incomplete', 'returned'];

    /** Statuses that still need someone to act. */
    public const OPEN = ['draft', 'received', 'incomplete', 'under_review', 'returned', 'recommended'];

    protected $fillable = [
        'received_on', 'applicant_name', 'contact_person', 'phone', 'email', 'submission_method',
        'title', 'category', 'description', 'proposed_start', 'proposed_end', 'district_id', 'venue',
        'participants_male', 'participants_female', 'estimated_budget',
    ];

    protected $casts = [
        'received_on' => 'date',
        'proposed_start' => 'date',
        'proposed_end' => 'date',
        'decided_on' => 'date',
        'assigned_at' => 'datetime',
        'estimated_budget' => 'decimal:2',
        'participants_male' => 'integer',
        'participants_female' => 'integer',
        'district_id' => 'integer',
        'assigned_to' => 'integer',
        'submitted_by' => 'integer',
    ];

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function activity()
    {
        return $this->hasOne(Activity::class, 'proposal_id');
    }

    public function documents()
    {
        return $this->morphMany(ActivityDocument::class, 'documentable')->latest('id');
    }

    /** Funds reserved in the Budget module when the proposal was approved. */
    public function commitment()
    {
        return $this->hasOne(BudgetCommitment::class, 'proposal_id');
    }

    /** Budget head that normally funds this category. */
    public function budgetHead(): string
    {
        return config("budget.category_heads.{$this->category}", config('budget.default_activity_head'));
    }

    public function actions()
    {
        return $this->hasMany(ProposalAction::class, 'proposal_id')->orderBy('id');
    }

    public function participantsTotal(): int
    {
        return $this->participants_male + $this->participants_female;
    }

    public function statusLabel(): string
    {
        return config("activity.proposal_statuses.{$this->status}.0", ucfirst($this->status));
    }

    public function categoryLabel(): string
    {
        return config("activity.categories.{$this->category}", ucfirst($this->category));
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! filled($term)) {
            return $query;
        }
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($term)) . '%';

        return $query->where(fn ($q) => $q->where('tracking_no', 'like', $like)
            ->orWhere('applicant_name', 'like', $like)
            ->orWhere('title', 'like', $like)
            ->orWhere('contact_person', 'like', $like));
    }

    /** Next number for display only; the real one is reserved in register(). */
    public static function previewTrackingNo(): string
    {
        return self::formatTrackingNo(now()->year, self::lastSequence(now()->year) + 1);
    }

    /**
     * Save a new proposal with a unique tracking number. The unique index is
     * the final guard; a clash from a concurrent save is simply retried.
     */
    public function saveWithTrackingNo(): void
    {
        $year = now()->year;
        for ($attempt = 1; ; $attempt++) {
            try {
                DB::transaction(function () use ($year) {
                    $this->tracking_no = self::formatTrackingNo($year, self::lastSequence($year, true) + 1);
                    $this->save();
                });

                return;
            } catch (QueryException $e) {
                if ($attempt >= 5 || ($e->errorInfo[1] ?? null) !== 1062) {
                    throw $e;
                }
            }
        }
    }

    protected static function lastSequence(int $year, bool $lock = false): int
    {
        $prefix = config('activity.tracking_prefix') . "-{$year}-";
        $query = static::where('tracking_no', 'like', $prefix . '%');
        if ($lock) {
            $query->lockForUpdate();
        }
        $last = $query->orderByDesc('tracking_no')->value('tracking_no');

        return $last ? (int) substr($last, strlen($prefix)) : 0;
    }

    protected static function formatTrackingNo(int $year, int $sequence): string
    {
        return sprintf('%s-%d-%04d', config('activity.tracking_prefix'), $year, $sequence);
    }

    /** Change status and record the step in the proposal's history. */
    public function transition(string $action, ?string $to, User $actor, array $extra = []): ProposalAction
    {
        $from = $this->status;
        if ($to !== null) {
            $this->status = $to;
        }
        $this->updated_by = $actor->id;
        $this->save();

        return $this->actions()->create([
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to ?? $from,
            'user_id' => $actor->id,
            'assigned_to' => $extra['assigned_to'] ?? null,
            'activity_id' => $extra['activity_id'] ?? null,
            'remarks' => $extra['remarks'] ?? null,
            'action_date' => $extra['action_date'] ?? now()->toDateString(),
        ]);
    }
}
