<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityDocument;
use App\Http\Controllers\Budget\TransactionController;
use App\Models\District;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Execution of approved activities: actual dates and participants, expenses,
 * evidence, completion report submission and verification.
 */
class ActivityController extends Controller
{
    public const TABS = ['details', 'participants', 'expenses', 'evidence', 'report'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', Activity::class);
        $user = $request->user();

        $activities = Activity::visibleTo($user)
            ->with('proposal', 'district')
            ->withSum('expenses', 'amount')
            ->when($request->filled('q'), fn ($q) => $q->whereHas('proposal', fn ($p) => $p->search($request->input('q'))))
            ->when($request->filled('district'), fn ($q) => $q->where('district_id', (int) $request->input('district')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('id')
            ->paginate(15)->withQueryString();

        return view('activity.activities.index', [
            'activities' => $activities,
            'districts' => District::withinCoverageOf($user)->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, Activity $activity)
    {
        $this->authorize('view', $activity);
        $activity->load('proposal.documents', 'proposal.actions.user', 'proposal.commitment.allocation.office', 'district', 'expenseEntries.submitter', 'documents.uploader', 'verifier', 'completionSubmitter');

        $tab = in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'details';

        return view('activity.activities.show', [
            'activity' => $activity,
            'proposal' => $activity->proposal,
            'tab' => $tab,
            'returnNote' => $activity->status === 'returned' ? $activity->proposal->actions->where('action', 'completion_returned')->last() : null,
            'checklist' => $this->completionChecklist($activity),
        ]);
    }

    public function update(Request $request, Activity $activity)
    {
        $this->authorize('update', $activity);
        $data = $request->validate([
            'actual_start' => ['nullable', 'date', 'before_or_equal:today'],
            'actual_end' => ['nullable', 'date', 'after_or_equal:actual_start', 'before_or_equal:today'],
            'location' => ['nullable', 'string', 'max:200'],
            'actual_male' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'actual_female' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        // Each tab posts only its own fields; leave the others untouched.
        $data = array_intersect_key($data, $request->all());
        foreach (['actual_male', 'actual_female'] as $count) {
            if (array_key_exists($count, $data)) {
                $data[$count] = (int) $data[$count];
            }
        }

        $before = array_map('strval', $activity->only(array_keys($data)));
        $activity->fill($data);
        $activity->updated_by = $request->user()->id;
        $activity->save();
        [$old, $new] = Audit::diff($before, array_map('strval', $activity->only(array_keys($data))));
        if ($new) {
            $this->audit('activity.execution_updated', $activity, null, ['before' => $old, 'after' => $new]);
        }

        $next = in_array($request->input('next'), self::TABS, true) ? $request->input('next') : 'details';

        return redirect()->route('activity.activities.show', [$activity, 'tab' => $next])->with('status', 'Saved.');
    }

    public function storeExpense(Request $request, Activity $activity)
    {
        $this->authorize('update', $activity);
        $data = $request->validate([
            'txn_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in(array_keys(config('budget.expense_categories')))],
            'description' => ['required', 'string', 'max:255'],
            'payee' => ['nullable', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999'],
        ]);

        // The expense is booked once, in the Budget module's expense register, against this activity's commitment.
        $commitment = $activity->proposal->commitment;
        if (! $commitment) {
            throw ValidationException::withMessages(['amount' => 'This activity has no budget commitment, so expenses cannot be charged to it. Ask Finance to record one.']);
        }
        $txn = TransactionController::book($commitment->allocation, $commitment, $data, $request->user(), $activity->proposal_id);

        return redirect()->route('activity.activities.show', [$activity, 'tab' => 'expenses'])
            ->with('status', "Expense {$txn->reference_no} recorded in the expense register and awaiting financial verification.");
    }

    public function storeDocuments(Request $request, Activity $activity)
    {
        $this->authorize('update', $activity);
        $request->validate([
            'kind' => ['required', Rule::in(['picture', 'attendance', 'receipt', 'report', 'attachment'])],
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ], ['files.required' => 'Choose at least one file to upload.']);

        foreach ($request->file('files') as $file) {
            ActivityDocument::storeFor($activity, $file, $request->input('kind'), $request->user());
        }
        $this->audit('activity.evidence_uploaded', $activity, null, ['after' => ['kind' => $request->input('kind'), 'files' => count($request->file('files'))]]);

        return redirect()->route('activity.activities.show', [$activity, 'tab' => 'evidence'])->with('status', 'Files uploaded.');
    }

    public function submitCompletion(Request $request, Activity $activity)
    {
        $this->authorize('submitCompletion', $activity);
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']]);

        $missing = collect($this->completionChecklist($activity))->reject()->keys();
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['completion' => $missing->map(fn ($item) => "Missing: {$item}.")->all()]);
        }

        DB::transaction(function () use ($activity, $request, $data) {
            $activity->status = 'completion_submitted';
            $activity->completion_submitted_by = $request->user()->id;
            $activity->completion_submitted_at = now();
            $activity->updated_by = $request->user()->id;
            $activity->save();
            $activity->proposal->transition('completion_submitted', null, $request->user(), ['activity_id' => $activity->id, 'remarks' => $data['remarks'] ?? null]);
        });
        $this->audit('activity.completion_submitted', $activity, $data['remarks'] ?? null);

        return redirect()->route('activity.activities.show', [$activity, 'tab' => 'report'])->with('status', 'Completion report submitted for verification.');
    }

    /** "Verified?" — Yes: finalize the activity. No: return it for correction. */
    public function verify(Request $request, Activity $activity)
    {
        $this->authorize('verify', $activity);
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['verified', 'returned'])],
            'remarks' => ['required_if:outcome,returned', 'nullable', 'string', 'max:2000'],
        ], ['remarks.required_if' => 'Explain what needs to be corrected.']);
        $user = $request->user();

        DB::transaction(function () use ($activity, $user, $data) {
            $activity->status = $data['outcome'] === 'verified' ? 'completed' : 'returned';
            if ($data['outcome'] === 'verified') {
                $activity->verified_by = $user->id;
                $activity->verified_at = now();
            }
            $activity->updated_by = $user->id;
            $activity->save();
            $activity->proposal->transition($data['outcome'] === 'verified' ? 'completed' : 'completion_returned', null, $user, ['activity_id' => $activity->id, 'remarks' => $data['remarks'] ?? null]);

            // Finalized: release whatever the activity's commitment did not spend.
            $commitment = $activity->proposal->commitment;
            if ($data['outcome'] === 'verified' && $commitment && $commitment->status === 'open') {
                $commitment->close($user, "Activity {$activity->proposal->tracking_no} completed and verified.");
            }
        });
        $this->audit($data['outcome'] === 'verified' ? 'activity.completed' : 'activity.completion_returned', $activity, $data['remarks'] ?? null);

        return redirect()->route('activity.activities.show', [$activity, 'tab' => 'report'])->with('status', $data['outcome'] === 'verified'
            ? 'Completion verified. The activity is finalized.'
            : 'Completion report returned for correction.');
    }

    /** Printable final report (use the browser's "Save as PDF"). */
    public function report(Request $request, Activity $activity)
    {
        $this->authorize('view', $activity);
        $activity->load('proposal.actions.user', 'district', 'expenses', 'documents', 'verifier', 'completionSubmitter');

        return view('activity.activities.report', ['activity' => $activity, 'proposal' => $activity->proposal]);
    }

    /** Requirements for submitting the completion report: label => satisfied. */
    protected function completionChecklist(Activity $activity): array
    {
        $docs = $activity->relationLoaded('documents') ? $activity->documents : $activity->documents()->get();

        return [
            'actual start and end dates' => $activity->actual_start && $activity->actual_end,
            'location' => filled($activity->location),
            'actual participants' => $activity->participantsTotal() > 0,
            'activity summary' => filled($activity->summary),
            'at least one picture' => $docs->contains('kind', 'picture'),
        ];
    }

    protected function audit(string $event, Activity $activity, ?string $remarks, array $extra = []): void
    {
        $tracking = $activity->proposal->tracking_no;
        Audit::log($event, $activity, $extra + [
            'entity_label' => $tracking,
            'description' => "{$tracking}: {$activity->proposal->title} — {$activity->statusLabel()}",
            'reason' => $remarks,
        ]);
    }
}
