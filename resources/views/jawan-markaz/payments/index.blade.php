@extends('layouts.app')
@section('title', 'Jawan Markaz Payments')

@section('content')
    <div class="page-head">
        <div>
            <h1>Payment Management</h1>
            <p>Approved expenses awaiting payment, and the record of payments made.</p>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">Approved, Awaiting Payment
            <span class="small fw-normal">Outstanding total: <strong>@pkr($awaiting->sum(fn ($e) => $e->outstanding()))</strong></span></div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Expense ID</th><th>Center</th><th>Supplier</th><th>Approved On</th><th class="text-end">Approved</th><th class="text-end">Paid</th><th class="text-end">Outstanding</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($awaiting as $e)
                    <tr>
                        <td><a href="{{ route('jawan-markaz.expenses.show', $e) }}" class="fw-medium">{{ $e->expense_number }}</a></td>
                        <td>{{ $e->center->name }}</td>
                        <td class="small">{{ $e->supplier_name }}</td>
                        <td class="small text-nowrap">{{ $e->decided_at?->format('d M Y') }}<div class="text-muted-2">{{ $e->reviewer->name ?? '' }}</div></td>
                        <td class="text-end">{{ number_format($e->approved_amount) }}</td>
                        <td class="text-end">{{ number_format($e->paidAmount()) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($e->outstanding()) }}</td>
                        <td>@include('jawan-markaz.partials.status', ['status' => $e->status])</td>
                        <td class="text-end">
                            @if (Gate::allows('pay', $e))<a href="{{ route('jawan-markaz.expenses.show', $e) }}#p-amount" class="btn btn-sm btn-primary">Record Payment</a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted-2 py-4">No approved expenses are awaiting payment.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Payments List</div>
        <form class="card-body border-bottom row g-2" method="GET">
            <div class="col-md-3">
                <select name="center" class="form-select" aria-label="Center"><option value="">All Centers</option>
                    @foreach ($centers as $c)<option value="{{ $c->id }}" @selected(request('center') == $c->id)>{{ $c->name }}</option>@endforeach</select>
            </div>
            <div class="col-md-3">
                <select name="method" class="form-select" aria-label="Method"><option value="">All Methods</option>
                    @foreach (config('jawan_markaz.payment_methods') as $k => $l)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $l }}</option>@endforeach</select>
            </div>
            <div class="col-6 col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="From"></div>
            <div class="col-6 col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="To"></div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Search</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Payment ID</th><th>Expense ID</th><th>Center</th><th class="text-end">Amount (PKR)</th><th>Payment Date</th><th>Method</th><th>Reference</th><th>Recorded By</th><th>Central Entry</th></tr></thead>
                <tbody>
                @forelse ($payments as $p)
                    <tr>
                        <td class="fw-medium">{{ $p->reference_no }}</td>
                        <td><a href="{{ route('jawan-markaz.expenses.show', $p->expense) }}">{{ $p->expense->expense_number }}</a></td>
                        <td>{{ $p->center->name }}</td>
                        <td class="text-end">{{ number_format($p->amount, 2) }}</td>
                        <td class="small">{{ $p->payment_date->format('d M Y') }}</td>
                        <td class="small">{{ $p->methodLabel() }}</td>
                        <td class="small">{{ $p->transaction_reference }}</td>
                        <td class="small">{{ $p->recorder->name ?? '—' }}</td>
                        <td class="small">{{ $p->transaction->reference_no ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted-2 py-4">No payments recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
            <span class="small text-muted-2">Showing {{ $payments->firstItem() ?? 0 }}–{{ $payments->lastItem() ?? 0 }} of {{ $payments->total() }}</span>
            {{ $payments->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
