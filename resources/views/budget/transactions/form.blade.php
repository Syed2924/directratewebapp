@extends('layouts.app')
@section('title', 'Record Expense')

@section('content')
    <div class="page-head">
        <div>
            <h1>Record Actual Expenditure</h1>
            <p>Each expense is charged to an allocation (or to a commitment within it) and then verified by another officer.</p>
        </div>
        <a href="{{ route('budget.transactions.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" enctype="multipart/form-data" action="{{ route('budget.transactions.store') }}" novalidate>
        @csrf
        <div class="card">
            <div class="card-body p-4">
                <div class="section-title">Charge To</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="commitment_id">Commitment <span class="text-muted-2 fw-normal">(if this pays an authorized obligation)</span></label>
                        <select id="commitment_id" name="commitment_id" class="form-select @error('commitment_id') is-invalid @enderror">
                            <option value="">— None —</option>
                            @foreach ($commitments as $c)
                                <option value="{{ $c->id }}" @selected(old('commitment_id', $selectedCommitment) == $c->id)>{{ $c->reference_no }} · {{ $c->office->name }} · {{ \Illuminate\Support\Str::limit($c->description, 40) }} (outstanding {{ number_format($c->outstanding()) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6" id="allocationField">
                        <label class="form-label" for="allocation_id">Allocation <span class="text-danger">*</span></label>
                        <select id="allocation_id" name="allocation_id" class="form-select @error('allocation_id') is-invalid @enderror">
                            <option value="">Select allocation</option>
                            @foreach ($allocations as $a)
                                <option value="{{ $a->id }}" @selected(old('allocation_id') == $a->id)>{{ $a->label() }} — available {{ number_format($a->available()) }}</option>
                            @endforeach
                        </select>
                        @error('allocation_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="section-title">Expense Details</div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label d-flex justify-content-between" for="ref">Reference <span class="auto-tag">Auto Generated</span></label>
                        <input id="ref" class="form-control bg-light fw-medium" value="{{ $reference }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="txn_date">Date <span class="text-danger">*</span></label>
                        <input type="date" id="txn_date" name="txn_date" max="{{ now()->toDateString() }}" class="form-control @error('txn_date') is-invalid @enderror" value="{{ old('txn_date', now()->toDateString()) }}">
                        @error('txn_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="category">Category <span class="text-danger">*</span></label>
                        <select id="category" name="category" class="form-select">
                            @foreach (config('budget.expense_categories') as $k => $l)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="description">Description <span class="text-danger">*</span></label>
                        <input id="description" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" maxlength="255">
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="amount">Amount (PKR) <span class="text-danger">*</span></label>
                        <input type="number" min="0.01" step="0.01" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}">
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="payee">Payee / Supplier</label>
                        <input id="payee" name="payee" class="form-control" value="{{ old('payee') }}" maxlength="150">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="payment_reference">Payment Reference</label>
                        <input id="payment_reference" name="payment_reference" class="form-control" value="{{ old('payment_reference') }}" maxlength="100" placeholder="Cheque / transfer no.">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Receipts &amp; Evidence</label>
                        @include('activity.partials.dropzone', ['name' => 'receipts[]', 'id' => 'receipts', 'multiple' => true])
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white text-end py-3"><button class="btn btn-primary"><i class="bi bi-receipt me-1"></i>Record Expense</button></div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            var c = document.getElementById('commitment_id'), a = document.getElementById('allocationField');
            function sync() { a.hidden = !!c.value; }
            c.addEventListener('change', sync); sync();
        })();
    </script>
@endpush
