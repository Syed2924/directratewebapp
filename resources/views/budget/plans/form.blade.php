@extends('layouts.app')
@section('title', $plan->exists ? 'Edit Plan' : 'New Budget Plan')

@section('content')
    <div class="page-head">
        <div>
            <h1>Form D — Budget Planning</h1>
            <p>Plan spending by quarter and category.</p>
        </div>
        <a href="{{ $plan->exists ? route('budget.plans.show', $plan) : route('budget.plans.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" novalidate action="{{ $plan->exists ? route('budget.plans.update', $plan) : route('budget.plans.store') }}">
        @csrf
        @if ($plan->exists) @method('PUT') @endif
        <div class="card">
            <div class="card-body p-4">
                <div class="section-title">Budget Planning Form</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label d-flex justify-content-between" for="ref">Plan Reference <span class="auto-tag">{{ $plan->exists ? 'Assigned' : 'Auto Generated' }}</span></label>
                        <input id="ref" class="form-control bg-light fw-medium" value="{{ $reference }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="financial_year_id">Financial Year <span class="text-danger">*</span></label>
                        <select id="financial_year_id" name="financial_year_id" class="form-select @error('financial_year_id') is-invalid @enderror">
                            @foreach ($years as $y)<option value="{{ $y->id }}" @selected(old('financial_year_id', $plan->financial_year_id) == $y->id)>{{ $y->label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="quarter">Quarter <span class="text-danger">*</span></label>
                        <select id="quarter" name="quarter" class="form-select">
                            @foreach (config('budget.quarters') as $q => $l)<option value="{{ $q }}" @selected(old('quarter', $plan->quarter) == $q)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="office_id">Office / District <span class="text-danger">*</span></label>
                        <select id="office_id" name="office_id" class="form-select @error('office_id') is-invalid @enderror">
                            <option value="">Select office</option>
                            @foreach ($offices as $o)<option value="{{ $o->id }}" @selected(old('office_id', $plan->office_id) == $o->id)>{{ $o->name }}</option>@endforeach
                        </select>
                        @error('office_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="category">Program / Category <span class="text-danger">*</span></label>
                        <select id="category" name="category" class="form-select @error('category') is-invalid @enderror">
                            <option value="">Select category</option>
                            @foreach (config('activity.categories') as $k => $l)<option value="{{ $k }}" @selected(old('category', $plan->category) === $k)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="budget_head">Budget Head <span class="text-danger">*</span></label>
                        <select id="budget_head" name="budget_head" class="form-select">
                            @foreach (config('budget.budget_heads') as $k => $l)<option value="{{ $k }}" @selected(old('budget_head', $plan->budget_head ?? config('budget.category_heads.' . $plan->category, config('budget.default_activity_head'))) === $k)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="planned_amount">Planned Amount (PKR) <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="0.01" id="planned_amount" name="planned_amount" class="form-control @error('planned_amount') is-invalid @enderror" value="{{ old('planned_amount', $plan->planned_amount) }}">
                        @error('planned_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="expected_on">Expected Expenditure Date</label>
                        <input type="date" id="expected_on" name="expected_on" class="form-control @error('expected_on') is-invalid @enderror" value="{{ old('expected_on', $plan->expected_on?->toDateString()) }}">
                        @error('expected_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="proposal_tracking">Linked Proposal <span class="text-muted-2 fw-normal">(optional)</span></label>
                        <input id="proposal_tracking" name="proposal_tracking" class="form-control @error('proposal_tracking') is-invalid @enderror" value="{{ old('proposal_tracking', $plan->proposal->tracking_no ?? '') }}" placeholder="YA-2026-0001">
                        @error('proposal_tracking')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="justification">Justification</label>
                        <textarea id="justification" name="justification" rows="2" class="form-control" maxlength="2000" placeholder="e.g. To encourage youth participation and leadership skills.">{{ old('justification', $plan->justification) }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="approval_reference">Approval Reference</label>
                        <input id="approval_reference" name="approval_reference" class="form-control" value="{{ old('approval_reference', $plan->approval_reference) }}" maxlength="60">
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2 py-3">
                <button name="intent" value="draft" class="btn btn-outline-secondary">Save Draft</button>
                <button name="intent" value="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Save &amp; Submit for Approval</button>
            </div>
        </div>
    </form>
@endsection
