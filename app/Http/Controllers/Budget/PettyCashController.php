<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\FinancialDocument;
use App\Models\Office;
use App\Models\PettyCashFund;
use App\Models\PettyCashVoucher;
use App\Models\User;
use App\Support\Audit;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Form C — petty cash funds, vouchers and reconciliations. The balance is
 * always computed from vouchers; nobody can type a new balance in.
 */
class PettyCashController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', PettyCashFund::class);
        $user = $request->user();
        $funds = PettyCashFund::visibleTo($user)->with('office.district', 'custodian')
            ->with('reconciliations')->get();

        return view('budget.petty-cash.index', [
            'funds' => $funds,
            'offices' => Office::active()->withinCoverageOf($user)->whereDoesntHave('pettyCashFund')->orderBy('name')->get(),
            'custodians' => User::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeFund(Request $request)
    {
        $this->authorize('create', PettyCashFund::class);
        $user = $request->user();
        $data = $request->validate([
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')->where('is_active', true), Rule::unique('petty_cash_funds', 'office_id'),
                function ($a, $v, $fail) use ($user) {
                    if (! $user->scopeCoverage()->covers('office', $v)) {
                        $fail('That office is outside your scope.');
                    }
                }],
            'custodian_id' => ['required', Rule::exists('users', 'id')->where('status', 'active')],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'limit' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'opened_on' => ['required', 'date', 'before_or_equal:today'],
        ], ['office_id.unique' => 'This office already has a petty cash fund.']);

        $fund = new PettyCashFund($data);
        $fund->status = 'active';
        $fund->created_by = $fund->updated_by = $user->id;
        $fund->save();
        Audit::log('budget.petty_fund_created', $fund, ['entity_label' => 'Petty cash — ' . $fund->office->name, 'after' => $data]);

        return redirect()->route('budget.petty-cash.show', $fund)->with('status', 'Petty cash fund opened.');
    }

    public function show(Request $request, PettyCashFund $fund)
    {
        $this->authorize('view', $fund);
        $fund->load('office.district', 'custodian', 'vouchers.submitter', 'vouchers.documents', 'vouchers.approvals.user', 'reconciliations.reconciler');

        return view('budget.petty-cash.show', [
            'fund' => $fund,
            'totals' => $fund->totals(),
            'reference' => ReferenceNumber::preview(PettyCashVoucher::class, 'voucher_no', 'PC'),
        ]);
    }

    public function storeVoucher(Request $request, PettyCashFund $fund)
    {
        $this->authorize('view', $fund);
        $this->authorize('budget.record_payment');
        $user = $request->user();
        $data = $request->validate([
            'type' => ['required', Rule::in(['expense', 'replenishment'])],
            'voucher_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $fund->opened_on->toDateString()],
            'category' => ['required_if:type,expense', 'nullable', Rule::in(array_keys(config('budget.petty_cash_categories')))],
            'description' => ['required', 'string', 'max:255'],
            'payee' => ['required_if:type,expense', 'nullable', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'replenishment_reference' => ['required_if:type,replenishment', 'nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ], [
            'category.required_if' => 'Choose the expense category.',
            'payee.required_if' => 'Enter who was paid.',
            'replenishment_reference.required_if' => 'Enter the cheque / authorization reference for the cash received.',
        ]);

        $voucher = DB::transaction(function () use ($fund, $data, $user) {
            PettyCashFund::whereKey($fund->id)->lockForUpdate()->first();
            if ($data['type'] === 'expense' && (float) $data['amount'] > $fund->balance()) {
                throw ValidationException::withMessages(['amount' => 'Only PKR ' . number_format($fund->balance()) . ' cash is available in this fund.']);
            }
            $voucher = new PettyCashVoucher($data);
            $voucher->fund_id = $fund->id;
            $voucher->office_id = $fund->office_id;
            $voucher->status = 'pending';
            $voucher->created_by = $voucher->updated_by = $voucher->submitted_by = $user->id;
            ReferenceNumber::saveWith($voucher, 'voucher_no', 'PC');
            $voucher->recordAction('created', null, $user, $data['remarks'] ?? null);

            return $voucher;
        });
        if ($request->hasFile('receipt')) {
            FinancialDocument::storeFor($voucher, $request->file('receipt'), 'receipt', $user);
        }
        Audit::log('budget.voucher_recorded', $voucher, ['entity_label' => $voucher->voucher_no, 'description' => "{$voucher->voucher_no}: {$voucher->type} PKR " . number_format($voucher->amount) . " — {$voucher->description}"]);

        return redirect()->route('budget.petty-cash.show', $fund)->with('status', "Voucher {$voucher->voucher_no} recorded and awaiting verification.");
    }

    /** Count the cash and compare it with the book balance. */
    public function storeReconciliation(Request $request, PettyCashFund $fund)
    {
        $this->authorize('view', $fund);
        abort_unless($request->user()->hasPermission('budget.record_payment') || $request->user()->hasPermission('budget.review'), 403);
        $data = $request->validate([
            'reconciled_on' => ['required', 'date', 'before_or_equal:today'],
            'physical_cash' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $book = $fund->balance();
        $difference = round((float) $data['physical_cash'] - $book, 2);
        if ($difference != 0 && blank($data['remarks'] ?? null)) {
            throw ValidationException::withMessages(['remarks' => 'Explain the difference of PKR ' . number_format($difference, 2) . '.']);
        }

        $rec = $fund->reconciliations()->make($data + ['book_balance' => $book, 'difference' => $difference, 'status' => $difference == 0 ? 'balanced' : 'difference']);
        $rec->reconciled_by = $request->user()->id;
        $rec->save();
        Audit::log('budget.petty_reconciled', $fund, ['entity_label' => 'Petty cash — ' . $fund->office->name, 'description' => 'Book PKR ' . number_format($book, 2) . ', counted PKR ' . number_format($data['physical_cash'], 2) . ', difference PKR ' . number_format($difference, 2), 'reason' => $data['remarks'] ?? null]);

        return redirect()->route('budget.petty-cash.show', $fund)->with($difference == 0 ? 'status' : 'status_error', $difference == 0
            ? 'Cash count agrees with the book balance.'
            : 'Reconciliation recorded with a difference of PKR ' . number_format($difference, 2) . '.');
    }
}
