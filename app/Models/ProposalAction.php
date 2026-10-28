<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One step in a proposal's (and its activity's) history. Append-only.
 */
class ProposalAction extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'activity_proposal_actions';

    protected $guarded = ['id'];

    protected $casts = [
        'action_date' => 'date',
        'created_at' => 'datetime',
    ];

    public const LABELS = [
        'drafted' => ['Saved as draft', 'bi-pencil-square'],
        'registered' => ['Proposal registered', 'bi-inbox'],
        'updated' => ['Details updated', 'bi-pencil'],
        'info_missing' => ['Missing information recorded', 'bi-exclamation-circle'],
        'info_completed' => ['Information completed', 'bi-check2-square'],
        'assigned' => ['Assigned to officer', 'bi-person-check'],
        'reassigned' => ['Reassigned', 'bi-arrow-left-right'],
        'recommended' => ['Recommended for approval', 'bi-hand-thumbs-up'],
        'review_returned' => ['Returned for correction by reviewing officer', 'bi-arrow-return-left'],
        'review_rejected' => ['Rejected by reviewing officer', 'bi-x-circle'],
        'resubmitted' => ['Corrected and resubmitted', 'bi-send'],
        'approved' => ['Approved', 'bi-patch-check'],
        'decision_returned' => ['Returned for correction by approver', 'bi-arrow-return-left'],
        'decision_rejected' => ['Rejected by approver', 'bi-x-octagon'],
        'activity_created' => ['Activity record created', 'bi-calendar-plus'],
        'completion_submitted' => ['Completion report submitted', 'bi-send-check'],
        'completion_returned' => ['Completion report returned for correction', 'bi-arrow-return-left'],
        'completed' => ['Completion verified, activity finalized', 'bi-flag'],
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
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
            throw new \LogicException('Proposal history entries are immutable.');
        }

        return parent::save($options);
    }
}
