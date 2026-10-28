@extends('layouts.app')
@section('title', 'Hostel Budgets')

@section('content')
    <div class="page-head">
        <div>
            <h1>Budget Management</h1>
            <p>Budget issued to each youth hostel, funded from the central Budget &amp; Planning module.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @include('budget.partials.fy-picker')
            @can('create', App\Models\JawanMarkazBudget::class)
                <a href="{{ route('hostel.budgets.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Budget</a>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Reference</th><th>Hostel</th><th>District</th><th>Issue Date</th><th class="text-end">Original</th><th class="text-end">Revisions</th><th class="text-end">Revised</th><th class="text-end">Expenditure</th><th class="text-end">Commitments</th><th class="text-end">Remaining</th><th class="text-end">Available to Commit</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($budgets as $b)
                    @php($f = $b->figures())
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('hostel.budgets.show', $b) }}" class="fw-medium">{{ $b->reference_no }}</a></td>
                        <td>{{ $b->hostel->name }}</td>
                        <td>{{ $b->hostel->district->name }}</td>
                        <td class="small text-nowrap">{{ $b->issue_date->format('d M Y') }}</td>
                        <td class="text-end">{{ number_format($b->original_amount) }}</td>
                        <td class="text-end">{{ $f['revisions'] ? ($f['revisions'] > 0 ? '+' : '') . number_format($f['revisions']) : '—' }}</td>
                        <td class="text-end">{{ $b->status === 'approved' ? number_format($f['allocated']) : '—' }}</td>
                        <td class="text-end">{{ number_format($f['paid']) }}</td>
                        <td class="text-end">{{ number_format($f['unpaid']) }}</td>
                        <td class="text-end">{{ $b->status === 'approved' ? number_format($f['remaining']) : '—' }}</td>
                        <td class="text-end fw-semibold">{{ $b->status === 'approved' ? number_format($f['available']) : '—' }}</td>
                        <td>@include('budget.partials.status', ['status' => $b->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="text-center text-muted-2 py-5">No hostel budgets for this financial year.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
