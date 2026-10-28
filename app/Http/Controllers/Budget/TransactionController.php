<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Controller;
use App\Models\BudgetAllocation;
use App\Models\BudgetCommitment;
use App\Models\FinancialDocument;
use App\Models\FinancialTransaction;
use App\Models\FinancialYear;
use App\Models\Office;
use App\Models\User;
use App\Support\Audit;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Expense register: actual expenditure, receipts and financial verification.
 */
class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', FinancialTransaction::class);
        $fy = FinancialYear::fromRequest($request);
        $query = $this->filtered($request, $fy);

        return view('budget.transactions.index', [
            'fy' => $fy,
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'offices' => Office::withinCoverageOf($request->user())->orderBy('name')->get(),
            'transactions' => (clone $query)->with('office', 'proposal', 'submitter')->latest('txn_date')->latest('id')->paginate(20)->withQueryString(),
            'totals' => (clone $query)->selectRaw('status, SUM(amount) as total, COUNT(*) as n')->groupBy('status')->get()->keyBy('status'),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('budget.record_payment');

        return view('budget.transactions.form', [
            'allocations' => $this->allocations($request),
            'commitments' => $this->commitments($request),
            'reference' => ReferenceNumber::preview(FinancialTransaction::class, 'reference_no', 'EX'),
            'selectedCommitment' => $request->integer('commitment') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('budget.record_payment');
        $user = $request->user();
        $data = $request->validate([
            'commitment_id' => ['nullable', Rule::in($this->commitments($request)->pluck('id')->all())],
            'allocation_id' => ['required_without:commitment_id', 'nullable', Rule::in($this->allocations($request)->pluck('id')->all())],
            'txn_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in(array_keys(config('budget.expense_categories')))],
            'description' => ['required', 'string', 'max:255'],
            'payee' => ['nullable', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999999999'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'receipts' => ['nullable', 'array', 'max:10'],
            'receipts.*' => ['file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ], ['allocation_id.required_without' => 'Choose the allocation (or commitment) this expense is charged to.']);

        $commitment = filled($data['commitment_id'] ?? null) ? BudgetCommitment::find($data['commitment_id']) : null;
        $allocation = $commitment ? $commitment->allocation : BudgetAllocation::findOrFail($data['allocation_id']);
        $transaction = self::book($allocation, $commitment, $data, $user);

        foreach ($request->file('receipts', []) as $file) {
            FinancialDocument::storeFor($transaction, $file, 'receipt', $user);
        }

        return redirect()->route('budget.transactions.show', $transaction)->with('status', "Expense {$transaction->reference_no} recorded and awaiting verification.");
    }

    /**
     * Record an expense against an allocation (and optionally a commitment / activity),
     * refusing anything that would overspend. Shared with the Activity module.
     */
    public static function book(BudgetAllocation $allocation, ?BudgetCommitment $commitment, array $data, User $user, ?int $proposalId = null): FinancialTransaction
    {
        return DB::transaction(function () use ($allocation, $commitment, $data, $user, $proposalId) {
            $fy = FinancialYear::whereKey($allocation->financial_year_id)->lockForUpdate()->first();
            if (! $fy->contains(\Illuminate\Support\Carbon::parse($data['txn_date']))) {
                throw ValidationException::withMessages(['txn_date' => "The date must fall within financial year {$fy->label}."]);
            }
            $headroom = FinancialTransaction::headroom($allocation, $commitment);
            if ((float) $data['amount'] > $headroom) {
                throw ValidationException::withMessages(['amount' => 'This would overspend: only PKR ' . number_format(max(0, $headroom)) . ' is available to charge here.']);
            }

            $txn = new FinancialTransaction($data);
            $txn->allocation_id = $allocation->id;
            $txn->financial_year_id = $allocation->financial_year_id;
            $txn->office_id = $allocation->office_id;
            $txn->commitment_id = $commitment?->id;
            $txn->proposal_id = $proposalId ?? $commitment?->proposal_id;
            $txn->status = 'pending';
            $txn->created_by = $txn->updated_by = $txn->submitted_by = $user->id;
            ReferenceNumber::saveWith($txn, 'reference_no', 'EX');
            $txn->recordAction('created', null, $user);
            Audit::log('budget.transaction_recorded', $txn, ['entity_label' => $txn->reference_no, 'description' => "{$txn->reference_no}: PKR " . number_format($txn->amount) . " — {$txn->description}"]);

            return $txn;
        });
    }

    public function show(Request $request, FinancialTransaction $transaction)
    {
        $this->authorize('view', $transaction);
        $transaction->load('allocation.office', 'commitment', 'proposal.activity', 'approvals.user', 'documents.uploader', 'submitter');

        return view('budget.transactions.show', ['transaction' => $transaction]);
    }

    public function export(Request $request)
    {
        $this->authorize('export', FinancialTransaction::class);
        $fy = FinancialYear::fromRequest($request);
        $query = $this->filtered($request, $fy)->with('office', 'proposal', 'allocation')->latest('txn_date');
        Audit::log('budget.register_exported', null, ['description' => 'Exported expense register (' . $query->count() . ' rows)']);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Reference', 'Date', 'Office', 'Budget Head', 'Category', 'Description', 'Payee', 'Amount (PKR)', 'Payment Ref', 'Activity', 'Status']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $t) {
                    fputcsv($out, array_map([AuditLogController::class, 'csvSafe'], [
                        $t->reference_no, $t->txn_date->format('Y-m-d'), $t->office->name, $t->allocation->headLabel(), $t->categoryLabel(),
                        $t->description, $t->payee, number_format((float) $t->amount, 2, '.', ''), $t->payment_reference,
                        $t->proposal?->tracking_no, $t->statusLabel(),
                    ]));
                }
            });
            fclose($out);
        }, 'expense-register-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function filtered(Request $request, ?FinancialYear $fy)
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('q'))) . '%';

        return FinancialTransaction::visibleTo($request->user())
            ->when($fy, fn ($q) => $q->where('financial_year_id', $fy->id))
            ->when($request->filled('office'), fn ($q) => $q->where('office_id', (int) $request->input('office')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('reference_no', 'like', $like)
                ->orWhere('description', 'like', $like)->orWhere('payee', 'like', $like)->orWhere('payment_reference', 'like', $like)));
    }

    protected function allocations(Request $request)
    {
        $fy = FinancialYear::current();

        return $fy ? BudgetAllocation::visibleTo($request->user())->where('financial_year_id', $fy->id)->where('status', 'approved')
            ->withLedger()->with('office')->get()->sortBy(fn ($a) => $a->label())->values() : collect();
    }

    protected function commitments(Request $request)
    {
        return BudgetCommitment::visibleTo($request->user())->where('status', 'open')
            ->withSum(['transactions as spent_sum' => fn ($t) => $t->where('status', '!=', 'rejected')], 'amount')
            ->with('office', 'proposal')->orderBy('reference_no')->get();
    }
}
