@extends('layouts.app')
@section('title', 'Activity ' . $proposal->tracking_no)

@php
    $user = auth()->user();
    $canEdit = $user->can('update', $activity);
    $steps = [
        'details' => ['Activity Details', $activity->actual_start && $activity->location && $activity->summary],
        'participants' => ['Participants', $activity->participantsTotal() > 0],
        'expenses' => ['Expenses', $activity->expenses->isNotEmpty()],
        'evidence' => ['Evidence', $activity->documents->contains('kind', 'picture')],
        'report' => ['Final Report', $activity->status === 'completed'],
    ];
    $keys = array_keys($steps);
    $next = $keys[array_search($tab, $keys, true) + 1] ?? null;
    $spent = $activity->expenses->sum('amount');
    $verifyCheck = Gate::inspect('verify', $activity);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Activity Execution &amp; Reporting</h1>
            <p>Manage the approved activity, record actual participants and expenses, and upload evidence.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('activity.activities.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Activities</a>
            <a href="{{ route('activity.activities.report', $activity) }}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-printer me-1"></i>Print Report</a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="summary-strip">
            <div><div class="k">Tracking No.</div><div class="v"><a href="{{ route('activity.proposals.show', $proposal) }}">{{ $proposal->tracking_no }}</a></div></div>
            <div><div class="k">Activity Title</div><div class="v">{{ $proposal->title }}</div></div>
            <div><div class="k">Applicant</div><div class="v">{{ $proposal->applicant_name }}</div></div>
            <div><div class="k">District</div><div class="v">{{ $activity->district->name }}</div></div>
            <div><div class="k">Approved Budget</div><div class="v">PKR {{ number_format($proposal->estimated_budget) }}</div></div>
            <div><div class="k">Status</div><div class="v">@include('activity.partials.badge', ['type' => 'activity', 'status' => $activity->status])</div></div>
        </div>
    </div>

    @if ($returnNote)
        <div class="alert alert-warning"><strong><i class="bi bi-arrow-return-left me-1"></i>Returned for correction</strong> by {{ $returnNote->user->name ?? '—' }} on {{ $returnNote->created_at->format('d M Y') }}:
            <div class="mt-1" style="white-space:pre-line">{{ $returnNote->remarks }}</div></div>
    @endif
    @if (! $canEdit && $activity->isEditable())
        <div class="alert alert-light border small"><i class="bi bi-eye me-1"></i>You can view this activity but not record implementation details.</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card">
                <nav class="nav flex-column step-nav p-2">
                    @foreach ($steps as $key => [$label, $done])
                        <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('activity.activities.show', [$activity, 'tab' => $key]) }}">
                            <span class="num">{{ $loop->iteration }}</span>{{ $label }}
                            @if ($done)<i class="bi bi-check-circle-fill done"></i>@endif
                        </a>
                    @endforeach
                </nav>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card">
                <div class="card-header">{{ $steps[$tab][0] }}</div>

                {{-- ===== 1. Details ===== --}}
                @if ($tab === 'details')
                    <form method="POST" action="{{ route('activity.activities.update', $activity) }}" novalidate>
                        @csrf @method('PUT')
                        <fieldset @disabled(! $canEdit) class="card-body row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="actual_start">Actual Start Date</label>
                                <input type="date" id="actual_start" name="actual_start" max="{{ now()->toDateString() }}" class="form-control @error('actual_start') is-invalid @enderror" value="{{ old('actual_start', $activity->actual_start?->toDateString()) }}">
                                @error('actual_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="actual_end">Actual End Date</label>
                                <input type="date" id="actual_end" name="actual_end" max="{{ now()->toDateString() }}" class="form-control @error('actual_end') is-invalid @enderror" value="{{ old('actual_end', $activity->actual_end?->toDateString()) }}">
                                @error('actual_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="location">Location</label>
                                <input id="location" name="location" class="form-control @error('location') is-invalid @enderror" value="{{ old('location', $activity->location) }}" maxlength="200" placeholder="e.g. Mardan Community Center">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="summary">Activity Summary</label>
                                <textarea id="summary" name="summary" rows="4" class="form-control @error('summary') is-invalid @enderror" maxlength="5000" placeholder="What took place, outcomes achieved, guests and resource persons…">{{ old('summary', $activity->summary) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="remarks">Remarks</label>
                                <textarea id="remarks" name="remarks" rows="2" class="form-control" maxlength="2000" placeholder="e.g. Activity completed successfully.">{{ old('remarks', $activity->remarks) }}</textarea>
                            </div>
                            @if ($canEdit)
                                <div class="col-12 d-flex justify-content-end gap-2">
                                    <button name="next" value="details" class="btn btn-outline-secondary">Save</button>
                                    <button name="next" value="{{ $next }}" class="btn btn-primary">Save &amp; Next <i class="bi bi-arrow-right"></i></button>
                                </div>
                            @endif
                        </fieldset>
                    </form>

                {{-- ===== 2. Participants ===== --}}
                @elseif ($tab === 'participants')
                    <form method="POST" action="{{ route('activity.activities.update', $activity) }}" novalidate>
                        @csrf @method('PUT')
                        <fieldset @disabled(! $canEdit) class="card-body row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="actual_male">Male</label>
                                <input type="number" min="0" id="actual_male" name="actual_male" class="form-control @error('actual_male') is-invalid @enderror" value="{{ old('actual_male', $activity->actual_male) }}" data-sum="act">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="actual_female">Female</label>
                                <input type="number" min="0" id="actual_female" name="actual_female" class="form-control @error('actual_female') is-invalid @enderror" value="{{ old('actual_female', $activity->actual_female) }}" data-sum="act">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="act-total">Total</label>
                                <input id="act-total" class="form-control bg-light fw-semibold" readonly data-sum-total="act">
                            </div>
                            <div class="col-12">
                                <div class="p-3 rounded bg-light small">
                                    <strong>Expected in proposal:</strong> {{ $proposal->participantsTotal() }}
                                    ({{ $proposal->participants_male }} male, {{ $proposal->participants_female }} female)
                                    @if ($activity->participantsTotal() && $proposal->participantsTotal())
                                        · <strong>Achieved:</strong> {{ round($activity->participantsTotal() / $proposal->participantsTotal() * 100) }}%
                                    @endif
                                </div>
                                <div class="form-text">Upload the signed attendance sheet under <a href="{{ route('activity.activities.show', [$activity, 'tab' => 'evidence']) }}">Evidence</a>.</div>
                            </div>
                            @if ($canEdit)
                                <div class="col-12 d-flex justify-content-end gap-2">
                                    <button name="next" value="participants" class="btn btn-outline-secondary">Save</button>
                                    <button name="next" value="{{ $next }}" class="btn btn-primary">Save &amp; Next <i class="bi bi-arrow-right"></i></button>
                                </div>
                            @endif
                        </fieldset>
                    </form>

                {{-- ===== 3. Expenses (booked in the Budget module's expense register) ===== --}}
                @elseif ($tab === 'expenses')
                    @php($commitment = $proposal->commitment)
                    <div class="card-body border-bottom">
                        @if ($commitment)
                            <div class="d-flex flex-wrap gap-4 small">
                                <div><div class="text-muted-2">Budget commitment</div>
                                    @can('view', $commitment)
                                        <a href="{{ route('budget.commitments.show', $commitment) }}" class="fw-semibold">{{ $commitment->reference_no }}</a>
                                    @else
                                        <span class="fw-semibold">{{ $commitment->reference_no }}</span>
                                    @endcan
                                    @include('budget.partials.status', ['status' => $commitment->status])</div>
                                <div><div class="text-muted-2">Charged to</div><span class="fw-semibold">{{ $commitment->allocation->office->name }} · {{ $commitment->allocation->headLabel() }}</span></div>
                                <div><div class="text-muted-2">Committed</div><span class="fw-semibold">@pkr($commitment->amount)</span></div>
                                <div><div class="text-muted-2">Spent</div><span class="fw-semibold">@pkr($spent)</span></div>
                                <div><div class="text-muted-2">Still reserved</div><span class="fw-semibold">@pkr($commitment->outstanding())</span></div>
                            </div>
                        @else
                            <div class="text-muted-2 small"><i class="bi bi-info-circle me-1"></i>No budget commitment is linked to this activity, so expenses cannot be charged to it here.</div>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Reference</th><th>Date</th><th>Category</th><th>Description</th><th class="text-end">Amount (PKR)</th><th>Verification</th></tr></thead>
                            <tbody>
                            @forelse ($activity->expenseEntries as $e)
                                <tr class="{{ $e->status === 'rejected' ? 'text-muted-2' : '' }}">
                                    <td class="text-nowrap small">
                                        @can('view', $e)
                                            <a href="{{ route('budget.transactions.show', $e) }}">{{ $e->reference_no }}</a>
                                        @else
                                            {{ $e->reference_no }}
                                        @endcan
                                    </td>
                                    <td class="text-nowrap small">{{ $e->txn_date->format('d M Y') }}</td>
                                    <td>{{ $e->categoryLabel() }}</td>
                                    <td>{{ $e->description }}<div class="small-2 text-muted-2">by {{ $e->submitter->name ?? '—' }}</div></td>
                                    <td class="text-end {{ $e->status === 'rejected' ? 'text-decoration-line-through' : '' }}">{{ number_format($e->amount, 2) }}</td>
                                    <td>@include('budget.partials.status', ['status' => $e->status])</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted-2 py-4">No expenses recorded yet.</td></tr>
                            @endforelse
                            </tbody>
                            <tfoot>
                            <tr class="fw-semibold"><td colspan="4" class="text-end">Total spent (excluding rejected)</td><td class="text-end">{{ number_format($spent, 2) }}</td><td></td></tr>
                            <tr class="small text-muted-2"><td colspan="4" class="text-end">Approved budget / remaining</td>
                                <td class="text-end {{ $spent > $proposal->estimated_budget ? 'text-danger fw-semibold' : '' }}">{{ number_format($proposal->estimated_budget, 2) }} / {{ number_format($proposal->estimated_budget - $spent, 2) }}</td><td></td></tr>
                            </tfoot>
                        </table>
                    </div>
                    @if ($canEdit && $commitment && $commitment->status === 'open')
                        <form method="POST" action="{{ route('activity.activities.expenses.store', $activity) }}" class="card-body border-top row g-2 align-items-end" novalidate>
                            @csrf
                            <div class="col-6 col-md-2">
                                <label class="form-label small" for="txn_date">Date</label>
                                <input type="date" id="txn_date" name="txn_date" max="{{ now()->toDateString() }}" class="form-control @error('txn_date') is-invalid @enderror" value="{{ old('txn_date', ($activity->actual_start ?? now())->toDateString()) }}">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small" for="category">Category</label>
                                <select id="category" name="category" class="form-select @error('category') is-invalid @enderror">
                                    @foreach (config('budget.expense_categories') as $k => $l)
                                        <option value="{{ $k }}" @selected(old('category') === $k)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="e-desc">Description</label>
                                <input id="e-desc" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" maxlength="255" placeholder="e.g. Tea and lunch for 200 participants">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small" for="e-payee">Payee</label>
                                <input id="e-payee" name="payee" class="form-control" value="{{ old('payee') }}" maxlength="150">
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="form-label small" for="amount">Amount</label>
                                <input type="number" min="0.01" step="0.01" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}">
                            </div>
                            <div class="col-2 col-md-1 d-grid"><button class="btn btn-primary" aria-label="Add expense"><i class="bi bi-plus-lg"></i></button></div>
                            @error('amount')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
                            @error('txn_date')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
                            <div class="col-12 form-text">Each expense goes to the Budget module's expense register for financial verification. Upload receipts under <a href="{{ route('activity.activities.show', [$activity, 'tab' => 'evidence']) }}">Evidence</a> as “Receipt / Invoice”.</div>
                        </form>
                    @endif

                {{-- ===== 4. Evidence ===== --}}
                @elseif ($tab === 'evidence')
                    <div class="card-body">
                        @if ($canEdit)
                            <form method="POST" action="{{ route('activity.activities.documents.store', $activity) }}" enctype="multipart/form-data" class="row g-3 mb-4" novalidate>
                                @csrf
                                <div class="col-md-4">
                                    <label class="form-label" for="kind">Type of evidence</label>
                                    <select id="kind" name="kind" class="form-select">
                                        @foreach (['picture', 'attendance', 'receipt', 'report', 'attachment'] as $k)
                                            <option value="{{ $k }}" @selected(old('kind') === $k)>{{ config("activity.document_kinds.$k") }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-primary w-100 mt-3"><i class="bi bi-upload me-1"></i>Upload</button>
                                </div>
                                <div class="col-md-8">
                                    @include('activity.partials.dropzone', ['name' => 'files[]', 'id' => 'files', 'multiple' => true])
                                </div>
                            </form>
                        @endif

                        @php($pictures = $activity->documents->where('kind', 'picture'))
                        <div class="section-title">Pictures <span class="text-muted-2 fw-normal small">({{ $pictures->count() }})</span></div>
                        @if ($pictures->isEmpty())
                            <p class="text-muted-2 small">No pictures uploaded yet. At least one picture is required before submitting completion.</p>
                        @else
                            <div class="evidence-grid mb-4">
                                @foreach ($pictures as $pic)
                                    <div class="tile">
                                        <a href="{{ route('activity.documents.show', [$pic, 'inline' => 1]) }}" target="_blank" rel="noopener">
                                            @if ($pic->isImage())
                                                <img src="{{ route('activity.documents.show', [$pic, 'inline' => 1]) }}" alt="{{ $pic->original_name }}" loading="lazy">
                                            @else
                                                <div class="d-grid" style="height:100px;place-items:center;background:#eef1f5"><i class="bi {{ $pic->icon() }} fs-2"></i></div>
                                            @endif
                                        </a>
                                        <div class="cap">
                                            <span class="text-truncate flex-grow-1" title="{{ $pic->original_name }}">{{ $pic->original_name }}</span>
                                            @if ($canEdit)
                                                <form method="POST" action="{{ route('activity.documents.destroy', $pic) }}" data-confirm="Remove this picture?">
                                                    @csrf @method('DELETE')
                                                    <button class="btn btn-sm p-0 text-danger border-0" aria-label="Remove picture"><i class="bi bi-x-circle"></i></button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="section-title">Other Documents</div>
                        @include('activity.partials.documents', ['documents' => $activity->documents->where('kind', '!=', 'picture'), 'canDelete' => $canEdit])
                    </div>

                {{-- ===== 5. Final report ===== --}}
                @else
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            @foreach ([
                                ['Participants', number_format($activity->participantsTotal()), $activity->actual_female . ' female'],
                                ['Expenses (PKR)', number_format($spent), 'of ' . number_format($proposal->estimated_budget) . ' approved'],
                                ['Evidence files', $activity->documents->count(), $activity->documents->where('kind', 'picture')->count() . ' pictures'],
                            ] as [$label, $value, $sub])
                                <div class="col-md-4">
                                    <div class="p-3 rounded border h-100">
                                        <div class="small text-muted-2">{{ $label }}</div>
                                        <div class="fs-4 fw-bold">{{ $value }}</div>
                                        <div class="small-2 text-muted-2">{{ $sub }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($activity->status === 'completed')
                            <div class="alert alert-success mb-0"><i class="bi bi-flag-fill me-1"></i>Completion verified by <strong>{{ $activity->verifier->name ?? '—' }}</strong> on {{ $activity->verified_at?->format('d M Y') }}. This activity is finalized.
                                <a href="{{ route('activity.activities.report', $activity) }}" target="_blank" class="alert-link ms-1">Print final report</a></div>
                        @elseif ($activity->status === 'completion_submitted')
                            <div class="alert alert-info"><i class="bi bi-hourglass-split me-1"></i>Completion report submitted by {{ $activity->completionSubmitter->name ?? '—' }} on {{ $activity->completion_submitted_at?->format('d M Y') }} and is awaiting verification.</div>
                            @if ($verifyCheck->allowed())
                                <form method="POST" action="{{ route('activity.activities.verify', $activity) }}" class="border rounded p-3" novalidate>
                                    @csrf
                                    <div class="section-title">Verify Completion Report</div>
                                    @php($vo = old('outcome', 'verified'))
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="outcome" id="v-yes" value="verified" @checked($vo === 'verified')>
                                        <label class="form-check-label" for="v-yes"><strong>Verified</strong> — finalize activity</label>
                                    </div>
                                    <div class="form-check form-check-inline mb-2">
                                        <input class="form-check-input" type="radio" name="outcome" id="v-no" value="returned" @checked($vo === 'returned')>
                                        <label class="form-check-label" for="v-no"><strong>Not verified</strong> — return for correction</label>
                                    </div>
                                    <textarea name="remarks" rows="2" class="form-control @error('remarks') is-invalid @enderror" placeholder="Remarks (required when returning)" aria-label="Remarks">{{ old('remarks') }}</textarea>
                                    @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="text-end mt-2"><button class="btn btn-primary"><i class="bi bi-clipboard-check me-1"></i>Save Verification</button></div>
                                </form>
                            @elseif ($user->hasPermission('activity.review') && $verifyCheck->message())
                                <p class="small text-muted-2 mb-0">{{ $verifyCheck->message() }}</p>
                            @endif
                        @else
                            <div class="section-title">Ready to submit?</div>
                            <ul class="checklist mb-3">
                                @foreach ($checklist as $item => $ok)
                                    <li><i class="bi {{ $ok ? 'bi-check-circle-fill text-primary' : 'bi-circle text-muted-2' }} me-2"></i>{{ ucfirst($item) }}</li>
                                @endforeach
                            </ul>
                            @error('completion')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
                            @can('submitCompletion', $activity)
                                <form method="POST" action="{{ route('activity.activities.submit', $activity) }}" class="row g-2 align-items-end">
                                    @csrf
                                    <div class="col-md-8">
                                        <label class="form-label small" for="s-remarks">Note for the verifying officer</label>
                                        <input id="s-remarks" name="remarks" class="form-control" maxlength="2000">
                                    </div>
                                    <div class="col-md-4 d-grid">
                                        <button class="btn btn-primary" @disabled(in_array(false, $checklist, true))><i class="bi bi-send-check me-1"></i>Submit Completion</button>
                                    </div>
                                </form>
                            @endcan
                        @endif
                    </div>
                @endif
            </div>

            <div class="card mt-3">
                <div class="card-header">History</div>
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
            var inputs = document.querySelectorAll('[data-sum="act"]'), total = document.querySelector('[data-sum-total="act"]');
            if (!total) return;
            function sum() { var t = 0; inputs.forEach(function (i) { t += parseInt(i.value, 10) || 0; }); total.value = t; }
            inputs.forEach(function (i) { i.addEventListener('input', sum); });
            sum();
        })();
    </script>
@endpush
