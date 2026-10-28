<?php

namespace App\Models;

use App\Models\Concerns\HasFinancialWorkflow;
use App\Support\Concerns\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Form D — how an office intends to spend over the year. Planning is not
 * allocation: a plan reserves nothing and only feeds planned-vs-actual reports.
 */
class BudgetPlan extends Model
{
    use HasFinancialWorkflow, HasOrganizationalScope;

    protected string $scopeUnit = 'office';
    protected string $scopeColumn = 'office_id';

    protected $fillable = [
        'financial_year_id', 'quarter', 'office_id', 'category', 'budget_head', 'planned_amount',
        'expected_on', 'proposal_id', 'justification', 'approval_reference',
    ];

    protected $casts = [
        'planned_amount' => 'decimal:2',
        'expected_on' => 'date',
        'quarter' => 'integer',
        'office_id' => 'integer',
        'submitted_by' => 'integer',
    ];

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function proposal()
    {
        return $this->belongsTo(ActivityProposal::class, 'proposal_id');
    }

    public function quarterLabel(): string
    {
        return config("budget.quarters.{$this->quarter}", "Q{$this->quarter}");
    }

    public function categoryLabel(): string
    {
        return config("activity.categories.{$this->category}", $this->category);
    }

    public function headLabel(): string
    {
        return config("budget.budget_heads.{$this->budget_head}", $this->budget_head);
    }
}
