@extends('layouts.app')
@section('title', $budget->exists ? 'Edit Center Budget' : 'Add Center Budget')

@section('content')
    <div class="page-head">
        <div>
            <h1>Budget Allocation for Jawan Markaz</h1>
            <p>Record the approved budget allocated to a center for a financial year.</p>
        </div>
        <a href="{{ $budget->exists ? route('jawan-markaz.budgets.show', $budget) : route('jawan-markaz.budgets.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" enctype="multipart/form-data" novalidate action="{{ $budget->exists ? route('jawan-markaz.budgets.update', $budget) : route('jawan-markaz.budgets.store') }}">
        @csrf
        @if ($budget->exists) @method('PUT') @endif
        <div class="card">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="financial_year_id">Financial Year <span class="text-danger">*</span></label>
                        <select id="financial_year_id" name="financial_year_id" class="form-select @error('financial_year_id') is-invalid @enderror">
                            @foreach ($years as $y)<option value="{{ $y->id }}" @selected(old('financial_year_id', $budget->financial_year_id) == $y->id)>{{ $y->label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="center_id">Center <span class="text-danger">*</span></label>
                        <select id="center_id" name="center_id" class="form-select @error('center_id') is-invalid @enderror">
                            <option value="">Select center</option>
                            @foreach ($centers as $c)<option value="{{ $c->id }}" @selected(old('center_id', $budget->center_id) == $c->id)>{{ $c->name }} ({{ $c->district->name }})</option>@endforeach
                        </select>
                        @error('center_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label d-flex justify-content-between" for="ref">Reference No. <span class="auto-tag">{{ $budget->exists ? 'Assigned' : 'Auto Generated' }}</span></label>
                        <input id="ref" class="form-control bg-light fw-medium" value="{{ $reference }}" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="allocation_date">Issue / Allocation Date <span class="text-danger">*</span></label>
                        <input type="date" id="allocation_date" name="allocation_date" class="form-control @error('allocation_date') is-invalid @enderror" value="{{ old('allocation_date', $budget->allocation_date?->toDateString()) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="authorization_reference">Authorization / Reference Number <span class="text-danger">*</span></label>
                        <input id="authorization_reference" name="authorization_reference" class="form-control @error('authorization_reference') is-invalid @enderror" value="{{ old('authorization_reference', $budget->authorization_reference) }}" maxlength="100" placeholder="e.g. DYA/JM/2026/31">
                        @error('authorization_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="allocated_amount">Approved Allocation (PKR) <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="0.01" id="allocated_amount" name="allocated_amount" class="form-control @error('allocated_amount') is-invalid @enderror" value="{{ old('allocated_amount', $budget->allocated_amount) }}">
                        @error('allocated_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Later increases or decreases are recorded as revisions; the original amount is kept in the history.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Authorization Document</label>
                        @include('activity.partials.dropzone', ['name' => 'authorization_document', 'id' => 'authorization_document', 'hint' => 'Approved budget letter or document (PDF, DOC, JPG, PNG)'])
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" rows="4" class="form-control" maxlength="2000" placeholder="Additional remarks (optional)">{{ old('remarks', $budget->remarks) }}</textarea>
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
