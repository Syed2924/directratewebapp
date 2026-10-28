@extends('layouts.app')
@section('title', 'New Commitment')

@section('content')
    <div class="page-head">
        <div>
            <h1>Record Authorized Budget Commitment</h1>
            <p>Reserve funds in an allocation for an authorized obligation. Approved activities are committed automatically.</p>
        </div>
        <a href="{{ route('budget.commitments.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" enctype="multipart/form-data" action="{{ route('budget.commitments.store') }}" novalidate>
        @csrf
        <div class="card">
            <div class="card-body p-4 row g-3">
                <div class="col-md-4">
                    <label class="form-label d-flex justify-content-between" for="ref">Reference <span class="auto-tag">Auto Generated</span></label>
                    <input id="ref" class="form-control bg-light fw-medium" value="{{ $reference }}" readonly>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="allocation_id">Charge to Allocation <span class="text-danger">*</span></label>
                    <select id="allocation_id" name="allocation_id" class="form-select @error('allocation_id') is-invalid @enderror">
                        <option value="">Select allocation</option>
                        @foreach ($allocations as $a)
                            <option value="{{ $a->id }}" @selected(old('allocation_id') == $a->id) @disabled($a->available() <= 0)>{{ $a->label() }} — available PKR {{ number_format($a->available()) }}</option>
                        @endforeach
                    </select>
                    @error('allocation_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="description">Description <span class="text-danger">*</span></label>
                    <input id="description" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" maxlength="255" placeholder="e.g. Annual maintenance contract — office equipment">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="amount">Amount (PKR) <span class="text-danger">*</span></label>
                    <input type="number" min="1" step="0.01" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}">
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="committed_on">Commitment Date <span class="text-danger">*</span></label>
                    <input type="date" id="committed_on" name="committed_on" max="{{ now()->toDateString() }}" class="form-control" value="{{ old('committed_on', now()->toDateString()) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="authority_reference">Sanction / Approval Reference <span class="text-danger">*</span></label>
                    <input id="authority_reference" name="authority_reference" class="form-control @error('authority_reference') is-invalid @enderror" value="{{ old('authority_reference') }}" maxlength="100">
                    @error('authority_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="document">Sanction Document</label>
                    <input type="file" id="document" name="document" class="form-control">
                </div>
            </div>
            <div class="card-footer bg-white text-end py-3"><button class="btn btn-primary"><i class="bi bi-bookmark-check me-1"></i>Record Commitment</button></div>
        </div>
    </form>
@endsection
