@extends('layouts.app')
@section('title', 'Proposal ' . $proposal->tracking_no)

@php
    $user = auth()->user();
    $lastReturn = $proposal->actions->whereIn('action', ['review_returned', 'decision_returned'])->last();
    $lastRejection = $proposal->actions->whereIn('action', ['review_rejected', 'decision_rejected'])->last();
    $decisionCheck = Gate::inspect('decide', $proposal);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Proposal {{ $proposal->tracking_no }}</h1>
            <p>{{ $proposal->title }} · {{ $proposal->applicant_name }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('activity.proposals.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>All Proposals</a>
            @can('update', $proposal)
                <a href="{{ route('activity.proposals.edit', $proposal) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
            @endcan
            @if ($proposal->status === 'draft' && $user->can('update', $proposal))
                <form method="POST" action="{{ route('activity.proposals.destroy', $proposal) }}" data-confirm="Delete draft {{ $proposal->tracking_no }}? This cannot be undone.">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete Draft</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card mb-3">
        <div class="summary-strip">
            <div><div class="k">Tracking No.</div><div class="v">{{ $proposal->tracking_no }}</div></div>
            <div><div class="k">Activity Title</div><div class="v">{{ $proposal->title }}</div></div>
            <div><div class="k">District</div><div class="v">{{ $proposal->district->name }}</div></div>
            <div><div class="k">Applicant</div><div class="v">{{ $proposal->applicant_name }}</div></div>
            <div><div class="k">Reviewing Officer</div><div class="v">{{ $proposal->assignee->name ?? '—' }}</div></div>
            <div><div class="k">Status</div><div class="v">@include('activity.partials.badge', ['type' => 'proposal', 'status' => $proposal->status])</div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">

            {{-- ===== Contextual workflow actions ===== --}}

            @if ($proposal->status === 'draft')
                <div class="alert alert-secondary d-flex gap-2 align-items-start">
                    <i class="bi bi-pencil-square mt-1"></i>
                    <div>This proposal is a <strong>draft</strong> and has not entered the workflow yet.
                        @can('register', $proposal) Open <a href="{{ route('activity.proposals.edit', $proposal) }}">Edit</a> and choose <em>Register Proposal</em> when the details are complete. @endcan
                    </div>
                </div>
            @endif

            @can('checkCompleteness', $proposal)
                <div class="card action-card mb-3">
                    <div class="card-header">Review Proposal Details · Information Complete?</div>
                    <form method="POST" action="{{ route('activity.proposals.completeness', $proposal) }}" class="card-body" novalidate>
                        @csrf
                        @if ($proposal->status === 'incomplete')
                            <div class="alert alert-warning py-2 small"><strong>Missing information requested:</strong> {{ $proposal->missing_information }}</div>
                        @endif
                        @php($outcome = old('outcome', 'complete'))
                        <div class="mb-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="outcome" id="oc-yes" value="complete" @checked($outcome === 'complete')>
                                <label class="form-check-label" for="oc-yes"><strong>Yes</strong>, complete — assign for internal review</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="outcome" id="oc-no" value="incomplete" @checked($outcome === 'incomplete')>
                                <label class="form-check-label" for="oc-no"><strong>No</strong>, record missing information</label>
                            </div>
                        </div>
                        <div data-when="complete" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="c-assigned">Assign to Officer <span class="text-danger">*</span></label>
                                <select id="c-assigned" name="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror">
                                    <option value="">Select officer</option>
                                    @foreach ($officers as $o)
                                        <option value="{{ $o->id }}" @selected(old('assigned_to') == $o->id)>{{ $o->name }}{{ $o->designation ? ' — ' . $o->designation : '' }}</option>
                                    @endforeach
                                </select>
                                @error('assigned_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @if ($officers->isEmpty())<div class="form-text text-danger">No active officer with review permission covers {{ $proposal->district->name }}.</div>@endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="c-remarks">Instructions for the officer</label>
                                <input id="c-remarks" name="remarks" class="form-control" value="{{ old('remarks') }}" maxlength="2000">
                            </div>
                        </div>
                        <div data-when="incomplete">
                            <label class="form-label" for="c-missing">What is missing? <span class="text-danger">*</span></label>
                            <textarea id="c-missing" name="missing_information" rows="3" class="form-control @error('missing_information') is-invalid @enderror" placeholder="e.g. Budget breakdown and NOC from district administration">{{ old('missing_information', $proposal->missing_information) }}</textarea>
                            @error('missing_information')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="text-end mt-3"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save</button></div>
                    </form>
                </div>
            @endcan

            @can('officerReview', $proposal)
                <div class="card action-card mb-3">
                    <div class="card-header">Officer Review · Review Decision</div>
                    <form method="POST" action="{{ route('activity.proposals.review', $proposal) }}" class="card-body row g-3" novalidate>
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label">Review By</label>
                            <input class="form-control bg-light" value="{{ $user->name }}" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="r-decision">Recommendation <span class="text-danger">*</span></label>
                            <select id="r-decision" name="decision" class="form-select @error('decision') is-invalid @enderror">
                                <option value="recommend" @selected(old('decision') === 'recommend')>Recommend for Approval</option>
                                <option value="return" @selected(old('decision') === 'return')>Return for Correction</option>
                                <option value="reject" @selected(old('decision') === 'reject')>Reject</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="r-remarks">Remarks <span class="text-danger">*</span></label>
                            <textarea id="r-remarks" name="remarks" rows="3" class="form-control @error('remarks') is-invalid @enderror" placeholder="e.g. The proposal is well planned and aligns with department objectives.">{{ old('remarks') }}</textarea>
                            @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 text-end"><button class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit Review</button></div>
                    </form>
                </div>
            @endcan

            @if ($proposal->status === 'recommended')
                @if ($decisionCheck->allowed())
                    <div class="card action-card mb-3">
                        <div class="card-header">Approval &amp; Decision</div>
                        <form method="POST" action="{{ route('activity.proposals.decide', $proposal) }}" class="card-body row g-3" novalidate>
                            @csrf
                            @if ($recommendation)
                                <div class="col-12">
                                    <div class="small fw-semibold mb-1">Recommendation</div>
                                    <div class="p-3 rounded border bg-light small">
                                        <div class="mb-1"><strong>{{ $recommendation->user->name ?? 'Officer' }}</strong> recommended approval on {{ $recommendation->created_at->format('d M Y') }}</div>
                                        <div style="white-space:pre-line">{{ $recommendation->remarks }}</div>
                                    </div>
                                </div>
                            @endif
                            @if ($funding !== null)
                                <div class="col-12">
                                    <div class="small fw-semibold mb-1">Authorized Funding Available?</div>
                                    @if ($funding->isEmpty())
                                        <div class="alert alert-warning small mb-0">
                                            <strong>No.</strong> No approved {{ App\Models\FinancialYear::current()?->label }} allocation in {{ $proposal->district->name }} has
                                            @pkr($proposal->estimated_budget) available. Escalate or revise the plan: return the proposal for a revised budget,
                                            or ask Finance to allocate or transfer funds, then approve.
                                        </div>
                                    @else
                                        <label class="form-label small text-muted-2" for="d-allocation">Commit @pkr($proposal->estimated_budget) against</label>
                                        <select id="d-allocation" name="allocation_id" class="form-select @error('allocation_id') is-invalid @enderror">
                                            @foreach ($funding as $fa)
                                                <option value="{{ $fa->id }}" @selected(old('allocation_id') == $fa->id)>{{ $fa->reference_no }} · {{ $fa->office->name }} · {{ $fa->headLabel() }} — available PKR {{ number_format($fa->available()) }}</option>
                                            @endforeach
                                        </select>
                                        @error('allocation_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <div class="form-text">Approving records an authorized budget commitment for this amount.</div>
                                    @endif
                                </div>
                            @endif
                            <div class="col-md-6">
                                <label class="form-label" for="d-decision">Decision <span class="text-danger">*</span></label>
                                <select id="d-decision" name="decision" class="form-select @error('decision') is-invalid @enderror">
                                    <option value="approve" @selected(old('decision') === 'approve')>Approved</option>
                                    <option value="return" @selected(old('decision') === 'return')>Return for Correction</option>
                                    <option value="reject" @selected(old('decision') === 'reject')>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="d-date">Decision Date <span class="text-danger">*</span></label>
                                <input type="date" id="d-date" name="decision_date" max="{{ now()->toDateString() }}" class="form-control @error('decision_date') is-invalid @enderror" value="{{ old('decision_date', now()->toDateString()) }}">
                                @error('decision_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="d-remarks">Remarks <span class="text-danger">*</span></label>
                                <textarea id="d-remarks" name="remarks" rows="3" class="form-control @error('remarks') is-invalid @enderror" placeholder="e.g. Approved for implementation. Please proceed with next steps.">{{ old('remarks') }}</textarea>
                                @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 d-flex justify-content-end gap-2">
                                <a href="{{ route('activity.assignments.index') }}" class="btn btn-light">Cancel</a>
                                <button class="btn btn-primary"><i class="bi bi-patch-check me-1"></i>Save Decision</button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="alert alert-info d-flex gap-2"><i class="bi bi-hourglass-split"></i>
                        <div>Recommended by {{ $proposal->assignee->name ?? 'the reviewing officer' }} — waiting for the approving authority's decision.
                            @if ($user->hasPermission('activity.approve') && $decisionCheck->message()) <br><span class="small">{{ $decisionCheck->message() }}</span>@endif
                        </div>
                    </div>
                @endif
            @endif

            @if ($proposal->status === 'under_review' && $proposal->assigned_to !== $user->id)
                <div class="alert alert-info d-flex gap-2"><i class="bi bi-hourglass-split"></i><div>Under internal review by <strong>{{ $proposal->assignee->name ?? '—' }}</strong> since {{ $proposal->assigned_at?->format('d M Y') }}.</div></div>
            @endif

            @if ($proposal->status === 'returned')
                <div class="card mb-3 border-warning">
                    <div class="card-header bg-warning-subtle"><i class="bi bi-arrow-return-left me-1"></i>Returned for Correction</div>
                    <div class="card-body">
                        @if ($lastReturn)
                            <p class="mb-2 small"><strong>{{ $lastReturn->user->name ?? '—' }}</strong> on {{ $lastReturn->created_at->format('d M Y') }}:</p>
                            <div class="p-3 rounded border bg-light small mb-3" style="white-space:pre-line">{{ $lastReturn->remarks }}</div>
                        @endif
                        @can('resubmit', $proposal)
                            <form method="POST" action="{{ route('activity.proposals.resubmit', $proposal) }}" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-md-8">
                                    <label class="form-label small" for="rs-remarks">Note on corrections made</label>
                                    <input id="rs-remarks" name="remarks" class="form-control" maxlength="2000" placeholder="e.g. Revised budget attached">
                                </div>
                                <div class="col-md-4 d-flex gap-2">
                                    @can('update', $proposal)<a href="{{ route('activity.proposals.edit', $proposal) }}" class="btn btn-outline-primary">Edit</a>@endcan
                                    <button class="btn btn-primary flex-grow-1"><i class="bi bi-send me-1"></i>Resubmit</button>
                                </div>
                            </form>
                        @endcan
                    </div>
                </div>
            @endif

            @if ($proposal->status === 'rejected' && $lastRejection)
                <div class="alert alert-danger"><strong>Rejected</strong> by {{ $lastRejection->user->name ?? '—' }} on {{ ($proposal->decided_on ?? $lastRejection->created_at)->format('d M Y') }}.
                    <div class="mt-1" style="white-space:pre-line">{{ $lastRejection->remarks }}</div></div>
            @endif

            @if ($proposal->status === 'approved' && $proposal->activity)
                <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div><i class="bi bi-patch-check-fill me-1"></i>Approved on {{ $proposal->decided_on?->format('d M Y') }}. Activity status:
                        @include('activity.partials.badge', ['type' => 'activity', 'status' => $proposal->activity->status])</div>
                    @can('view', $proposal->activity)
                        <a href="{{ route('activity.activities.show', $proposal->activity) }}" class="btn btn-sm btn-primary">Open Activity <i class="bi bi-arrow-right"></i></a>
                    @endcan
                </div>
            @endif

            {{-- ===== Proposal details ===== --}}
            <div class="card mb-3">
                <div class="card-header">Proposal Details</div>
                <div class="card-body">
                    <dl class="row kv mb-0">
                        <dt class="col-sm-4">Date Received</dt><dd class="col-sm-8">{{ $proposal->received_on->format('d M Y') }} · {{ $proposal->submission_method === 'physical' ? 'Physical paper' : 'Digital document' }}</dd>
                        <dt class="col-sm-4">Applicant / Organization</dt><dd class="col-sm-8">{{ $proposal->applicant_name }}</dd>
                        <dt class="col-sm-4">Contact</dt><dd class="col-sm-8">{{ collect([$proposal->contact_person, $proposal->phone, $proposal->email])->filter()->implode(' · ') ?: '—' }}</dd>
                        <dt class="col-sm-4">Category</dt><dd class="col-sm-8">{{ $proposal->categoryLabel() }}</dd>
                        <dt class="col-sm-4">Proposed Dates</dt><dd class="col-sm-8">{{ $proposal->proposed_start?->format('d M Y') ?? '—' }}{{ $proposal->proposed_end && ! $proposal->proposed_end->eq($proposal->proposed_start ?? $proposal->proposed_end) ?' – ' . $proposal->proposed_end->format('d M Y') : '' }}</dd>
                        <dt class="col-sm-4">District / Venue</dt><dd class="col-sm-8">{{ $proposal->district->name }}{{ $proposal->venue ? ' · ' . $proposal->venue : '' }}</dd>
                        <dt class="col-sm-4">Expected Participants</dt><dd class="col-sm-8">{{ $proposal->participantsTotal() }} <span class="text-muted-2">({{ $proposal->participants_male }} male, {{ $proposal->participants_female }} female)</span></dd>
                        <dt class="col-sm-4">Estimated Budget</dt><dd class="col-sm-8">PKR {{ number_format($proposal->estimated_budget) }}
                            @if ($proposal->commitment)
                                <div class="small text-muted-2">Committed as
                                    @can('view', $proposal->commitment)
                                        <a href="{{ route('budget.commitments.show', $proposal->commitment) }}">{{ $proposal->commitment->reference_no }}</a>
                                    @else
                                        {{ $proposal->commitment->reference_no }}
                                    @endcan
                                    from {{ $proposal->commitment->allocation->office->name }} ({{ $proposal->commitment->statusLabel() }})</div>
                            @endif
                        </dd>
                        @if ($proposal->description)
                            <dt class="col-sm-4">Description</dt><dd class="col-sm-8" style="white-space:pre-line">{{ $proposal->description }}</dd>
                        @endif
                        <dt class="col-sm-4">Registered By</dt><dd class="col-sm-8 mb-0">{{ $proposal->submitter->name ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Documents</div>
                <div class="card-body py-2">
                    @include('activity.partials.documents', ['documents' => $proposal->documents, 'canDelete' => $user->can('update', $proposal)])
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            @if (in_array($proposal->status, ['under_review', 'returned'], true) && $user->can('assign', $proposal))
                <div class="card mb-3">
                    <div class="card-header">Reassign</div>
                    <form method="POST" action="{{ route('activity.proposals.assign', $proposal) }}" class="card-body" novalidate>
                        @csrf
                        <select name="assigned_to" class="form-select mb-2" aria-label="Officer">
                            @foreach ($officers as $o)
                                <option value="{{ $o->id }}" @selected($proposal->assigned_to === $o->id)>{{ $o->name }}</option>
                            @endforeach
                        </select>
                        <input name="remarks" class="form-control mb-2" placeholder="Reason (optional)" maxlength="2000">
                        <button class="btn btn-outline-primary w-100">Reassign</button>
                    </form>
                </div>
            @endif

            <div class="card">
                <div class="card-header">Assignment &amp; Workflow History</div>
                <div class="card-body">
                    @include('activity.partials.timeline', ['actions' => $proposal->actions])
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var radios = document.querySelectorAll('input[name=outcome]');
            if (!radios.length) return;
            function sync() {
                var v = document.querySelector('input[name=outcome]:checked').value;
                document.querySelectorAll('[data-when]').forEach(function (el) { el.hidden = el.dataset.when !== v; });
            }
            radios.forEach(function (r) { r.addEventListener('change', sync); });
            sync();
        })();
    </script>
@endpush
