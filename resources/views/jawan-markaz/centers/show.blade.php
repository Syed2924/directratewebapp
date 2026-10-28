@extends('layouts.app')
@section('title', $center->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>Jawan Markaz Center Profile</h1>
            <p>{{ $center->name }} · {{ $center->district->name }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('jawan-markaz.centers.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Centers</a>
            @can('organization.manage')<a href="{{ route('jawan-markaz.centers.edit', $center) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-body d-flex gap-3 align-items-center">
                    <span class="stat-icon tint-green" style="width:64px;height:64px;flex-basis:64px;font-size:1.6rem"><i class="bi bi-building"></i></span>
                    <div>
                        <div class="fs-5 fw-semibold">{{ $center->name }}</div>
                        <div class="small text-muted-2">District: {{ $center->district->name }} · @include('partials.status-badge', ['status' => $center->is_active ? 'active' : 'inactive'])</div>
                    </div>
                </div>
                <div class="card-body border-top">
                    <dl class="row kv mb-0">
                        <dt class="col-sm-3">Location</dt><dd class="col-sm-9">{{ $center->location ?? '—' }}</dd>
                        <dt class="col-sm-3">Center In-charge</dt><dd class="col-sm-9">{{ $center->incharge->name ?? '—' }}</dd>
                        <dt class="col-sm-3">Contact</dt><dd class="col-sm-9">{{ collect([$center->phone, $center->email])->filter()->implode(' · ') ?: '—' }}</dd>
                        <dt class="col-sm-3">Remarks</dt><dd class="col-sm-9 mb-0">{{ $center->remarks ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">Budget Summary
                    @can('create', App\Models\JawanMarkazBudget::class)<a href="{{ route('jawan-markaz.budgets.create', ['center' => $center->id]) }}" class="small fw-normal">Add budget</a>@endcan</div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Financial Year</th><th>Reference</th><th class="text-end">Allocated</th><th class="text-end">Approved</th><th class="text-end">Paid</th><th class="text-end">Remaining</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($budgets as $b)
                            @php($f = $b->figures())
                            <tr>
                                <td>{{ $b->financialYear->label }}</td>
                                <td><a href="{{ route('jawan-markaz.budgets.show', $b) }}" class="fw-medium">{{ $b->reference_no }}</a></td>
                                <td class="text-end">{{ number_format($b->status === 'approved' ? $f['allocated'] : $b->allocated_amount) }}</td>
                                <td class="text-end">{{ number_format($f['approved']) }}</td>
                                <td class="text-end">{{ number_format($f['paid']) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($f['available']) }}</td>
                                <td>@include('budget.partials.status', ['status' => $b->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted-2 py-4">No budgets allocated to this center yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">Recent Expenses
                    <a href="{{ route('jawan-markaz.expenses.index', ['center' => $center->id]) }}" class="small fw-normal">View all</a></div>
                <div class="table-responsive">
                    <table class="table">
                        <thead><tr><th>Expense ID</th><th>Date</th><th>Category</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($expenses as $e)
                            <tr>
                                <td><a href="{{ route('jawan-markaz.expenses.show', $e) }}" class="fw-medium">{{ $e->expense_number }}</a></td>
                                <td class="small">{{ $e->expense_date->format('d M Y') }}</td>
                                <td class="small">{{ $e->categoryLabel() }}</td>
                                <td class="text-end">{{ number_format($e->requested_amount) }}</td>
                                <td>@include('jawan-markaz.partials.status', ['status' => $e->status])</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted-2 py-4">No expenses yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header">Center Expense Tracking</div>
                <div class="card-body row g-2 text-center">
                    @foreach ([['submitted', 'Submitted', ['submitted', 'under_review']], ['approved', 'Approved', ['approved', 'partially_paid', 'paid']], ['returned', 'Returned', ['returned']], ['rejected', 'Rejected', ['rejected']]] as [$k, $label, $statuses])
                        <div class="col-6"><div class="p-2 rounded border"><div class="fs-4 fw-bold">{{ collect($statuses)->sum(fn ($s) => $counts[$s] ?? 0) }}</div><div class="small text-muted-2">{{ $label }}</div></div></div>
                    @endforeach
                </div>
            </div>
            <div class="card">
                <div class="card-header">Assigned Staff</div>
                <ul class="list-group list-group-flush">
                    @forelse ($staff as $s)
                        <li class="list-group-item d-flex align-items-center gap-2"><span class="avatar">{{ $s->initials() }}</span>
                            <div><div class="small fw-medium">{{ $s->name }}</div><div class="small-2 text-muted-2">{{ $s->primaryRoleName() }}</div></div></li>
                    @empty
                        <li class="list-group-item small text-muted-2">No staff assigned. Assign users to this center under Users → scope.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
