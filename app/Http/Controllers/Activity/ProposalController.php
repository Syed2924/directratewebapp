<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Models\ActivityDocument;
use App\Models\ActivityProposal;
use App\Models\District;
use App\Support\ActivityOfficers;
use App\Support\Audit;
use App\Support\BudgetSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Proposal receiving (register paper / digital proposals) and proposal management (list, search, view).
 */
class ProposalController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ActivityProposal::class);
        $user = $request->user();

        $proposals = ActivityProposal::visibleTo($user)
            ->with('district', 'assignee')
            ->search($request->input('q'))
            ->when($request->filled('district'), fn ($q) => $q->where('district_id', $request->integer('district')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('received_on', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('received_on', '<=', $request->date('to')))
            ->orderByDesc('received_on')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        $counts = ActivityProposal::visibleTo($user)
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('activity.proposals.index', [
            'proposals' => $proposals,
            'counts' => $counts,
            'districts' => District::withinCoverageOf($user)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', ActivityProposal::class);

        $proposal = new ActivityProposal(['received_on' => now(), 'submission_method' => 'physical']);

        return view('activity.proposals.form', [
            'proposal' => $proposal,
            'trackingPreview' => ActivityProposal::previewTrackingNo(),
            'districts' => District::active()->withinCoverageOf($request->user())->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', ActivityProposal::class);
        $user = $request->user();
        $data = $request->validate($this->rules($request) + ['intent' => ['required', Rule::in(['draft', 'register'])]]);

        $proposal = new ActivityProposal($this->attributes($data));
        $proposal->status = $data['intent'] === 'register' ? 'received' : 'draft';
        $proposal->submitted_by = $proposal->created_by = $proposal->updated_by = $user->id;
        $proposal->saveWithTrackingNo();

        $proposal->actions()->create([
            'action' => $data['intent'] === 'register' ? 'registered' : 'drafted',
            'to_status' => $proposal->status,
            'user_id' => $user->id,
            'action_date' => $proposal->received_on,
        ]);
        $this->storeUploads($request, $proposal);

        Audit::log('activity.proposal_' . ($data['intent'] === 'register' ? 'registered' : 'drafted'), $proposal, [
            'entity_label' => $proposal->tracking_no,
            'description' => "{$proposal->tracking_no}: {$proposal->title} ({$proposal->applicant_name})",
        ]);

        return redirect()->route('activity.proposals.show', $proposal)->with('status', $data['intent'] === 'register'
            ? "Proposal registered with tracking number {$proposal->tracking_no}."
            : "Draft saved as {$proposal->tracking_no}. Register it when the details are complete.");
    }

    public function show(Request $request, ActivityProposal $proposal)
    {
        $this->authorize('view', $proposal);
        $user = $request->user();
        $proposal->load('district', 'assignee', 'submitter', 'documents.uploader', 'actions.user', 'actions.assignee', 'activity', 'commitment.allocation.office');

        $officers = ($user->can('checkCompleteness', $proposal) || $user->can('assign', $proposal))
            ? ActivityOfficers::reviewersFor($proposal)
            : collect();

        $funding = $proposal->status === 'recommended' && $user->can('decide', $proposal) && $proposal->estimated_budget > 0
            ? BudgetSummary::fundingOptions($proposal->district_id, (float) $proposal->estimated_budget, $proposal->budgetHead())
            : null;

        return view('activity.proposals.show', [
            'proposal' => $proposal,
            'officers' => $officers,
            'funding' => $funding,
            'recommendation' => $proposal->actions->where('action', 'recommended')->last(),
        ]);
    }

    public function edit(Request $request, ActivityProposal $proposal)
    {
        $this->authorize('update', $proposal);
        $proposal->load('documents');

        return view('activity.proposals.form', [
            'proposal' => $proposal,
            'trackingPreview' => $proposal->tracking_no,
            'districts' => District::active()->withinCoverageOf($request->user())->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ActivityProposal $proposal)
    {
        $this->authorize('update', $proposal);
        $user = $request->user();
        $data = $request->validate($this->rules($request) + ['intent' => ['required', Rule::in(['draft', 'register', 'save'])]]);

        $before = $proposal->only(array_keys($this->attributes($data)));
        $proposal->fill($this->attributes($data));
        $changed = $proposal->isDirty();

        DB::transaction(function () use ($proposal, $user, $data, $changed) {
            if ($data['intent'] === 'register' && $proposal->status === 'draft') {
                $proposal->transition('registered', 'received', $user);
            } elseif ($changed) {
                $proposal->transition('updated', null, $user);
            }
        });
        $uploaded = $this->storeUploads($request, $proposal);

        if ($changed || $uploaded) {
            [$old, $new] = Audit::diff(array_map('strval', $before), array_map('strval', $proposal->only(array_keys($before))));
            Audit::log('activity.proposal_updated', $proposal, ['entity_label' => $proposal->tracking_no, 'before' => $old, 'after' => $new + ($uploaded ? ['documents_added' => $uploaded] : [])]);
        }

        return redirect()->route('activity.proposals.show', $proposal)->with('status', $data['intent'] === 'register'
            ? "Proposal {$proposal->tracking_no} registered."
            : 'Proposal details saved.');
    }

    public function destroy(Request $request, ActivityProposal $proposal)
    {
        $this->authorize('update', $proposal);
        abort_unless($proposal->status === 'draft', 403, 'Only drafts can be deleted.');

        DB::transaction(function () use ($proposal) {
            $proposal->documents->each->delete();
            $proposal->delete();
        });
        Audit::log('activity.proposal_deleted', null, ['entity_type' => 'ActivityProposal', 'entity_id' => $proposal->id, 'entity_label' => $proposal->tracking_no, 'description' => "Draft {$proposal->tracking_no} deleted"]);

        return redirect()->route('activity.proposals.index')->with('status', "Draft {$proposal->tracking_no} deleted.");
    }

    protected function rules(Request $request): array
    {
        $user = $request->user();
        $file = ['file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))];

        return [
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'applicant_name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'submission_method' => ['required', Rule::in(['physical', 'digital'])],
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::in(array_keys(config('activity.categories')))],
            'description' => ['nullable', 'string', 'max:5000'],
            'proposed_start' => ['nullable', 'date'],
            'proposed_end' => ['nullable', 'date', 'after_or_equal:proposed_start'],
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->where('is_active', true),
                function ($attribute, $value, $fail) use ($user) {
                    if (! $user->scopeCoverage()->covers('district', $value)) {
                        $fail('You can only register proposals for districts within your scope.');
                    }
                }],
            'venue' => ['nullable', 'string', 'max:200'],
            'participants_male' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'participants_female' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'estimated_budget' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'proposal_file' => array_merge(['nullable'], $file),
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => $file,
        ];
    }

    protected function attributes(array $data): array
    {
        $attrs = collect($data)->only((new ActivityProposal)->getFillable())->all();
        $attrs['participants_male'] = (int) ($attrs['participants_male'] ?? 0);
        $attrs['participants_female'] = (int) ($attrs['participants_female'] ?? 0);

        return $attrs;
    }

    /** @return int number of files stored */
    protected function storeUploads(Request $request, ActivityProposal $proposal): int
    {
        $n = 0;
        if ($request->hasFile('proposal_file')) {
            ActivityDocument::storeFor($proposal, $request->file('proposal_file'), 'proposal', $request->user());
            $n++;
        }
        foreach ($request->file('attachments', []) as $file) {
            ActivityDocument::storeFor($proposal, $file, 'attachment', $request->user());
            $n++;
        }

        return $n;
    }
}
