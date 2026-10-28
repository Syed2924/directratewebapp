<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\ActivityProposal;
use App\Models\BudgetPlan;
use App\Models\FinancialYear;
use App\Models\Office;
use App\Support\Audit;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Form D — planned spending by quarter, office and program category.
 */
class PlanController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', BudgetPlan::class);
        $user = $request->user();
        $fy = FinancialYear::fromRequest($request);

        $plans = BudgetPlan::visibleTo($user)->with('office', 'proposal')
            ->when($fy, fn ($q) => $q->where('financial_year_id', $fy->id))
            ->when($request->filled('office'), fn ($q) => $q->where('office_id', (int) $request->input('office')))
            ->when($request->filled('quarter'), fn ($q) => $q->where('quarter', (int) $request->input('quarter')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderBy('quarter')->orderBy('expected_on')->get();

        return view('budget.plans.index', [
            'fy' => $fy,
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'plans' => $plans,
            'offices' => Office::withinCoverageOf($user)->orderBy('name')->get(),
            'byQuarter' => $plans->where('status', '!=', 'rejected')->groupBy('quarter')->map->sum('planned_amount'),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', BudgetPlan::class);
        $plan = new BudgetPlan(['financial_year_id' => FinancialYear::current()?->id, 'quarter' => 1]);
        if ($request->filled('proposal')) {
            $proposal = ActivityProposal::visibleTo($request->user())->find((int) $request->input('proposal'));
            if ($proposal) {
                $plan->fill(['category' => $proposal->category, 'planned_amount' => $proposal->estimated_budget, 'expected_on' => $proposal->proposed_start]);
                $plan->setRelation('proposal', $proposal);
            }
        }

        return view('budget.plans.form', $this->formData($request, $plan) + ['reference' => ReferenceNumber::preview(BudgetPlan::class, 'reference_no', 'BP')]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', BudgetPlan::class);
        $data = $this->validated($request);
        $user = $request->user();

        $plan = DB::transaction(function () use ($data, $user) {
            $plan = new BudgetPlan($data);
            $plan->status = 'draft';
            $plan->created_by = $plan->updated_by = $plan->submitted_by = $user->id;
            ReferenceNumber::saveWith($plan, 'reference_no', 'BP');
            $plan->recordAction('created', null, $user);
            if ($data['intent'] === 'submit') {
                $plan->recordAction('submitted', 'submitted', $user);
            }

            return $plan;
        });
        Audit::log('budget.plan_created', $plan, ['entity_label' => $plan->reference_no, 'description' => "{$plan->reference_no}: {$plan->quarterLabel()} {$plan->categoryLabel()} PKR " . number_format($plan->planned_amount)]);

        return redirect()->route('budget.plans.show', $plan)->with('status', "Plan {$plan->reference_no} " . ($data['intent'] === 'submit' ? 'submitted for approval.' : 'saved as draft.'));
    }

    public function show(Request $request, BudgetPlan $plan)
    {
        $this->authorize('view', $plan);
        $plan->load('financialYear', 'office.district', 'proposal', 'approvals.user', 'documents.uploader', 'submitter');

        return view('budget.plans.show', ['plan' => $plan]);
    }

    public function edit(Request $request, BudgetPlan $plan)
    {
        $this->authorize('update', $plan);
        $plan->load('proposal');

        return view('budget.plans.form', $this->formData($request, $plan) + ['reference' => $plan->reference_no]);
    }

    public function update(Request $request, BudgetPlan $plan)
    {
        $this->authorize('update', $plan);
        $data = $this->validated($request);
        $user = $request->user();

        DB::transaction(function () use ($plan, $data, $user) {
            $plan->fill($data);
            $plan->recordAction('updated', null, $user);
            if ($data['intent'] === 'submit') {
                $plan->submitted_by = $user->id;
                $plan->recordAction('submitted', 'submitted', $user);
            }
        });
        Audit::log('budget.plan_updated', $plan, ['entity_label' => $plan->reference_no]);

        return redirect()->route('budget.plans.show', $plan)->with('status', $data['intent'] === 'submit' ? 'Plan submitted for approval.' : 'Plan saved.');
    }

    protected function formData(Request $request, BudgetPlan $plan): array
    {
        return [
            'plan' => $plan,
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'offices' => Office::active()->withinCoverageOf($request->user())->with('district')->orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request): array
    {
        $user = $request->user();
        $validator = validator($request->all(), [
            'financial_year_id' => ['required', 'integer', 'exists:financial_years,id'],
            'quarter' => ['required', Rule::in(array_keys(config('budget.quarters')))],
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')->where('is_active', true), function ($a, $v, $fail) use ($user) {
                if (! $user->scopeCoverage()->covers('office', $v)) {
                    $fail('That office is outside your scope.');
                }
            }],
            'category' => ['required', Rule::in(array_keys(config('activity.categories')))],
            'budget_head' => ['required', Rule::in(array_keys(config('budget.budget_heads')))],
            'planned_amount' => ['required', 'numeric', 'min:1', 'max:99999999999999'],
            'expected_on' => ['nullable', 'date'],
            'proposal_tracking' => ['nullable', 'string', 'max:20'],
            'justification' => ['nullable', 'string', 'max:2000'],
            'approval_reference' => ['nullable', 'string', 'max:60'],
            'intent' => ['required', Rule::in(['draft', 'submit'])],
        ]);

        $validator->after(function (Validator $v) use ($request, $user) {
            $fy = FinancialYear::find($request->input('financial_year_id'));
            if ($fy && $request->filled('expected_on') && ! $fy->contains($request->date('expected_on'))) {
                $v->errors()->add('expected_on', "The expected date must fall within {$fy->label}.");
            }
            if ($request->filled('proposal_tracking') && ! ActivityProposal::visibleTo($user)->where('tracking_no', trim($request->input('proposal_tracking')))->exists()) {
                $v->errors()->add('proposal_tracking', 'No proposal with that tracking number in your scope.');
            }
        });
        $data = $validator->validate();
        $data['proposal_id'] = filled($data['proposal_tracking'] ?? null)
            ? ActivityProposal::where('tracking_no', trim($data['proposal_tracking']))->value('id')
            : null;

        return $data;
    }
}
