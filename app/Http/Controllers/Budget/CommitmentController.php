<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\BudgetAllocation;
use App\Models\BudgetCommitment;
use App\Models\FinancialDocument;
use App\Models\FinancialYear;
use App\Support\Audit;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Commitments: funds reserved for authorized obligations. Activity approvals
 * create them automatically; other obligations are recorded here.
 */
class CommitmentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', BudgetCommitment::class);
        $fy = FinancialYear::fromRequest($request);

        $commitments = BudgetCommitment::visibleTo($request->user())
            ->with('office', 'allocation', 'proposal')
            ->withSum(['transactions as spent_sum' => fn ($t) => $t->where('status', '!=', 'rejected')], 'amount')
            ->when($fy, fn ($q) => $q->where('financial_year_id', $fy->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('committed_on')->latest('id')
            ->paginate(20)->withQueryString();

        return view('budget.commitments.index', ['fy' => $fy, 'years' => FinancialYear::orderByDesc('starts_on')->get(), 'commitments' => $commitments]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', BudgetCommitment::class);

        return view('budget.commitments.form', [
            'allocations' => $this->fundable($request),
            'reference' => ReferenceNumber::preview(BudgetCommitment::class, 'reference_no', 'CM'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', BudgetCommitment::class);
        $user = $request->user();
        $data = $request->validate([
            'allocation_id' => ['required', Rule::in($this->fundable($request)->pluck('id')->all())],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999999999'],
            'committed_on' => ['required', 'date', 'before_or_equal:today'],
            'authority_reference' => ['required', 'string', 'max:100'],
            'document' => ['nullable', 'file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ], ['authority_reference.required' => 'Enter the sanction / approval reference that authorizes this obligation.']);

        $commitment = DB::transaction(function () use ($data, $user) {
            $allocation = BudgetAllocation::findOrFail($data['allocation_id']);
            FinancialYear::whereKey($allocation->financial_year_id)->lockForUpdate()->first();
            if ((float) $data['amount'] > $allocation->loadLedger()->available()) {
                throw ValidationException::withMessages(['amount' => 'Only PKR ' . number_format($allocation->available()) . ' is available in this allocation.']);
            }

            return self::commit($allocation, $data, $user);
        });
        if ($request->hasFile('document')) {
            FinancialDocument::storeFor($commitment, $request->file('document'), 'approval', $user);
        }

        return redirect()->route('budget.commitments.show', $commitment)->with('status', "Commitment {$commitment->reference_no} recorded.");
    }

    /** Create an open commitment (caller has already checked availability under a lock). */
    public static function commit(BudgetAllocation $allocation, array $data, $user, ?int $proposalId = null): BudgetCommitment
    {
        $commitment = new BudgetCommitment($data);
        $commitment->allocation_id = $allocation->id;
        $commitment->financial_year_id = $allocation->financial_year_id;
        $commitment->office_id = $allocation->office_id;
        $commitment->proposal_id = $proposalId;
        $commitment->status = 'open';
        $commitment->created_by = $commitment->updated_by = $commitment->submitted_by = $user->id;
        ReferenceNumber::saveWith($commitment, 'reference_no', 'CM');
        $commitment->recordAction('created', null, $user, $data['authority_reference'] ?? null);
        Audit::log('budget.commitment_created', $commitment, ['entity_label' => $commitment->reference_no, 'description' => "{$commitment->reference_no}: PKR " . number_format($commitment->amount) . " on {$allocation->reference_no} — {$commitment->description}"]);

        return $commitment;
    }

    public function show(Request $request, BudgetCommitment $commitment)
    {
        $this->authorize('view', $commitment);
        $commitment->load('allocation.office', 'proposal.activity', 'transactions.submitter', 'approvals.user', 'documents.uploader');

        return view('budget.commitments.show', ['commitment' => $commitment]);
    }

    public function close(Request $request, BudgetCommitment $commitment)
    {
        $this->authorize('close', $commitment);
        $data = $request->validate(['action' => ['required', Rule::in(['closed', 'cancelled'])], 'remarks' => ['required', 'string', 'max:1000']]);
        if ($data['action'] === 'cancelled' && $commitment->spent() > 0) {
            throw ValidationException::withMessages(['action' => 'Expenditure has been booked against this commitment; close it instead of cancelling.']);
        }

        $data['action'] === 'closed'
            ? $commitment->close($request->user(), $data['remarks'])
            : $commitment->recordAction('cancelled', 'cancelled', $request->user(), $data['remarks']);
        Audit::log("budget.commitment_{$data['action']}", $commitment, ['entity_label' => $commitment->reference_no, 'reason' => $data['remarks']]);

        return redirect()->route('budget.commitments.show', $commitment)->with('status', "Commitment {$commitment->reference_no} {$data['action']}.");
    }

    protected function fundable(Request $request)
    {
        $fy = FinancialYear::current();

        return $fy ? BudgetAllocation::visibleTo($request->user())->where('financial_year_id', $fy->id)
            ->where('status', 'approved')->withLedger()->with('office')->get()->sortBy(fn ($a) => $a->label())->values() : collect();
    }
}
