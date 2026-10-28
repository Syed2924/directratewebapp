@extends('layouts.app')
@section('title', $expense->exists ? 'Edit Expense' : 'Submit Expense')

@section('content')
    <div class="page-head">
        <div>
            <h1>Expense Submission (Jawan Markaz Staff)</h1>
            <p>{{ $expense->exists ? 'Correct the expense and resubmit it.' : 'Enter the expense, attach the invoice and submit it to the Directorate.' }}</p>
        </div>
        <a href="{{ $expense->exists ? route('jawan-markaz.expenses.show', $expense) : route('jawan-markaz.expenses.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    @if ($expense->status === 'returned' && ($ret = $expense->approvals->where('action', 'returned')->last()))
        <div class="alert alert-warning"><strong>Returned for correction</strong> by {{ $ret->user->name ?? '—' }}: {{ $ret->remarks }}</div>
    @endif
    @if ($centers->isEmpty())
        <div class="alert alert-warning">You are not assigned to any active Jawan Markaz. Ask an administrator to assign your center.</div>
    @endif

    <form method="POST" enctype="multipart/form-data" novalidate action="{{ $expense->exists ? route('jawan-markaz.expenses.update', $expense) : route('jawan-markaz.expenses.store') }}">
        @csrf
        @if ($expense->exists) @method('PUT') @endif
        <div class="card">
            <div class="card-body p-4">
                <div class="section-title">Expense Details</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label d-flex justify-content-between" for="ref">Expense ID <span class="auto-tag">{{ $expense->exists ? 'Assigned' : 'Auto Generated' }}</span></label>
                        <input id="ref" class="form-control bg-light fw-medium" value="{{ $reference }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="center_id">Jawan Markaz <span class="text-danger">*</span></label>
                        <select id="center_id" name="center_id" class="form-select @error('center_id') is-invalid @enderror" @disabled($expense->exists)>
                            @foreach ($centers as $c)<option value="{{ $c->id }}" @selected(old('center_id', $expense->center_id) == $c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                        @error('center_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="expense_date">Expense Date <span class="text-danger">*</span></label>
                        <input type="date" id="expense_date" name="expense_date" max="{{ now()->toDateString() }}" class="form-control @error('expense_date') is-invalid @enderror" value="{{ old('expense_date', $expense->expense_date?->toDateString()) }}">
                        @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="category">Category <span class="text-danger">*</span></label>
                        <select id="category" name="category" class="form-select @error('category') is-invalid @enderror">
                            <option value="">Select category</option>
                            @foreach (config('jawan_markaz.expense_categories') as $k => $l)<option value="{{ $k }}" @selected(old('category', $expense->category) === $k)>{{ $l }}</option>@endforeach
                        </select>
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="description">Description <span class="text-danger">*</span></label>
                        <input id="description" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description', $expense->description) }}" maxlength="500" placeholder="e.g. Stationery and office supplies for center">
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="supplier_name">Payee / Supplier <span class="text-danger">*</span></label>
                        <input id="supplier_name" name="supplier_name" class="form-control @error('supplier_name') is-invalid @enderror" value="{{ old('supplier_name', $expense->supplier_name) }}" maxlength="150">
                        @error('supplier_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="invoice_number">Invoice Number <span class="text-danger">*</span></label>
                        <input id="invoice_number" name="invoice_number" class="form-control @error('invoice_number') is-invalid @enderror" value="{{ old('invoice_number', $expense->invoice_number) }}" maxlength="60">
                        @error('invoice_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="invoice_date">Invoice Date <span class="text-danger">*</span></label>
                        <input type="date" id="invoice_date" name="invoice_date" max="{{ now()->toDateString() }}" class="form-control @error('invoice_date') is-invalid @enderror" value="{{ old('invoice_date', $expense->invoice_date?->toDateString()) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="requested_amount">Amount (PKR) <span class="text-danger">*</span></label>
                        <input type="number" min="1" step="0.01" id="requested_amount" name="requested_amount" class="form-control @error('requested_amount') is-invalid @enderror" value="{{ old('requested_amount', $expense->requested_amount) }}">
                        @error('requested_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @if ($expense->exists)
                        <div class="col-md-8">
                            <label class="form-label">Submitted By</label>
                            <input class="form-control bg-light" value="{{ auth()->user()->name }} (recorded automatically)" readonly>
                        </div>
                    @endif
                </div>

                <div class="section-title">Invoice &amp; Supporting Documents</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Invoice <span class="text-danger">*</span> <span class="text-muted-2 fw-normal small">(required to submit)</span></label>
                        @include('activity.partials.dropzone', ['name' => 'invoice_file', 'id' => 'invoice_file', 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'PDF, JPG, PNG · Max ' . (config('activity.upload_max_kb') / 1024) . ' MB'])
                        @error('invoice_file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @error('invoice')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Supporting Documents <span class="text-muted-2 fw-normal">(optional)</span></label>
                        @include('activity.partials.dropzone', ['name' => 'supporting[]', 'id' => 'supporting', 'multiple' => true])
                    </div>
                    @if ($expense->exists && $expense->documents->isNotEmpty())
                        <div class="col-12 small"><strong>Already attached:</strong> {{ $expense->documents->map(fn ($d) => $d->kindLabel() . ': ' . $d->original_name)->implode(' · ') }}</div>
                    @endif
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between flex-wrap gap-2 py-3">
                <span class="small text-muted-2 align-self-center"><i class="bi bi-info-circle me-1"></i>Submission date and submitting user are recorded automatically. Possible duplicate invoices are flagged.</span>
                <div class="d-flex gap-2">
                    <button name="intent" value="draft" class="btn btn-outline-secondary">Save Draft</button>
                    <button name="intent" value="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>{{ $expense->status === 'returned' ? 'Resubmit Expense' : 'Submit Expense' }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection
