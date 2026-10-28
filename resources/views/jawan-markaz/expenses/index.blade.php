@extends('layouts.app')
@section('title', $reviewQueue ? 'Expense Approvals' : 'Jawan Markaz Expenses')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $reviewQueue ? 'Expense Approval Queue' : 'Expense List' }}</h1>
            <p>{{ $reviewQueue ? 'Submitted expenses waiting for a Directorate decision, oldest first.' : 'All expenses from the centers in your scope.' }}</p>
        </div>
        @can('create', App\Models\JawanMarkazExpense::class)
            <a href="{{ route('jawan-markaz.expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Submit Expense</a>
        @endcan
    </div>

    <div class="card">
        <form class="card-body border-bottom row g-2" method="GET">
            <div class="col-md-3"><input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Expense ID, supplier, invoice…" aria-label="Search"></div>
            <div class="col-6 col-md-2">
                <select name="center" class="form-select" aria-label="Center">
                    <option value="">All Centers</option>
                    @foreach ($centers as $c)<option value="{{ $c->id }}" @selected(request('center') == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            @unless ($reviewQueue)
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select" aria-label="Status">
                        <option value="">All Status</option>
                        @foreach (config('jawan_markaz.expense_statuses') as $k => [$l])<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
                    </select>
                </div>
            @endunless
            <div class="col-6 col-md-2"><input type="date" name="from" value="{{ request('from') }}" class="form-control" aria-label="From date"></div>
            <div class="col-6 col-md-2"><input type="date" name="to" value="{{ request('to') }}" class="form-control" aria-label="To date"></div>
            <div class="col-md-1 d-grid"><button class="btn btn-outline-primary">Search</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Expense ID</th><th>Center</th><th>Category</th><th>Supplier / Invoice</th><th class="text-end">Amount (PKR)</th><th>Submitted</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse ($expenses as $e)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('jawan-markaz.expenses.show', $e) }}" class="fw-medium">{{ $e->expense_number }}</a></td>
                        <td>{{ $e->center->name }}</td>
                        <td class="small">{{ $e->categoryLabel() }}</td>
                        <td class="small">{{ $e->supplier_name }}<div class="text-muted-2">{{ $e->invoice_number }}</div></td>
                        <td class="text-end">{{ number_format($e->requested_amount) }}</td>
                        <td class="small text-nowrap">{{ $e->submitted_at?->format('d M Y') ?? '—' }}<div class="text-muted-2">{{ $e->submitter->name ?? '' }}</div></td>
                        <td>@include('jawan-markaz.partials.status', ['status' => $e->status])</td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="Actions for {{ $e->expense_number }}"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><a class="dropdown-item" href="{{ route('jawan-markaz.expenses.show', $e) }}"><i class="bi bi-eye me-2"></i>{{ $e->awaitingDecision() && auth()->user()->can('decide', $e) ? 'Review' : 'View' }}</a></li>
                                    @can('update', $e)<li><a class="dropdown-item" href="{{ route('jawan-markaz.expenses.edit', $e) }}"><i class="bi bi-pencil me-2"></i>Edit</a></li>@endcan
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted-2 py-5">{{ $reviewQueue ? 'No expenses are waiting for review.' : 'No expenses match these filters.' }}</td></tr>
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
