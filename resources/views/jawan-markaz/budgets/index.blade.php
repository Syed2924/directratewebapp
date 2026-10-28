@extends('layouts.app')
@section('title', 'Jawan Markaz Budgets')

@section('content')
    <div class="page-head">
        <div>
            <h1>Budget Management</h1>
            <p>Budget allocated to each Jawan Markaz, funded from the central Budget &amp; Planning module.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @include('budget.partials.fy-picker')
            @can('create', App\Models\JawanMarkazBudget::class)
                <a href="{{ route('jawan-markaz.budgets.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add Budget</a>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Reference</th><th>Center</th><th>District</th><th>Issue Date</th><th>Authorization</th><th class="text-end">Allocated</th><th class="text-end">Approved Exp.</th><th class="text-end">Paid</th><th class="text-end">Remaining</th><th>Central Commitment</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($budgets as $b)
                    @php($f = $b->figures())
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('jawan-markaz.budgets.show', $b) }}" class="fw-medium">{{ $b->reference_no }}</a></td>
                        <td>{{ $b->center->name }}</td>
                        <td>{{ $b->center->district->name }}</td>
                        <td class="small text-nowrap">{{ $b->allocation_date->format('d M Y') }}</td>
                        <td class="small">{{ $b->authorization_reference }}</td>
                        <td class="text-end">{{ number_format($b->status === 'approved' ? $f['allocated'] : $b->allocated_amount) }}</td>
                        <td class="text-end">{{ number_format($f['approved']) }}</td>
                        <td class="text-end">{{ number_format($f['paid']) }}</td>
                        <td class="text-end fw-semibold">{{ $b->status === 'approved' ? number_format($f['available']) : '—' }}</td>
                        <td class="small">{{ $b->commitment->reference_no ?? '—' }}</td>
                        <td>@include('budget.partials.status', ['status' => $b->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-muted-2 py-5">No center budgets for this financial year.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
