<?php

namespace App\Models;

/**
 * An authorized increase or decrease of a hostel budget. Same rules as a
 * Jawan Markaz budget revision: a decrease may not go below approved
 * expenses, an increase needs central headroom, and the central commitment
 * is adjusted on approval.
 */
class HostelBudgetRevision extends JawanMarkazBudgetRevision
{
    protected $table = 'hostel_budget_revisions';

    public function budget()
    {
        return $this->belongsTo(HostelBudget::class, 'hostel_budget_id');
    }
}
