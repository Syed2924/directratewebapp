<?php

namespace App\Http\Controllers\JawanMarkaz;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\FinancialDocument;
use App\Models\JawanMarkazBudget;
use App\Models\JawanMarkazExpense;
use App\Support\Audit;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Expense submission (center staff), review queue and decisions (Directorate officers).
 */
class ExpenseController extends Controller
{
    public function index(Request $request, bool $reviewQueue = false)
    {
        $this->authorize('viewAny', JawanMarkazExpense::class);
        $user = $request->user();
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('q'))) . '%';

        $query = JawanMarkazExpense::visibleTo($user)->with('center', 'submitter')
            ->when($reviewQueue, fn ($q) => $q->whereIn('status', ['submitted', 'under_review']))
            ->when($request->filled('center'), fn ($q) => $q->where('center_id', (int) $request->input('center')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->input('to')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('expense_number', 'like', $like)
                ->orWhere('supplier_name', 'like', $like)->orWhere('invoice_number', 'like', $like)->orWhere('description', 'like', $like)));

        return view('jawan-markaz.expenses.index', [
            'reviewQueue' => $reviewQueue,
            'expenses' => $query->orderByRaw($reviewQueue ? 'submitted_at asc' : 'id desc')->paginate(20)->withQueryString(),
            'centers' => Center::withinCoverageOf($user)->orderBy('name')->get(),
        ]);
    }

    public function queue(Request $request)
    {
        return $this->index($request, true);
    }

    public function create(Request $request)
    {
        $this->authorize('create', JawanMarkazExpense::class);
        $centers = Center::active()->withinCoverageOf($request->user())->orderBy('name')->get();

        return view('jawan-markaz.expenses.form', [
            'expense' => new JawanMarkazExpense(['expense_date' => now(), 'invoice_date' => now()]),
            'centers' => $centers,
            'reference' => ReferenceNumber::preview(JawanMarkazExpense::class, 'expense_number', 'EXP'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', JawanMarkazExpense::class);
        $user = $request->user();
        $data = $request->validate($this->rules($request, null));
        $budget = $this->budgetFor((int) $data['center_id'], $data['expense_date']);

        $expense = DB::transaction(function () use ($data, $user, $budget) {
            $expense = new JawanMarkazExpense($data);
            $expense->center_id = (int) $data['center_id'];
            $expense->jawan_markaz_budget_id = $budget->id;
            $expense->status = 'draft';
            $expense->created_by = $expense->updated_by = $expense->submitted_by = $user->id;
            ReferenceNumber::saveWith($expense, 'expense_number', 'EXP');
            $expense->recordAction('created', null, $user);

            return $expense;
        });
        $this->storeFiles($request, $expense);
        Audit::log('jawan_markaz.expense_created', $expense, ['entity_label' => $expense->expense_number, 'description' => "{$expense->expense_number}: PKR " . number_format($expense->requested_amount) . " — {$expense->supplier_name} invoice {$expense->invoice_number}"]);

        if ($data['intent'] === 'submit') {
            return $this->doSubmit($request, $expense);
        }

        return $this->afterSave($expense, "Expense {$expense->expense_number} saved as draft.");
    }

    public function show(Request $request, JawanMarkazExpense $expense)
    {
        $this->authorize('view', $expense);
        $expense->load('center.district', 'budget.financialYear', 'approvals.user', 'documents.uploader', 'payments.recorder', 'payments.documents', 'payments.transaction', 'submitter', 'reviewer');

        return view('jawan-markaz.expenses.show', [
            'expense' => $expense,
            'figures' => $expense->budget->figures(),
            'duplicates' => $expense->possibleDuplicates(),
        ]);
    }

    public function edit(Request $request, JawanMarkazExpense $expense)
    {
        $this->authorize('update', $expense);
        $expense->load('documents', 'approvals.user');

        return view('jawan-markaz.expenses.form', [
            'expense' => $expense,
            'centers' => Center::whereKey($expense->center_id)->get(),
            'reference' => $expense->expense_number,
        ]);
    }

    public function update(Request $request, JawanMarkazExpense $expense)
    {
        $this->authorize('update', $expense);
        $data = $request->validate($this->rules($request, $expense));
        $budget = $this->budgetFor($expense->center_id, $data['expense_date']);

        $before = array_map('strval', $expense->only($expense->getFillable()));
        $expense->fill($data);
        $expense->jawan_markaz_budget_id = $budget->id;
        $expense->recordAction('updated', null, $request->user());
        $this->storeFiles($request, $expense);
        [$old, $new] = Audit::diff($before, array_map('strval', $expense->only($expense->getFillable())));
        Audit::log('jawan_markaz.expense_updated', $expense, ['entity_label' => $expense->expense_number, 'before' => $old, 'after' => $new]);

        if ($data['intent'] === 'submit') {
            return $this->doSubmit($request, $expense);
        }

        return $this->afterSave($expense, 'Expense saved.');
    }

    public function submit(Request $request, JawanMarkazExpense $expense)
    {
        return $this->doSubmit($request, $expense);
    }

    /** Send a draft or corrected expense to the Directorate. An invoice must be attached. */
    protected function doSubmit(Request $request, JawanMarkazExpense $expense)
    {
        $this->authorize('submit', $expense);
        if (! $expense->documents()->where('kind', 'invoice')->exists()) {
            throw ValidationException::withMessages(['invoice' => 'Attach the invoice before submitting.']);
        }
        $resubmit = $expense->status === 'returned';
        DB::transaction(function () use ($expense, $request, $resubmit) {
            $expense->submitted_by = $request->user()->id;
            $expense->submitted_at = now();
            $expense->recordAction($resubmit ? 'resubmitted' : 'submitted', 'submitted', $request->user());
        });
        Audit::log('jawan_markaz.expense_submitted', $expense, ['entity_label' => $expense->expense_number]);

        return $this->afterSave($expense, "Expense {$expense->expense_number} " . ($resubmit ? 'resubmitted' : 'submitted') . ' to the Directorate.');
    }

    public function startReview(Request $request, JawanMarkazExpense $expense)
    {
        $this->authorize('startReview', $expense);
        $expense->recordAction('under_review', 'under_review', $request->user());

        return redirect()->route('jawan-markaz.expenses.show', $expense)->with('status', 'Marked as under review.');
    }

    /** Approve (authorizes payment, does not pay), reject with reason, or return for correction. */
    public function decide(Request $request, JawanMarkazExpense $expense)
    {
        $this->authorize('decide', $expense);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return', 'reject'])],
            'approved_amount' => ['required_if:decision,approve', 'nullable', 'numeric', 'min:0.01', 'max:' . (float) $expense->requested_amount],
            'remarks' => ['required_if:decision,return,reject', 'nullable', 'string', 'max:2000'],
        ], [
            'remarks.required_if' => 'A reason is required when rejecting or returning an expense.',
            'approved_amount.max' => 'The approved amount cannot exceed the requested PKR ' . number_format($expense->requested_amount) . '.',
        ]);
        $user = $request->user();
        $to = ['approve' => 'approved', 'return' => 'returned', 'reject' => 'rejected'][$data['decision']];

        DB::transaction(function () use ($expense, $data, $user, $to) {
            if ($to === 'approved') {
                // Lock the center budget so two approvals cannot together exceed it.
                $budget = JawanMarkazBudget::whereKey($expense->jawan_markaz_budget_id)->lockForUpdate()->first();
                if ($budget->status !== 'approved') {
                    throw ValidationException::withMessages(['decision' => 'The center budget for this year is not approved yet.']);
                }
                $available = $budget->available();
                if ((float) $data['approved_amount'] > $available) {
                    throw ValidationException::withMessages(['approved_amount' => 'Only PKR ' . number_format($available) . ' of the center budget is still available.']);
                }
                $expense->approved_amount = $data['approved_amount'];
            }
            $expense->reviewed_by = $user->id;
            $expense->decided_at = now();
            $expense->recordAction($to, $to, $user, $data['remarks'] ?? null);
        });
        Audit::log("jawan_markaz.expense_{$to}", $expense, ['entity_label' => $expense->expense_number,
            'description' => "{$expense->expense_number}: " . $expense->statusLabel() . ($to === 'approved' ? ' PKR ' . number_format($expense->approved_amount) : ''),
            'reason' => $data['remarks'] ?? null]);

        return redirect()->route('jawan-markaz.expenses.show', $expense)->with('status', match ($to) {
            'approved' => 'Expense approved. It now appears in the payment queue.',
            'returned' => 'Expense returned to the center for correction.',
            'rejected' => 'Expense rejected.',
        });
    }

    public function storeDocuments(Request $request, JawanMarkazExpense $expense)
    {
        $this->authorize('update', $expense);
        $request->validate(['kind' => ['required', Rule::in(['invoice', 'supporting'])]] + $this->fileRules('files', true));
        foreach ($request->file('files') as $file) {
            FinancialDocument::storeFor($expense, $file, $request->input('kind'), $request->user());
        }

        return redirect()->route('jawan-markaz.expenses.show', $expense)->with('status', 'Documents uploaded.');
    }

    public function destroyDocument(Request $request, JawanMarkazExpense $expense, FinancialDocument $document)
    {
        $this->authorize('update', $expense);
        abort_unless($document->documentable_type === JawanMarkazExpense::class && $document->documentable_id === $expense->id && $document->uploaded_by === $request->user()->id, 403);
        $document->delete();
        Audit::log('jawan_markaz.document_removed', $expense, ['entity_label' => $expense->expense_number, 'description' => "Removed \"{$document->original_name}\""]);

        return back()->with('status', 'Document removed.');
    }

    /** Flag (not block) possible duplicate invoices after saving. */
    protected function afterSave(JawanMarkazExpense $expense, string $message)
    {
        $redirect = redirect()->route('jawan-markaz.expenses.show', $expense)->with('status', $message);
        $dupes = $expense->possibleDuplicates();

        return $dupes->isEmpty() ? $redirect : $redirect->with('status_error',
            "Possible duplicate invoice: {$expense->supplier_name} / {$expense->invoice_number} also appears on " . $dupes->pluck('expense_number')->implode(', ') . '. Please check before it is approved.');
    }

    protected function budgetFor(int $centerId, $date): JawanMarkazBudget
    {
        $budget = JawanMarkazBudget::where('center_id', $centerId)
            ->whereHas('financialYear', fn ($q) => $q->where('starts_on', '<=', $date)->where('ends_on', '>=', $date))
            ->whereIn('status', ['approved', 'submitted', 'draft'])->first();
        if (! $budget) {
            throw ValidationException::withMessages(['expense_date' => 'This center has no budget for the financial year of that date.']);
        }

        return $budget;
    }

    protected function rules(Request $request, ?JawanMarkazExpense $existing): array
    {
        $user = $request->user();
        $invoiceRequired = $request->input('intent') === 'submit' && ! ($existing && $existing->documents()->where('kind', 'invoice')->exists());

        return [
            'center_id' => [$existing ? 'nullable' : 'required', 'integer', function ($a, $v, $fail) use ($user, $existing) {
                if (! $existing && ! $user->scopeCoverage()->covers('center', $v)) {
                    $fail('You can only submit expenses for your assigned center.');
                }
            }],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in(array_keys(config('jawan_markaz.expense_categories')))],
            'description' => ['required', 'string', 'max:500'],
            'supplier_name' => ['required', 'string', 'max:150'],
            'invoice_number' => ['required', 'string', 'max:60'],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'requested_amount' => ['required', 'numeric', 'min:1', 'max:99999999999'],
            'invoice_file' => [$invoiceRequired ? 'required' : 'nullable', 'file', 'max:' . config('activity.upload_max_kb'), 'mimes:pdf,jpg,jpeg,png'],
            'intent' => ['required', Rule::in(['draft', 'submit'])],
        ] + $this->fileRules('supporting', false);
    }

    protected function fileRules(string $field, bool $required): array
    {
        return [
            $field => [$required ? 'required' : 'nullable', 'array', 'max:10'],
            "{$field}.*" => ['file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ];
    }

    protected function storeFiles(Request $request, JawanMarkazExpense $expense): void
    {
        if ($request->hasFile('invoice_file')) {
            FinancialDocument::storeFor($expense, $request->file('invoice_file'), 'invoice', $request->user());
        }
        foreach ($request->file('supporting', []) as $file) {
            FinancialDocument::storeFor($expense, $file, 'supporting', $request->user());
        }
    }
}
