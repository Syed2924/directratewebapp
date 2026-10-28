@extends('layouts.app')
@section('title', 'Record Hostel Expense')

@section('content')
    <div class="page-head">
        <div>
            <h1>Expenditure Form</h1>
            <p>Record a hostel expense. It is charged to the hostel's approved budget once an officer approves it.</p>
        </div>
        <a href="{{ route('hostel.expenses.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" enctype="multipart/form-data" action="{{ route('hostel.expenses.store') }}" novalidate>
        @csrf
        <div class="card">
            <div class="card-body p-4 row g-3">
                <div class="col-md-4">
                    <label class="form-label d-flex justify-content-between" for="ref">Expenditure ID <span class="auto-tag">Auto Generated</span></label>
                    <input id="ref" class="form-control bg-light fw-medium" value="{{ $reference }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="hostel_id">Hostel <span class="text-danger">*</span></label>
                    <select id="hostel_id" name="hostel_id" class="form-select @error('hostel_id') is-invalid @enderror">
                        @foreach ($hostels as $h)<option value="{{ $h->id }}" @selected(old('hostel_id') == $h->id)>{{ $h->name }}</option>@endforeach
                    </select>
                    @error('hostel_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="expense_date">Expense Date <span class="text-danger">*</span></label>
                    <input type="date" id="expense_date" name="expense_date" max="{{ now()->toDateString() }}" class="form-control @error('expense_date') is-invalid @enderror" value="{{ old('expense_date', now()->toDateString()) }}">
                    @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="category">Expense Category <span class="text-danger">*</span></label>
                    <select id="category" name="category" class="form-select">@foreach (config('hostel.expense_categories') as $k => $l)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $l }}</option>@endforeach</select>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="description">Description <span class="text-danger">*</span></label>
                    <input id="description" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" maxlength="500">
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="supplier_name">Supplier / Payee <span class="text-danger">*</span></label>
                    <input id="supplier_name" name="supplier_name" class="form-control @error('supplier_name') is-invalid @enderror" value="{{ old('supplier_name') }}" maxlength="150">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="invoice_number">Invoice / Voucher No. <span class="text-danger">*</span></label>
                    <input id="invoice_number" name="invoice_number" class="form-control @error('invoice_number') is-invalid @enderror" value="{{ old('invoice_number') }}" maxlength="60">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="amount">Expense Amount (PKR) <span class="text-danger">*</span></label>
                    <input type="number" min="1" step="0.01" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}">
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Supporting Invoice / Receipt <span class="text-danger">*</span></label>
                    @include('activity.partials.dropzone', ['name' => 'invoice_file', 'id' => 'invoice_file', 'accept' => '.pdf,.jpg,.jpeg,.png', 'hint' => 'PDF, JPG, PNG'])
                    @error('invoice_file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="5" class="form-control" maxlength="1000">{{ old('remarks') }}</textarea>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between py-3">
                <span class="small text-muted-2 align-self-center">Recorded by {{ auth()->user()->name }}. Approval and payment are recorded separately by other officers.</span>
                <button class="btn btn-primary"><i class="bi bi-receipt me-1"></i>Record Expense</button>
            </div>
        </div>
    </form>
@endsection
