<?php

namespace App\Http\Controllers\JawanMarkaz;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\FinancialDocument;
use App\Models\FinancialYear;
use App\Models\JawanMarkazBudget;
use App\Models\JawanMarkazBudgetRevision;
use App\Support\Audit;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Center budget allocations (finance prepares with budget.create; budget.approve
 * decides through the shared financial workflow) and authorized revisions.
 */
class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('jawan_markaz.view');
        $fy = FinancialYear::fromRequest($request);

        $budgets = JawanMarkazBudget::visibleTo($request->user())->with('center.district', 'financialYear', 'commitment')
            ->when($fy, fn ($q) => $q->where('financial_year_id', $fy->id))->get()->sortBy('center.name')->values();

        return view('jawan-markaz.budgets.index', ['fy' => $fy, 'years' => FinancialYear::orderByDesc('starts_on')->get(), 'budgets' => $budgets]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', JawanMarkazBudget::class);
        $budget = new JawanMarkazBudget(['financial_year_id' => FinancialYear::current()?->id, 'allocation_date' => now(), 'center_id' => $request->integer('center') ?: null]);

        return view('jawan-markaz.budgets.form', $this->formData($request, $budget) + ['reference' => ReferenceNumber::preview(JawanMarkazBudget::class, 'reference_no', 'JM-BUD')]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', JawanMarkazBudget::class);
        $data = $request->validate($this->rules($request) + ['intent' => ['required', Rule::in(['draft', 'submit'])]], $this->messages());
        $user = $request->user();

        $budget = DB::transaction(function () use ($data, $user) {
            $budget = new JawanMarkazBudget($data);
            $budget->status = 'draft';
            $budget->created_by = $budget->updated_by = $budget->submitted_by = $user->id;
            ReferenceNumber::saveWith($budget, 'reference_no', 'JM-BUD');
            $budget->recordAction('created', null, $user);
            if ($data['intent'] === 'submit') {
                $budget->recordAction('submitted', 'submitted', $user);
            }

            return $budget;
        });
        $this->storeFiles($request, $budget);
        Audit::log('jawan_markaz.budget_created', $budget, ['entity_label' => $budget->reference_no, 'description' => "{$budget->reference_no}: {$budget->center->name} PKR " . number_format($budget->allocated_amount)]);

        return redirect()->route('jawan-markaz.budgets.show', $budget)->with('status', "Center budget {$budget->reference_no} " . ($data['intent'] === 'submit' ? 'submitted for approval.' : 'saved as draft.'));
    }

    public function show(Request $request, JawanMarkazBudget $budget)
    {
        $this->authorize('view', $budget);
        $budget->load('center.district', 'financialYear', 'approvals.user', 'documents.uploader', 'revisions.approvals.user', 'revisions.submitter', 'commitment.allocation.office');

        return view('jawan-markaz.budgets.show', [
            'budget' => $budget,
            'figures' => $budget->figures(),
            'expenses' => $budget->expenses()->latest('id')->limit(10)->get(),
        ]);
    }

    public function edit(Request $request, JawanMarkazBudget $budget)
    {
        $this->authorize('update', $budget);

        return view('jawan-markaz.budgets.form', $this->formData($request, $budget) + ['reference' => $budget->reference_no]);
    }

    public function update(Request $request, JawanMarkazBudget $budget)
    {
        $this->authorize('update', $budget);
        $data = $request->validate($this->rules($request, $budget) + ['intent' => ['required', Rule::in(['draft', 'submit'])]], $this->messages());
        $user = $request->user();

        DB::transaction(function () use ($budget, $data, $user) {
            $budget->fill($data);
            $budget->recordAction('updated', null, $user);
            if ($data['intent'] === 'submit') {
                $budget->submitted_by = $user->id;
                $budget->recordAction('submitted', 'submitted', $user);
            }
        });
        $this->storeFiles($request, $budget);
        Audit::log('jawan_markaz.budget_updated', $budget, ['entity_label' => $budget->reference_no]);

        return redirect()->route('jawan-markaz.budgets.show', $budget)->with('status', $data['intent'] === 'submit' ? 'Budget submitted for approval.' : 'Budget saved.');
    }

    public function storeRevision(Request $request, JawanMarkazBudget $budget)
    {
        $this->authorize('create', JawanMarkazBudget::class);
        abort_unless($budget->status === 'approved' && $budget->isWithinScopeOf($request->user()), 403);
        $data = $request->validate([
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'reason' => ['required', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ]);
        $user = $request->user();

        $rev = new JawanMarkazBudgetRevision($data);
        $rev->jawan_markaz_budget_id = $budget->id;
        $rev->status = 'pending';
        $rev->created_by = $rev->updated_by = $rev->submitted_by = $user->id;
        ReferenceNumber::saveWith($rev, 'reference_no', 'JM-REV');
        $rev->recordAction('created', null, $user, $data['reason']);
        if ($request->hasFile('document')) {
            FinancialDocument::storeFor($rev, $request->file('document'), 'approval', $user);
        }
        Audit::log('jawan_markaz.revision_created', $rev, ['entity_label' => $rev->reference_no, 'description' => "{$rev->reference_no}: {$data['direction']} PKR " . number_format($data['amount']) . " on {$budget->reference_no}"]);

        return redirect()->route('jawan-markaz.budgets.show', $budget)->with('status', "Revision {$rev->reference_no} recorded and awaiting approval.");
    }

    protected function formData(Request $request, JawanMarkazBudget $budget): array
    {
        return [
            'budget' => $budget,
            'centers' => Center::active()->withinCoverageOf($request->user())->with('district')->orderBy('name')->get(),
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
        ];
    }

    protected function rules(Request $request, ?JawanMarkazBudget $existing = null): array
    {
        $user = $request->user();
        $file = ['file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))];

        return [
            'center_id' => ['required', 'integer', Rule::exists('centers', 'id')->where('is_active', true),
                Rule::unique('jawan_markaz_budgets')->where(fn ($q) => $q->where('financial_year_id', $request->input('financial_year_id')))->ignore($existing?->id),
                function ($a, $v, $fail) use ($user) {
                    if (! $user->scopeCoverage()->covers('center', $v)) {
                        $fail('That center is outside your scope.');
                    }
                }],
            'financial_year_id' => ['required', 'integer', 'exists:financial_years,id'],
            'allocated_amount' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'allocation_date' => ['required', 'date'],
            'authorization_reference' => ['required', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'authorization_document' => array_merge(['nullable'], $file),
        ];
    }

    protected function messages(): array
    {
        return ['center_id.unique' => 'This center already has a budget for that financial year. Record a revision instead.'];
    }

    protected function storeFiles(Request $request, JawanMarkazBudget $budget): void
    {
        if ($request->hasFile('authorization_document')) {
            FinancialDocument::storeFor($budget, $request->file('authorization_document'), 'approval', $request->user());
        }
    }
}
