<?php

namespace App\Http\Controllers\JawanMarkaz;

use App\Http\Controllers\Budget\TransactionController;
use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\FinancialDocument;
use App\Models\JawanMarkazExpense;
use App\Models\JawanMarkazPayment;
use App\Support\Audit;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Payments, recorded separately from approval by finance / payment staff.
 * Each payment is also booked once in the central expense register against
 * the center's budget commitment.
 */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('jawan_markaz.view');
        $user = $request->user();

        return view('jawan-markaz.payments.index', [
            'awaiting' => JawanMarkazExpense::visibleTo($user)->whereIn('status', ['approved', 'partially_paid'])
                ->with('center', 'payments', 'reviewer')->orderBy('decided_at')->get(),
            'payments' => JawanMarkazPayment::visibleTo($user)->with('expense', 'center', 'recorder', 'transaction')
                ->when($request->filled('center'), fn ($q) => $q->where('center_id', (int) $request->input('center')))
                ->when($request->filled('method'), fn ($q) => $q->where('method', $request->input('method')))
                ->when($request->filled('from'), fn ($q) => $q->whereDate('payment_date', '>=', $request->input('from')))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('payment_date', '<=', $request->input('to')))
                ->latest('payment_date')->latest('id')->paginate(20)->withQueryString(),
            'centers' => Center::withinCoverageOf($user)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, JawanMarkazExpense $expense)
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
        ], [
            'amount.max' => 'Payment cannot exceed the approved amount still unpaid (PKR ' . number_format($outstanding, 2) . ').',
            'payment_date.after_or_equal' => 'The payment date cannot be before the approval date.',
        ]);

        $payment = DB::transaction(function () use ($expense, $data, $user) {
            $expense = JawanMarkazExpense::whereKey($expense->id)->lockForUpdate()->first();
            if ((float) $data['amount'] > $expense->outstanding()) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds the approved amount still unpaid.']);
            }
            $commitment = $expense->budget->commitment;
            if (! $commitment) {
                throw ValidationException::withMessages(['amount' => 'The center budget has no central funding commitment.']);
            }

            // One expenditure record, in the central register, charged to the center's commitment.
            $txn = TransactionController::book($commitment->allocation, $commitment, [
                'txn_date' => $data['payment_date'],
                'category' => config("jawan_markaz.central_categories.{$expense->category}", 'other'),
                'description' => "{$expense->center->name} — {$expense->expense_number}: {$expense->description}",
                'payee' => $expense->supplier_name,
                'amount' => $data['amount'],
                'payment_reference' => $data['transaction_reference'],
            ], $user);
            // Already reviewed and approved in this module by the Directorate officer.
            $txn->recordAction('verified', 'verified', $expense->reviewer ?? $user,
                "Approved as Jawan Markaz expense {$expense->expense_number}" . ($expense->reviewer ? " by {$expense->reviewer->name}" : '') . '.');

            $payment = new JawanMarkazPayment($data);
            $payment->jawan_markaz_expense_id = $expense->id;
            $payment->center_id = $expense->center_id;
            $payment->financial_transaction_id = $txn->id;
            $payment->recorded_by = $user->id;
            ReferenceNumber::saveWith($payment, 'reference_no', 'PAY');

            $expense->load('payments');
            $to = $expense->outstanding() <= 0.004 ? 'paid' : 'partially_paid';
            $expense->recordAction($to, $to, $user, "{$payment->reference_no}: PKR " . number_format($payment->amount, 2) . " by {$payment->methodLabel()} ({$payment->transaction_reference})");

            return $payment;
        });
        if ($request->hasFile('evidence')) {
            FinancialDocument::storeFor($payment, $request->file('evidence'), 'payment_evidence', $user);
        }
        Audit::log('jawan_markaz.payment_recorded', $payment, ['entity_label' => $payment->reference_no,
            'description' => "{$payment->reference_no}: PKR " . number_format($payment->amount, 2) . " for {$expense->expense_number} ({$payment->methodLabel()} {$payment->transaction_reference})"]);

        return redirect()->route('jawan-markaz.expenses.show', $expense)->with('status', "Payment {$payment->reference_no} recorded.");
    }
}
