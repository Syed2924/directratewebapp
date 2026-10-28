@extends('layouts.app')
@section('title', 'Hostel Expenditure')

@section('content')
    <div class="page-head">
        <div>
            <h1>Hostel Expenditure</h1>
            <p>Expenses recorded against hostel budgets, with approval and payment status.</p>
        </div>
        @can('create', App\Models\HostelExpense::class)<a href="{{ route('hostel.expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Record Expense</a>@endcan
    </div>

    <div class="card">
        <form class="card-body border-bottom row g-2" method="GET">
            <div class="col-md-4"><select name="hostel" class="form-select" aria-label="Hostel"><option value="">All Hostels</option>@foreach ($hostels as $h)<option value="{{ $h->id }}" @selected(request('hostel') == $h->id)>{{ $h->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="category" class="form-select" aria-label="Category"><option value="">All Categories</option>@foreach (config('hostel.expense_categories') as $k => $l)<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="status" class="form-select" aria-label="Status"><option value="">All Status</option>@foreach (config('hostel.expense_statuses') as $k => [$l])<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Apply</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Expenditure ID</th><th>Hostel</th><th>Date</th><th>Category</th><th>Supplier / Invoice</th><th class="text-end">Amount</th><th>Approval</th><th>Payment</th><th>Recorded By</th></tr></thead>
                <tbody>
                @forelse ($expenses as $e)
                    <tr>
                        <td><a href="{{ route('hostel.expenses.show', $e) }}" class="fw-medium">{{ $e->expense_no }}</a></td>
                        <td class="small">{{ $e->hostel->name }}</td>
                        <td class="small text-nowrap">{{ $e->expense_date->format('d M Y') }}</td>
                        <td class="small">{{ $e->categoryLabel() }}</td>
                        <td class="small">{{ $e->supplier_name }}<div class="text-muted-2">{{ $e->invoice_number }}</div></td>
                        <td class="text-end">{{ number_format($e->amount) }}</td>
                        <td><span class="badge-status {{ ['Pending' => 'badge-review', 'Rejected' => 'badge-inactive', 'Approved' => 'badge-active'][$e->approvalStatus()] }}">{{ $e->approvalStatus() }}</span></td>
                        <td class="small">{{ $e->paymentStatus() }}</td>
                        <td class="small">{{ $e->submitter->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted-2 py-5">No expenditure recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
            <span class="small text-muted-2">Showing {{ $expenses->firstItem() ?? 0 }}–{{ $expenses->lastItem() ?? 0 }} of {{ $expenses->total() }}</span>
            {{ $expenses->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
