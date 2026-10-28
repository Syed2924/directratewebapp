@extends('layouts.app')
@section('title', 'Petty Cash — ' . $fund->office->name)

@php($canRecord = auth()->user()->can('budget.record_payment'))
@section('content')
    <div class="page-head">
        <div>
            <h1>Form C — Petty Cash Management</h1>
            <p>{{ $fund->office->name }} · Custodian: {{ $fund->custodian->name ?? '—' }}</p>
        </div>
        <a href="{{ route('budget.petty-cash.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>All Funds</a>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            @if ($canRecord)
                <div class="card mb-3">
                    <div class="card-header">Petty Cash Voucher</div>
                    <form method="POST" action="{{ route('budget.petty-cash.vouchers.store', $fund) }}" enctype="multipart/form-data" class="card-body row g-3" novalidate>
                        @csrf
                        @php($vtype = old('type', 'expense'))
                        <div class="col-md-4">
                            <label class="form-label d-flex justify-content-between" for="vno">Voucher No. <span class="auto-tag">Auto Generated</span></label>
                            <input id="vno" class="form-control bg-light fw-medium" value="{{ $reference }}" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="type">Voucher Type</label>
                            <select id="type" name="type" class="form-select">
                                <option value="expense" @selected($vtype === 'expense')>Expense (cash paid)</option>
                                <option value="replenishment" @selected($vtype === 'replenishment')>Replenishment (cash received)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="voucher_date">Date</label>
                            <input type="date" id="voucher_date" name="voucher_date" max="{{ now()->toDateString() }}" class="form-control @error('voucher_date') is-invalid @enderror" value="{{ old('voucher_date', now()->toDateString()) }}">
                            @error('voucher_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4" data-type="expense">
                            <label class="form-label" for="category">Expense Category</label>
                            <select id="category" name="category" class="form-select @error('category') is-invalid @enderror">
                                <option value="">Select category</option>
                                @foreach (config('budget.petty_cash_categories') as $k => $l)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $l }}</option>@endforeach
                            </select>
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="amount">Amount (PKR)</label>
                            <input type="number" min="1" id="amount" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}">
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4" data-type="expense">
                            <label class="form-label" for="payee">Payee / Supplier</label>
                            <input id="payee" name="payee" class="form-control @error('payee') is-invalid @enderror" value="{{ old('payee') }}" maxlength="150">
                            @error('payee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4" data-type="replenishment">
                            <label class="form-label" for="replenishment_reference">Replenishment Reference</label>
                            <input id="replenishment_reference" name="replenishment_reference" class="form-control @error('replenishment_reference') is-invalid @enderror" value="{{ old('replenishment_reference') }}" maxlength="100" placeholder="Cheque / authorization no.">
                            @error('replenishment_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="description">Description &amp; Purpose</label>
                            <input id="description" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" maxlength="255" placeholder="e.g. Stationery and printing supplies">
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="payment_reference">Payment Reference</label>
                            <input id="payment_reference" name="payment_reference" class="form-control" value="{{ old('payment_reference') }}" maxlength="100" placeholder="Optional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="receipt">Receipt / Document</label>
                            <input type="file" id="receipt" name="receipt" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="v-remarks">Remarks</label>
                            <input id="v-remarks" name="remarks" class="form-control" value="{{ old('remarks') }}" maxlength="1000">
                        </div>
                        <div class="col-12 text-end"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Record Voucher</button></div>
                    </form>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-header">Vouchers</div>
                @error('decision')<div class="alert alert-danger m-3 py-2">{{ $message }}</div>@enderror
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Voucher</th><th>Date</th><th>Description</th><th>Category</th><th class="text-end">Amount</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @forelse ($fund->vouchers as $v)
                            <tr>
                                <td class="fw-medium text-nowrap">{{ $v->voucher_no }}@if ($v->documents->isNotEmpty())
                                        <a href="{{ route('budget.documents.show', [$v->documents->first(), 'inline' => 1]) }}" target="_blank" title="Receipt" class="ms-1"><i class="bi bi-paperclip"></i></a>@endif</td>
                                <td class="small text-nowrap">{{ $v->voucher_date->format('d M Y') }}</td>
                                <td>{{ $v->description }}<div class="small-2 text-muted-2">{{ $v->payee ?? $v->replenishment_reference }} · by {{ $v->submitter->name ?? '—' }}</div></td>
                                <td class="small">{{ $v->categoryLabel() }}</td>
                                <td class="text-end text-nowrap {{ $v->type === 'replenishment' ? 'text-primary' : '' }}">{{ $v->type === 'replenishment' ? '+' : '−' }} {{ number_format($v->amount) }}</td>
                                <td>@include('budget.partials.status', ['status' => $v->status])</td>
                                <td class="text-end">
                                    @php($vd = Gate::inspect('decide', $v))
                                    @if ($vd->allowed())
                                        <form method="POST" action="{{ route('budget.workflow.decide', ['voucher', $v->id]) }}" class="d-flex gap-1 justify-content-end" data-reject-prompt>
                                            @csrf <input type="hidden" name="remarks">
                                            <button name="decision" value="verify" class="btn btn-sm btn-primary">Verify</button>
                                            <button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                                        </form>
                                    @elseif ($v->status === 'rejected' && ($rj = $v->approvals->where('action', 'rejected')->last()))
                                        <span class="small-2 text-muted-2" title="{{ $rj->remarks }}">{{ \Illuminate\Support\Str::limit($rj->remarks, 30) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted-2 py-4">No vouchers yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top small text-muted-2"><i class="bi bi-shield-check me-1"></i>Vouchers cannot be edited or deleted. A wrong voucher is rejected (with a reason) and entered again, so every transaction stays on record.</div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header">Petty Cash Summary</div>
                <ul class="list-group list-group-flush">
                    @foreach ([['Opening Balance', $totals['opening'], 'bi-box-arrow-in-right'], ['Total Received', $totals['received'], 'bi-plus-circle'], ['Total Paid', $totals['paid'], 'bi-dash-circle'], ['Closing Cash Balance', $totals['balance'], 'bi-cash-stack']] as [$label, $value, $icon])
                        <li class="list-group-item d-flex align-items-center gap-3 {{ $loop->last ? 'fw-semibold' : '' }}">
                            <span class="stat-icon tint-green" style="width:34px;height:34px;flex-basis:34px;font-size:.95rem"><i class="bi {{ $icon }}"></i></span>
                            <span class="flex-grow-1 small">{{ $label }}</span><span>@pkr($value)</span>
                        </li>
                    @endforeach
                </ul>
                <div class="card-body small text-muted-2 border-top">Closing = Opening + Received − Paid. Pending vouchers are included; rejected ones are not.
                    @if ($fund->limit)<br>Fund limit: @pkr($fund->limit)@endif</div>
            </div>

            <div class="card">
                <div class="card-header">Reconciliation</div>
                @if ($canRecord || auth()->user()->can('budget.review'))
                    <form method="POST" action="{{ route('budget.petty-cash.reconciliations.store', $fund) }}" class="card-body row g-2 border-bottom" novalidate>
                        @csrf
                        <div class="col-6">
                            <label class="form-label small" for="reconciled_on">Count Date</label>
                            <input type="date" id="reconciled_on" name="reconciled_on" max="{{ now()->toDateString() }}" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label small" for="physical_cash">Physical Cash</label>
                            <input type="number" min="0" step="0.01" id="physical_cash" name="physical_cash" class="form-control form-control-sm @error('physical_cash') is-invalid @enderror" value="{{ old('physical_cash') }}" placeholder="{{ $totals['balance'] }}">
                        </div>
                        <div class="col-12">
                            <input name="remarks" class="form-control form-control-sm @error('remarks') is-invalid @enderror" placeholder="Remarks (required if there is a difference)" value="{{ old('remarks') }}" aria-label="Remarks">
                            @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 d-grid"><button class="btn btn-sm btn-outline-primary">Record Cash Count</button></div>
                    </form>
                @endif
                <ul class="list-group list-group-flush">
                    @forelse ($fund->reconciliations as $r)
                        <li class="list-group-item small">
                            <div class="d-flex justify-content-between"><strong>{{ $r->reconciled_on->format('d M Y') }}</strong> @include('budget.partials.status', ['status' => $r->status])</div>
                            Book {{ number_format($r->book_balance) }} · Counted {{ number_format($r->physical_cash) }}
                            @if ($r->difference != 0)· <span class="text-danger">Diff {{ number_format($r->difference) }}</span>@endif
                            <div class="text-muted-2">{{ $r->reconciler->name ?? '—' }}{{ $r->remarks ? ' — ' . $r->remarks : '' }}</div>
                        </li>
                    @empty
                        <li class="list-group-item small text-muted-2">No cash counts recorded.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
    @include('budget.partials.reject-prompt')
@endsection

@push('scripts')
    <script>
        (function () {
            var t = document.getElementById('type');
            if (!t) return;
            function sync() { document.querySelectorAll('[data-type]').forEach(function (el) { el.hidden = el.dataset.type !== t.value; }); }
            t.addEventListener('change', sync); sync();
        })();
    </script>
@endpush
