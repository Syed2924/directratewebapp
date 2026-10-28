<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only: the application never updates or deletes audit rows.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
        'actor_id' => 'integer',
        'entity_id' => 'integer',
    ];

    public const EVENTS = [
        'login.success' => 'Login Success',
        'login.failed' => 'Login Failed',
        'login.blocked' => 'Login Blocked',
        'logout' => 'Logout',
        'session.expired' => 'Session Expired',
        'account.locked' => 'Account Locked',
        'account.unlocked' => 'Account Unlocked',
        'password.changed' => 'Password Changed',
        'password.reset_requested' => 'Password Reset Requested',
        'password.reset' => 'Password Reset',
        'password.link_sent' => 'Password Setup Link Sent',
        'user.created' => 'User Created',
        'user.updated' => 'User Updated',
        'user.activated' => 'User Activated',
        'user.deactivated' => 'User Deactivated',
        'user.roles_changed' => 'Role Assigned',
        'user.scope_changed' => 'Scope Changed',
        'role.created' => 'Role Created',
        'role.updated' => 'Role Updated',
        'role.deleted' => 'Role Deleted',
        'role.permissions_changed' => 'Permission Updated',
        'security.settings_changed' => 'Security Settings Changed',
        'organization.changed' => 'Organization Unit Changed',
        'access.denied' => 'Access Denied',
        'audit.exported' => 'Audit Log Exported',
        'activity.proposal_registered' => 'Proposal Registered',
        'activity.proposal_drafted' => 'Proposal Draft Saved',
        'activity.proposal_updated' => 'Proposal Updated',
        'activity.proposal_deleted' => 'Proposal Draft Deleted',
        'activity.proposal_incomplete' => 'Proposal Information Missing',
        'activity.proposal_assigned' => 'Proposal Assigned',
        'activity.proposal_recommended' => 'Proposal Recommended',
        'activity.proposal_review_returned' => 'Proposal Returned by Officer',
        'activity.proposal_review_rejected' => 'Proposal Rejected by Officer',
        'activity.proposal_resubmitted' => 'Proposal Resubmitted',
        'activity.proposal_approved' => 'Proposal Approved',
        'activity.proposal_decision_returned' => 'Proposal Returned by Approver',
        'activity.proposal_decision_rejected' => 'Proposal Rejected by Approver',
        'activity.execution_updated' => 'Activity Details Updated',
        'activity.expense_added' => 'Activity Expense Added',
        'activity.expense_removed' => 'Activity Expense Removed',
        'activity.evidence_uploaded' => 'Activity Evidence Uploaded',
        'activity.document_removed' => 'Activity Document Removed',
        'activity.completion_submitted' => 'Activity Completion Submitted',
        'activity.completion_returned' => 'Activity Completion Returned',
        'activity.completed' => 'Activity Completed',
        'activity.report_exported' => 'Activity Report Exported',
    ];

    public function eventLabel(): string
    {
        return self::EVENTS[$this->event] ?? ucwords(str_replace(['.', '_'], ' ', $this->event));
    }

    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new \LogicException('Audit log entries are immutable.');
        }

        return parent::save($options);
    }

    public function delete()
    {
        throw new \LogicException('Audit log entries cannot be deleted from the application.');
    }
}
