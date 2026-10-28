<?php

namespace App\Http\Controllers\Hostel;

use App\Http\Controllers\Controller;
use App\Models\FinancialDocument;
use App\Models\FinancialYear;
use App\Models\Hostel;
use App\Models\HostelBudget;
use App\Models\HostelBudgetRevision;
use App\Support\Audit;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Hostel budgets: prepared by finance (budget.create), decided through the
 * shared financial workflow (budget.approve), revised by authorized entries.
 */
class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('hostel.view');
        $fy = FinancialYear::fromRequest($request);
        $budgets = HostelBudget::visibleTo($request->user())->with('hostel.district', 'commitment')
            ->when($fy, fn ($q) => $q->where('financial_year_id', $fy->id))->get()->sortBy('hostel.name')->values();

        return view('hostel.budgets.index', ['fy' => $fy, 'years' => FinancialYear::orderByDesc('starts_on')->get(), 'budgets' => $budgets]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', HostelBudget::class);
        $budget = new HostelBudget(['financial_year_id' => FinancialYear::current()?->id, 'issue_date' => now(), 'hostel_id' => $request->integer('hostel') ?: null]);

        return view('hostel.budgets.form', $this->formData($request, $budget) + ['reference' => ReferenceNumber::preview(HostelBudget::class, 'reference_no', 'HBUD')]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', HostelBudget::class);
        $data = $request->validate($this->rules($request) + ['intent' => ['required', Rule::in(['draft', 'submit'])]], $this->messages());
        $user = $request->user();

        $budget = DB::transaction(function () use ($data, $user) {
            $budget = new HostelBudget($data);
            $budget->status = 'draft';
            $budget->created_by = $budget->updated_by = $budget->submitted_by = $user->id;
            ReferenceNumber::saveWith($budget, 'reference_no', 'HBUD');
            $budget->recordAction('created', null, $user);
            if ($data['intent'] === 'submit') {
                $budget->recordAction('submitted', 'submitted', $user);
            }

            return $budget;
        });
        $this->storeFiles($request, $budget);
        Audit::log('hostel.budget_created', $budget, ['entity_label' => $budget->reference_no, 'description' => "{$budget->reference_no}: {$budget->hostel->name} PKR " . number_format($budget->original_amount)]);

        return redirect()->route('hostel.budgets.show', $budget)->with('status', "Hostel budget {$budget->reference_no} " . ($data['intent'] === 'submit' ? 'submitted for approval.' : 'saved as draft.'));
    }

    public function show(Request $request, HostelBudget $budget)
    {
        $this->authorize('view', $budget);
        $budget->load('hostel.district', 'financialYear', 'approvals.user', 'documents.uploader', 'revisions.approvals.user', 'revisions.submitter', 'commitment.allocation.office');

        return view('hostel.budgets.show', ['budget' => $budget, 'figures' => $budget->figures(), 'expenses' => $budget->expenses()->latest('id')->limit(10)->get()]);
    }

    public function edit(Request $request, HostelBudget $budget)
    {
        $this->authorize('update', $budget);

        return view('hostel.budgets.form', $this->formData($request, $budget) + ['reference' => $budget->reference_no]);
    }

    public function update(Request $request, HostelBudget $budget)
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
        Audit::log('hostel.budget_updated', $budget, ['entity_label' => $budget->reference_no]);

        return redirect()->route('hostel.budgets.show', $budget)->with('status', $data['intent'] === 'submit' ? 'Budget submitted for approval.' : 'Budget saved.');
    }

    public function storeRevision(Request $request, HostelBudget $budget)
    {
        $this->authorize('create', HostelBudget::class);
        abort_unless($budget->status === 'approved' && $budget->isWithinScopeOf($request->user()), 403);
        $data = $request->validate([
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'reason' => ['required', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ]);
        $user = $request->user();
        $rev = new HostelBudgetRevision($data);
        $rev->hostel_budget_id = $budget->id;
        $rev->status = 'pending';
        $rev->created_by = $rev->updated_by = $rev->submitted_by = $user->id;
        ReferenceNumber::saveWith($rev, 'reference_no', 'HREV');
        $rev->recordAction('created', null, $user, $data['reason']);
        if ($request->hasFile('document')) {
            FinancialDocument::storeFor($rev, $request->file('document'), 'approval', $user);
        }
        Audit::log('hostel.revision_created', $rev, ['entity_label' => $rev->reference_no, 'description' => "{$rev->reference_no}: {$data['direction']} PKR " . number_format($data['amount']) . " on {$budget->reference_no}"]);

        return redirect()->route('hostel.budgets.show', $budget)->with('status', "Revision {$rev->reference_no} recorded and awaiting approval.");
    }

    protected function formData(Request $request, HostelBudget $budget): array
    {
        return [
            'budget' => $budget,
            'hostels' => Hostel::active()->withinCoverageOf($request->user())->with('district')->orderBy('name')->get(),
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
        ];
    }

    protected function rules(Request $request, ?HostelBudget $existing = null): array
    {
        $user = $request->user();

        return [
            'hostel_id' => ['required', 'integer', Rule::exists('hostels', 'id')->where('is_active', true),
                Rule::unique('hostel_budgets')->where(fn ($q) => $q->where('financial_year_id', $request->input('financial_year_id')))->ignore($existing?->id),
                function ($a, $v, $fail) use ($user) {
                    if (! $user->scopeCoverage()->covers('hostel', $v)) {
                        $fail('That hostel is outside your scope.');
                    }
                }],
            'financial_year_id' => ['required', 'integer', 'exists:financial_years,id'],
            'original_amount' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'issue_date' => ['required', 'date'],
            'authorization_reference' => ['required', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'authorization_document' => ['nullable', 'file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ];
    }

    protected function messages(): array
    {
        return ['hostel_id.unique' => 'This hostel already has a budget for that financial year. Record a revision instead.'];
    }

    protected function storeFiles(Request $request, HostelBudget $budget): void
    {
        if ($request->hasFile('authorization_document')) {
            FinancialDocument::storeFor($budget, $request->file('authorization_document'), 'approval', $request->user());
        }
    }
}
