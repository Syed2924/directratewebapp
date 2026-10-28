<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetTransfer;
use App\Models\FinancialDocument;
use App\Models\FinancialYear;
use App\Models\Office;
use App\Support\Audit;
use App\Support\BudgetSummary;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Form B — District / office allocations out of the approved Directorate budget,
 * and authorized transfers between allocations.
 */
class AllocationController extends Controller
{
    protected const MESSAGES = ['budget_head.unique' => 'This office already has an allocation for that budget head in this financial year.'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', BudgetAllocation::class);
        $fy = FinancialYear::fromRequest($request);

        return view('budget.allocations.index', [
            'fy' => $fy,
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'summary' => BudgetSummary::for($fy, $request->user()),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', BudgetAllocation::class);
        $allocation = new BudgetAllocation(['allocation_date' => now(), 'financial_year_id' => FinancialYear::current()?->id]);

        return view('budget.allocations.form', $this->formData($request, $allocation) + [
            'reference' => ReferenceNumber::preview(BudgetAllocation::class, 'reference_no', 'AL'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', BudgetAllocation::class);
        $data = $request->validate($this->rules($request) + ['intent' => ['required', Rule::in(['draft', 'submit'])]], self::MESSAGES);
        $user = $request->user();

        $allocation = DB::transaction(function () use ($data, $user) {
            $allocation = new BudgetAllocation($data);
            $allocation->status = 'draft';
            $allocation->created_by = $allocation->updated_by = $allocation->submitted_by = $user->id;
            ReferenceNumber::saveWith($allocation, 'reference_no', 'AL');
            $allocation->recordAction('created', null, $user);
            if ($data['intent'] === 'submit') {
                $allocation->recordAction('submitted', 'submitted', $user);
            }

            return $allocation;
        });
        $this->storeFiles($request, $allocation);
        Audit::log('budget.allocation_created', $allocation, ['entity_label' => $allocation->reference_no, 'description' => "{$allocation->reference_no}: {$allocation->label()} planned PKR " . number_format($allocation->planned_amount)]);

        return redirect()->route('budget.allocations.show', $allocation)->with('status', "Allocation {$allocation->reference_no} " . ($data['intent'] === 'submit' ? 'submitted for approval.' : 'saved as draft.'));
    }

    public function show(Request $request, BudgetAllocation $allocation)
    {
        $this->authorize('view', $allocation);
        $allocation->load('financialYear', 'office.district', 'approvals.user', 'documents.uploader', 'submitter',
            'transfersIn.from.office', 'transfersOut.to.office', 'commitments.proposal', 'transactions');
        $allocation->loadLedger();

        $transferTargets = BudgetAllocation::visibleTo($request->user())
            ->where('financial_year_id', $allocation->financial_year_id)->where('status', 'approved')
            ->whereKeyNot($allocation->id)->with('office')->get();

        return view('budget.allocations.show', [
            'allocation' => $allocation,
            'transferTargets' => $transferTargets,
            'transfers' => $allocation->transfersOut->concat($allocation->transfersIn)->sortByDesc('id'),
        ]);
    }

    public function edit(Request $request, BudgetAllocation $allocation)
    {
        $this->authorize('update', $allocation);
        $allocation->load('documents');

        return view('budget.allocations.form', $this->formData($request, $allocation) + ['reference' => $allocation->reference_no]);
    }

    public function update(Request $request, BudgetAllocation $allocation)
    {
        $this->authorize('update', $allocation);
        $data = $request->validate($this->rules($request, $allocation) + ['intent' => ['required', Rule::in(['draft', 'submit'])]], self::MESSAGES);
        $user = $request->user();

        DB::transaction(function () use ($allocation, $data, $user) {
            $allocation->fill($data);
            $allocation->recordAction('updated', null, $user);
            if ($data['intent'] === 'submit') {
                $allocation->submitted_by = $user->id;
                $allocation->recordAction('submitted', 'submitted', $user);
            }
        });
        $this->storeFiles($request, $allocation);
        Audit::log('budget.allocation_updated', $allocation, ['entity_label' => $allocation->reference_no]);

        return redirect()->route('budget.allocations.show', $allocation)->with('status', $data['intent'] === 'submit' ? 'Allocation submitted for approval.' : 'Allocation saved.');
    }

    /** Request a transfer from this allocation to another; takes effect once approved. */
    public function storeTransfer(Request $request, BudgetAllocation $allocation)
    {
        $this->authorize('create', BudgetAllocation::class);
        $this->authorize('view', $allocation);
        abort_unless($allocation->status === 'approved', 403, 'Transfers can only be made from an approved allocation.');
        $user = $request->user();
        $targets = BudgetAllocation::visibleTo($user)->where('financial_year_id', $allocation->financial_year_id)
            ->where('status', 'approved')->whereKeyNot($allocation->id)->pluck('id')->all();
        $data = $request->validate([
            'to_allocation_id' => ['required', Rule::in($targets)],
            'amount' => ['required', 'numeric', 'min:1', 'max:' . max(1, floor($allocation->loadLedger()->available()))],
            'effective_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
        ], ['amount.max' => 'Only PKR ' . number_format(max(0, $allocation->available())) . ' is available in this allocation.','to_allocation_id.in' => 'Choose an approved allocation of the same financial year within your scope.']);

        $transfer = new BudgetTransfer($data);
        $transfer->from_allocation_id = $allocation->id;
        $transfer->to_allocation_id = (int) $data['to_allocation_id'];
        $transfer->office_id = $allocation->office_id;
        $transfer->status = 'pending';
        $transfer->created_by = $transfer->updated_by = $transfer->submitted_by = $user->id;
        ReferenceNumber::saveWith($transfer, 'reference_no', 'TR');
        $transfer->recordAction('created', null, $user, $data['reason']);
        Audit::log('budget.transfer_created', $transfer, ['entity_label' => $transfer->reference_no, 'description' => "{$transfer->reference_no}: PKR " . number_format($data['amount']) . " from {$allocation->reference_no}"]);

        return redirect()->route('budget.allocations.show', $allocation)->with('status', "Transfer {$transfer->reference_no} recorded and awaiting approval.");
    }

    protected function formData(Request $request, BudgetAllocation $allocation): array
    {
        return [
            'allocation' => $allocation,
            'years' => FinancialYear::whereHas('budget', fn ($b) => $b->where('status', 'approved'))->orderByDesc('starts_on')->get(),
            'offices' => Office::active()->withinCoverageOf($request->user())->with('district')->orderBy('name')->get(),
        ];
    }

    protected function rules(Request $request, ?BudgetAllocation $existing = null): array
    {
        $user = $request->user();
        $file = ['file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))];

        return [
            'financial_year_id' => ['required', Rule::exists('budgets', 'financial_year_id')->where('status', 'approved')],
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')->where('is_active', true), function ($a, $v, $fail) use ($user) {
                if (! $user->scopeCoverage()->covers('office', $v)) {
                    $fail('That office is outside your scope.');
                }
            }],
            'budget_head' => ['required', Rule::in(array_keys(config('budget.budget_heads'))),
                Rule::unique('budget_allocations')->where(fn ($q) => $q->where('financial_year_id', $request->input('financial_year_id'))->where('office_id', $request->input('office_id')))->ignore($existing?->id)],
            'allocation_date' => ['required', 'date'],
            'planned_amount' => ['required', 'numeric', 'min:1', 'max:99999999999999'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => $file,
        ];
    }

    protected function storeFiles(Request $request, BudgetAllocation $allocation): void
    {
        foreach ($request->file('documents', []) as $file) {
            FinancialDocument::storeFor($allocation, $file, 'approval', $request->user());
        }
    }
}
