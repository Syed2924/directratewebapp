{{--
    Submit / decide panel for any financial record.
    @include('budget.partials.workflow', ['record' => $allocation, 'type' => 'allocation'])
--}}
@php
    $wfUser = auth()->user();
    $wfDecide = Gate::inspect('decide', $record);
    $wfVerb = $record->decisionAbility() === 'review' ? 'verification' : 'approval';
@endphp

@if ($record->status === 'returned' && ($ret = $record->approvals->where('action', 'returned')->last()))
    <div class="alert alert-warning"><strong><i class="bi bi-arrow-return-left me-1"></i>Returned for correction</strong> by {{ $ret->user->name ?? '—' }} on {{ $ret->created_at->format('d M Y') }}:
        <div class="mt-1" style="white-space:pre-line">{{ $ret->remarks }}</div></div>
@endif
@if ($record->status === 'rejected' && ($rej = $record->approvals->where('action', 'rejected')->last()))
    <div class="alert alert-danger"><strong>Rejected</strong> by {{ $rej->user->name ?? '—' }} on {{ $rej->created_at->format('d M Y') }}:
        <div class="mt-1" style="white-space:pre-line">{{ $rej->remarks }}</div></div>
@endif

@can('submit', $record)
    <div class="card action-card mb-3">
        <form method="POST" action="{{ route('budget.workflow.submit', [$type, $record->id]) }}" class="card-body d-flex flex-wrap gap-2 align-items-end">
            @csrf
            <div class="flex-grow-1">
                <label class="form-label small mb-1" for="submit-remarks">This entry is a {{ strtolower($record->statusLabel()) }}. Submit it for approval when it is ready.</label>
                <input id="submit-remarks" name="remarks" class="form-control" placeholder="Note for the approver (optional)" maxlength="2000">
            </div>
            <button class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit for Approval</button>
        </form>
    </div>
@endcan

@if ($record->awaitingDecision())
    @if ($wfDecide->allowed())
        <div class="card action-card mb-3">
            <div class="card-header">{{ $wfVerb === 'approval' ? 'Approval & Decision' : 'Financial Verification' }}</div>
            <form method="POST" action="{{ route('budget.workflow.decide', [$type, $record->id]) }}" class="card-body row g-3" novalidate>
                @csrf
                @error('decision')<div class="col-12"><div class="alert alert-danger py-2 mb-0">{{ $message }}</div></div>@enderror
                <div class="col-md-6">
                    <label class="form-label" for="wf-decision">Decision <span class="text-danger">*</span></label>
                    <select id="wf-decision" name="decision" class="form-select">
                        @foreach ($record->decisionOptions() as $key => [$label])
                            <option value="{{ $key }}" @selected(old('decision') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($type === 'allocation')
                    <div class="col-md-6">
                        <label class="form-label" for="wf-approved">Approved Allocation (PKR)</label>
                        <input type="number" min="1" step="0.01" id="wf-approved" name="approved_amount" class="form-control @error('approved_amount') is-invalid @enderror" value="{{ old('approved_amount', (float) $record->planned_amount) }}">
                        @error('approved_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Planned: @pkr($record->planned_amount)</div>
                    </div>
                @endif
                @if (method_exists($record, 'decisionFieldsView') && $record->decisionFieldsView())
                    @include($record->decisionFieldsView(), ['record' => $record])
                @endif
                <div class="col-12">
                    <label class="form-label" for="wf-remarks">Remarks</label>
                    <textarea id="wf-remarks" name="remarks" rows="2" class="form-control @error('remarks') is-invalid @enderror" placeholder="Required when returning or rejecting">{{ old('remarks') }}</textarea>
                    @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 text-end"><button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Decision</button></div>
            </form>
        </div>
    @else
        <div class="alert alert-info d-flex gap-2"><i class="bi bi-hourglass-split"></i>
            <div>Awaiting {{ $wfVerb }}.
                @if ($wfDecide->message() && $wfUser->hasPermission('budget.' . $record->decisionAbility()))<br><span class="small">{{ $wfDecide->message() }}</span>@endif
            </div>
        </div>
    @endif
@endif
