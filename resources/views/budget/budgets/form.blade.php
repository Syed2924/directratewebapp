@extends('layouts.app')
@section('title', $budget->exists ? 'Edit Budget' : 'Register Directorate Budget')

@section('content')
    <div class="page-head">
        <div>
            <h1>Form A — Overall Directorate Budget</h1>
            <p>Record the main budget for the entire Directorate for the financial year.</p>
        </div>
        <a href="{{ $budget->exists ? route('budget.budgets.show', $budget) : route('budget.budgets.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" enctype="multipart/form-data" novalidate action="{{ $budget->exists ? route('budget.budgets.update', $budget) : route('budget.budgets.store') }}">
        @csrf
        @if ($budget->exists) @method('PUT') @endif
        @php($fy = $budget->financialYear)
        <div class="card">
            <div class="card-body p-4">
                <div class="section-title">Directorate Budget Form</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label" for="fy_label">Financial Year <span class="text-danger">*</span></label>
                        <input id="fy_label" name="fy_label" class="form-control @error('fy_label') is-invalid @enderror" value="{{ old('fy_label', $fy->label) }}" placeholder="2026-27" @disabled($budget->exists)>
                        @error('fy_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fy_starts_on">Financial Year Start <span class="text-danger">*</span></label>
                        <input type="date" id="fy_starts_on" name="fy_starts_on" class="form-control @error('fy_starts_on') is-invalid @enderror" value="{{ old('fy_starts_on', $fy->starts_on?->toDateString()) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fy_ends_on">Financial Year End <span class="text-danger">*</span></label>
                        <input type="date" id="fy_ends_on" name="fy_ends_on" class="form-control @error('fy_ends_on') is-invalid @enderror" value="{{ old('fy_ends_on', $fy->ends_on?->toDateString()) }}">
                        @error('fy_ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label d-flex justify-content-between" for="ref">Budget Reference No. <span class="auto-tag">{{ $budget->exists ? 'Assigned' : 'Auto Generated' }}</span></label>
                        <input id="ref" class="form-control bg-light fw-medium" value="{{ $reference }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="original_amount">Original Approved Budget (PKR) <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="0.01" id="original_amount" name="original_amount" class="form-control @error('original_amount') is-invalid @enderror" value="{{ old('original_amount', $budget->original_amount) }}" placeholder="50000000">
                        @error('original_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Budget Status</label>
                        <input class="form-control bg-light" value="{{ $budget->exists ? $budget->statusLabel() : 'Draft' }}" readonly>
                        <div class="form-text">Changes to <em>Approved</em> only through the approval step. Later increases or decreases are recorded as revisions.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="funding_source">Funding Source <span class="text-danger">*</span></label>
                        <select id="funding_source" name="funding_source" class="form-select">
                            @foreach (config('budget.funding_sources') as $k => $l)<option value="{{ $k }}" @selected(old('funding_source', $budget->funding_source) === $k)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="budget_head">Budget Head <span class="text-danger">*</span></label>
                        <select id="budget_head" name="budget_head" class="form-select">
                            @foreach (config('budget.budget_heads') as $k => $l)<option value="{{ $k }}" @selected(old('budget_head', $budget->budget_head) === $k)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="approving_authority">Approving Authority</label>
                        <input id="approving_authority" name="approving_authority" class="form-control" value="{{ old('approving_authority', $budget->approving_authority) }}" placeholder="e.g. Finance Department, Govt. of KP" maxlength="150">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="approved_on">Date Approved</label>
                        <input type="date" id="approved_on" name="approved_on" max="{{ now()->toDateString() }}" class="form-control @error('approved_on') is-invalid @enderror" value="{{ old('approved_on', $budget->approved_on?->toDateString()) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="effective_on">Effective Date</label>
                        <input type="date" id="effective_on" name="effective_on" class="form-control" value="{{ old('effective_on', $budget->effective_on?->toDateString()) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="remarks">Remarks</label>
                        <input id="remarks" name="remarks" class="form-control" value="{{ old('remarks', $budget->remarks) }}" maxlength="2000">
                    </div>
                </div>

                <div class="section-title">Documents</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Budget Document</label>
                        @include('activity.partials.dropzone', ['name' => 'budget_document', 'id' => 'budget_document'])
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Supporting Approval Documents <span class="text-muted-2 fw-normal">(optional)</span></label>
                        @include('activity.partials.dropzone', ['name' => 'supporting[]', 'id' => 'supporting', 'multiple' => true])
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2 py-3">
                <button name="intent" value="draft" class="btn btn-outline-secondary"><i class="bi bi-save me-1"></i>Save Draft</button>
                <button name="intent" value="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Register &amp; Submit for Approval</button>
            </div>
        </div>
    </form>
@endsection
