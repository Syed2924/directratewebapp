<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetRevision;
use App\Models\FinancialDocument;
use App\Models\FinancialYear;
use App\Support\Audit;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Form A — the overall Directorate budget, plus its authorized revisions.
 */
class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Budget::class);

        return view('budget.budgets.index', [
            'budgets' => Budget::with('financialYear', 'revisions')->get()->sortByDesc(fn ($b) => $b->financialYear->starts_on)->values(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Budget::class);
        abort_unless($request->user()->scopeCoverage()->global, 403, 'Only Directorate-wide users can register the Directorate budget.');

        $start = now()->month >= 7 ? now()->year : now()->year - 1;
        $budget = new Budget(['funding_source' => 'government_grant', 'budget_head' => 'youth_development']);
        $budget->setRelation('financialYear', new FinancialYear([
            'label' => $start . '-' . substr((string) ($start + 1), 2),
            'starts_on' => "{$start}-07-01",
            'ends_on' => ($start + 1) . '-06-30',
        ]));

        return view('budget.budgets.form', ['budget' => $budget, 'reference' => ReferenceNumber::preview(Budget::class, 'reference_no', 'DB')]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Budget::class);
        abort_unless($request->user()->scopeCoverage()->global, 403);
        $data = $request->validate($this->rules() + [
            'fy_label' => ['required', 'regex:/^\d{4}-\d{2}$/', function ($a, $v, $fail) {
                if (Budget::whereHas('financialYear', fn ($q) => $q->where('label', $v))->exists()) {
                    $fail("A budget for {$v} already exists.");
                }
            }],
            'intent' => ['required', Rule::in(['draft', 'submit'])],
        ]);
        $user = $request->user();

        $budget = DB::transaction(function () use ($data, $user) {
            $fy = FinancialYear::updateOrCreate(['label' => $data['fy_label']], ['starts_on' => $data['fy_starts_on'], 'ends_on' => $data['fy_ends_on']]);
            $budget = new Budget($data);
            $budget->financial_year_id = $fy->id;
            $budget->status = 'draft';
            $budget->created_by = $budget->updated_by = $budget->submitted_by = $user->id;
            ReferenceNumber::saveWith($budget, 'reference_no', 'DB');
            $budget->recordAction('created', null, $user);
            if ($data['intent'] === 'submit') {
                $budget->recordAction('submitted', 'submitted', $user);
            }

            return $budget;
        });
        $this->storeFiles($request, $budget);
        Audit::log('budget.budget_created', $budget, ['entity_label' => $budget->reference_no, 'description' => "{$budget->reference_no}: {$data['fy_label']} budget PKR " . number_format($budget->original_amount)]);

        return redirect()->route('budget.budgets.show', $budget)->with('status', "Budget {$budget->reference_no} " . ($data['intent'] === 'submit' ? 'registered and submitted for approval.' : 'saved as draft.'));
    }

    public function show(Request $request, Budget $budget)
    {
        $this->authorize('view', $budget);
        $budget->load('financialYear', 'revisions.submitter', 'revisions.approvals.user', 'approvals.user', 'documents.uploader', 'submitter');

        return view('budget.budgets.show', [
            'budget' => $budget,
            'allocated' => $budget->allocatedAmount(),
            'revised' => $budget->revisedAmount(),
        ]);
    }

    public function edit(Request $request, Budget $budget)
    {
        $this->authorize('update', $budget);
        $budget->load('financialYear', 'documents');

        return view('budget.budgets.form', ['budget' => $budget, 'reference' => $budget->reference_no]);
    }

    public function update(Request $request, Budget $budget)
    {
        $this->authorize('update', $budget);
        $data = $request->validate($this->rules() + ['intent' => ['required', Rule::in(['draft', 'submit'])]]);
        $user = $request->user();

        $before = array_map('strval', $budget->only(array_keys($budget->getFillable())));
        DB::transaction(function () use ($budget, $data, $user) {
            $budget->financialYear->update(['starts_on' => $data['fy_starts_on'], 'ends_on' => $data['fy_ends_on']]);
            $budget->fill($data);
            $budget->recordAction('updated', null, $user);
            if ($data['intent'] === 'submit') {
                $budget->submitted_by = $user->id;
                $budget->recordAction('submitted', 'submitted', $user);
            }
        });
        $this->storeFiles($request, $budget);
        [$old, $new] = Audit::diff($before, array_map('strval', $budget->only(array_keys($before))));
        Audit::log('budget.budget_updated', $budget, ['entity_label' => $budget->reference_no, 'before' => $old, 'after' => $new]);

        return redirect()->route('budget.budgets.show', $budget)->with('status', $data['intent'] === 'submit' ? 'Budget submitted for approval.' : 'Budget saved.');
    }

    /** Record an authorized increase or decrease; it takes effect only once approved. */
    public function storeRevision(Request $request, Budget $budget)
    {
        $this->authorize('create', Budget::class);
        abort_unless($budget->isApproved() && $budget->isWithinScopeOf($request->user()), 403);
        $data = $request->validate([
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999999999'],
            'effective_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ]);
        $user = $request->user();

        $revision = new BudgetRevision($data);
        $revision->budget_id = $budget->id;
        $revision->status = 'pending';
        $revision->created_by = $revision->updated_by = $revision->submitted_by = $user->id;
        ReferenceNumber::saveWith($revision, 'reference_no', 'BR');
        $revision->recordAction('created', null, $user, $data['reason']);
        if ($request->hasFile('document')) {
            FinancialDocument::storeFor($revision, $request->file('document'), 'approval', $user);
        }
        Audit::log('budget.revision_created', $revision, ['entity_label' => $revision->reference_no, 'description' => "{$revision->reference_no}: {$data['direction']} PKR " . number_format($data['amount']) . " to {$budget->reference_no}"]);

        return redirect()->route('budget.budgets.show', $budget)->with('status', "Revision {$revision->reference_no} recorded and awaiting approval.");
    }

    protected function rules(): array
    {
        return [
            'fy_starts_on' => ['required', 'date'],
            'fy_ends_on' => ['required', 'date', 'after:fy_starts_on'],
            'original_amount' => ['required', 'numeric', 'min:1', 'max:99999999999999'],
            'funding_source' => ['required', Rule::in(array_keys(config('budget.funding_sources')))],
            'budget_head' => ['required', Rule::in(array_keys(config('budget.budget_heads')))],
            'approved_on' => ['nullable', 'date', 'before_or_equal:today'],
            'effective_on' => ['nullable', 'date'],
            'approving_authority' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'budget_document' => ['nullable', 'file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
            'supporting' => ['nullable', 'array', 'max:10'],
            'supporting.*' => ['file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ];
    }

    protected function storeFiles(Request $request, Budget $budget): void
    {
        if ($request->hasFile('budget_document')) {
            FinancialDocument::storeFor($budget, $request->file('budget_document'), 'budget', $request->user());
        }
        foreach ($request->file('supporting', []) as $file) {
            FinancialDocument::storeFor($budget, $file, 'supporting', $request->user());
        }
    }
}
