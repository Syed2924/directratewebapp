@extends('layouts.app')
@section('title', $allocation->exists ? 'Edit Allocation' : 'New Allocation')

@section('content')
    <div class="page-head">
        <div>
            <h1>Form B — District / Office Budget Allocation</h1>
            <p>Allocate part of the approved Directorate budget to an office.</p>
        </div>
        <a href="{{ $allocation->exists ? route('budget.allocations.show', $allocation) : route('budget.allocations.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    @if ($years->isEmpty())
        <div class="alert alert-warning">There is no approved Directorate budget yet. Allocations can only be made from an approved budget.</div>
    @endif

    <form method="POST" enctype="multipart/form-data" novalidate action="{{ $allocation->exists ? route('budget.allocations.update', $allocation) : route('budget.allocations.store') }}">
        @csrf
        @if ($allocation->exists) @method('PUT') @endif
        <div class="card">
            <div class="card-body p-4">
                <div class="section-title">District / Office Allocation Form</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label" for="financial_year_id">Financial Year <span class="text-danger">*</span></label>
                        <select id="financial_year_id" name="financial_year_id" class="form-select @error('financial_year_id') is-invalid @enderror">
                            @foreach ($years as $y)<option value="{{ $y->id }}" @selected(old('financial_year_id', $allocation->financial_year_id) == $y->id)>{{ $y->label }}</option>@endforeach
                        </select>
                        @error('financial_year_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="office_id">Office / District <span class="text-danger">*</span></label>
                        <select id="office_id" name="office_id" class="form-select @error('office_id') is-invalid @enderror">
                            <option value="">Select office</option>
                            @foreach ($offices as $o)<option value="{{ $o->id }}" @selected(old('office_id', $allocation->office_id) == $o->id)>{{ $o->name }} ({{ $o->district->name }})</option>@endforeach
                        </select>
                        @error('office_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="budget_head">Budget Head <span class="text-danger">*</span></label>
                        <select id="budget_head" name="budget_head" class="form-select @error('budget_head') is-invalid @enderror">
                            @foreach (config('budget.budget_heads') as $k => $l)<option value="{{ $k }}" @selected(old('budget_head', $allocation->budget_head) === $k)>{{ $l }}</option>@endforeach
                        </select>
                        @error('budget_head')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label d-flex justify-content-between" for="ref">Allocation Reference <span class="auto-tag">{{ $allocation->exists ? 'Assigned' : 'Auto Generated' }}</span></label>
                        <input id="ref" class="form-control bg-light fw-medium" value="{{ $reference }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="allocation_date">Allocation Date <span class="text-danger">*</span></label>
                        <input type="date" id="allocation_date" name="allocation_date" class="form-control @error('allocation_date') is-invalid @enderror" value="{{ old('allocation_date', $allocation->allocation_date?->toDateString()) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="planned_amount">Planned Allocation (PKR) <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="0.01" id="planned_amount" name="planned_amount" class="form-control @error('planned_amount') is-invalid @enderror" value="{{ old('planned_amount', $allocation->planned_amount) }}">
                        @error('planned_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">The approver sets the approved amount. Transfers in/out and the revised allocation are calculated from approved transfers.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="remarks">Remarks</label>
                        <input id="remarks" name="remarks" class="form-control" value="{{ old('remarks', $allocation->remarks) }}" maxlength="2000">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Supporting Documents <span class="text-muted-2 fw-normal">(optional)</span></label>
                        @include('activity.partials.dropzone', ['name' => 'documents[]', 'id' => 'documents', 'multiple' => true])
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
