<?php

namespace App\Http\Controllers\Hostel;

use App\Http\Controllers\Controller;
use App\Models\FinancialDocument;
use App\Models\Hostel;
use App\Models\HostelBudget;
use App\Models\HostelExpense;
use App\Models\HostelPayment;
use App\Support\Audit;
use App\Support\CentralPayment;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Hostel expenditure: recorded by hostel staff, approved by an officer, paid by finance.
 */
class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', HostelExpense::class);
        $user = $request->user();

        return view('hostel.expenses.index', [
            'expenses' => HostelExpense::visibleTo($user)->with('hostel', 'submitter', 'payments')
                ->when($request->filled('hostel'), fn ($q) => $q->where('hostel_id', (int) $request->input('hostel')))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
                ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
                ->latest('expense_date')->latest('id')->paginate(20)->withQueryString(),
            'hostels' => Hostel::withinCoverageOf($user)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', HostelExpense::class);

        return view('hostel.expenses.form', [
            'hostels' => Hostel::active()->withinCoverageOf($request->user())->orderBy('name')->get(),
            'reference' => ReferenceNumber::preview(HostelExpense::class, 'expense_no', 'HEXP'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', HostelExpense::class);
        $user = $request->user();
        $data = $request->validate([
            'hostel_id' => ['required', 'integer', function ($a, $v, $fail) use ($user) {
                if (! $user->scopeCoverage()->covers('hostel', $v)) {
                    $fail('You can only record expenses for your hostel.');
                }
            }],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in(array_keys(config('hostel.expense_categories')))],
            'description' => ['required', 'string', 'max:500'],
            'supplier_name' => ['required', 'string', 'max:150'],
            'invoice_number' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999999'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'invoice_file' => ['required', 'file', 'max:' . config('activity.upload_max_kb'), 'mimes:pdf,jpg,jpeg,png'],
        ], ['invoice_file.required' => 'Attach the invoice or receipt.']);

        $budget = HostelBudget::where('hostel_id', $data['hostel_id'])->where('status', 'approved')
            ->whereHas('financialYear', fn ($q) => $q->where('starts_on', '<=', $data['expense_date'])->where('ends_on', '>=', $data['expense_date']))->first();
        if (! $budget) {
            throw ValidationException::withMessages(['expense_date' => 'This hostel has no approved budget for the financial year of that date.']);
        }

        $expense = DB::transaction(function () use ($data, $user, $budget) {
            $e = new HostelExpense($data);
            $e->hostel_id = (int) $data['hostel_id'];
            $e->hostel_budget_id = $budget->id;
            $e->status = 'pending';
            $e->created_by = $e->updated_by = $e->submitted_by = $user->id;
            ReferenceNumber::saveWith($e, 'expense_no', 'HEXP');
            $e->recordAction('created', null, $user);

            return $e;
        });
        FinancialDocument::storeFor($expense, $request->file('invoice_file'), 'invoice', $user);
        Audit::log('hostel.expense_recorded', $expense, ['entity_label' => $expense->expense_no, 'description' => "{$expense->expense_no}: PKR " . number_format($expense->amount) . " — {$expense->supplier_name}"]);

        $redirect = redirect()->route('hostel.expenses.show', $expense)->with('status', "Expense {$expense->expense_no} recorded and awaiting approval.");
        $dupes = $expense->possibleDuplicates();

        return $dupes->isEmpty() ? $redirect : $redirect->with('status_error', "Possible duplicate invoice: also on " . $dupes->pluck('expense_no')->implode(', ') . '.');
    }

    public function show(Request $request, HostelExpense $expense)
    {
        $this->authorize('view', $expense);
        $expense->load('hostel', 'budget.financialYear', 'approvals.user', 'documents.uploader', 'payments.recorder', 'payments.transaction', 'payments.documents', 'submitter', 'reviewer');

        return view('hostel.expenses.show', ['expense' => $expense, 'figures' => $expense->budget->figures(), 'duplicates' => $expense->possibleDuplicates()]);
    }

    public function decide(Request $request, HostelExpense $expense)
    {
        $this->authorize('decide', $expense);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'remarks' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ], ['remarks.required_if' => 'A reason is required when rejecting an expense.']);
        $to = $data['decision'] === 'approve' ? 'approved' : 'rejected';

        DB::transaction(function () use ($expense, $data, $request, $to) {
            if ($to === 'approved') {
                $budget = HostelBudget::whereKey($expense->hostel_budget_id)->lockForUpdate()->first();
                $available = $budget->figures()['available'];
                if ((float) $expense->amount > $available) {
                    throw ValidationException::withMessages(['decision' => 'Only PKR ' . number_format($available) . ' of the hostel budget is available to commit.']);
                }
            }
            $expense->reviewed_by = $request->user()->id;
            $expense->decided_at = now();
            $expense->recordAction($to, $to, $request->user(), $data['remarks'] ?? null);
        });
        Audit::log("hostel.expense_{$to}", $expense, ['entity_label' => $expense->expense_no, 'reason' => $data['remarks'] ?? null]);

        return redirect()->route('hostel.expenses.show', $expense)->with('status', $to === 'approved' ? 'Expense approved. It can now be paid.' : 'Expense rejected.');
    }

    public function pay(Request $request, HostelExpense $expense)
    {
        $this->authorize('pay', $expense);
        $user = $request->user();
        $outstanding = $expense->outstanding();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:' . $outstanding],
            'payment_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $expense->decided_at->toDateString()],
            'method' => ['required', Rule::in(array_keys(config('jawan_markaz.payment_methods')))],
            'transaction_reference' => ['required', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['nullable', 'file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ], ['amount.max' => 'Payment cannot exceed the approved amount still unpaid (PKR ' . number_format($outstanding, 2) . ').']);

        $payment = DB::transaction(function () use ($expense, $data, $user) {
            $expense = HostelExpense::whereKey($expense->id)->lockForUpdate()->first();
            if ((float) $data['amount'] > $expense->outstanding()) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds the approved amount still unpaid.']);
            }
            $commitment = $expense->budget->commitment ?? throw ValidationException::withMessages(['amount' => 'The hostel budget has no central funding commitment.']);
            $txn = CentralPayment::book($commitment, [
                'txn_date' => $data['payment_date'],
                'category' => config("hostel.central_categories.{$expense->category}", 'other'),
                'description' => "{$expense->hostel->name} — {$expense->expense_no}: {$expense->description}",
                'payee' => $expense->supplier_name,
                'amount' => $data['amount'],
                'payment_reference' => $data['transaction_reference'],
            ], $user, $expense->reviewer, "Approved as hostel expense {$expense->expense_no}" . ($expense->reviewer ? " by {$expense->reviewer->name}" : '') . '.');

            $p = new HostelPayment($data);
            $p->hostel_expense_id = $expense->id;
            $p->hostel_id = $expense->hostel_id;
            $p->financial_transaction_id = $txn->id;
            $p->recorded_by = $user->id;
            ReferenceNumber::saveWith($p, 'reference_no', 'HPAY');
            $expense->load('payments');
            $to = $expense->outstanding() <= 0.004 ? 'paid' : 'partially_paid';
            $expense->recordAction($to, $to, $user, "{$p->reference_no}: PKR " . number_format($p->amount, 2) . " ({$p->transaction_reference})");

            return $p;
        });
        if ($request->hasFile('evidence')) {
            FinancialDocument::storeFor($payment, $request->file('evidence'), 'payment_evidence', $user);
        }
        Audit::log('hostel.payment_recorded', $payment, ['entity_label' => $payment->reference_no, 'description' => "{$payment->reference_no}: PKR " . number_format($payment->amount, 2) . " for {$expense->expense_no}"]);

        return redirect()->route('hostel.expenses.show', $expense)->with('status', "Payment {$payment->reference_no} recorded.");
    }
}
