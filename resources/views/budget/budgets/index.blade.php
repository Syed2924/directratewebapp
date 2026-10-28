@extends('layouts.app')
@section('title', 'Directorate Budgets')

@section('content')
    <div class="page-head">
        <div>
            <h1>Overall Directorate Budget</h1>
            <p>The main budget record for the Directorate for each financial year.</p>
        </div>
        @if (auth()->user()->can('create', App\Models\Budget::class) && auth()->user()->scopeCoverage()->global)
            <a href="{{ route('budget.budgets.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Register Budget</a>
        @endif
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Financial Year</th><th>Reference</th><th>Funding Source</th><th class="text-end">Original</th><th class="text-end">Revisions</th><th class="text-end">Revised Budget</th><th class="text-end">Allocated</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($budgets as $b)
                    @php($rev = $b->revisedAmount())
                    <tr>
                        <td class="fw-medium">{{ $b->financialYear->label }}</td>
                        <td><a href="{{ route('budget.budgets.show', $b) }}" class="fw-medium">{{ $b->reference_no }}</a></td>
                        <td>{{ config("budget.funding_sources.{$b->funding_source}") }}</td>
                        <td class="text-end">{{ number_format($b->original_amount) }}</td>
                        <td class="text-end">{{ $b->revisionTotal() ? ($b->revisionTotal() > 0 ? '+' : '') . number_format($b->revisionTotal()) : '—' }}</td>
                        <td class="text-end fw-semibold">{{ number_format($rev) }}</td>
                        <td class="text-end">{{ number_format($b->allocatedAmount()) }}</td>
                        <td>@include('budget.partials.status', ['status' => $b->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted-2 py-5">No Directorate budget has been registered yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Amounts allocated to offices are part of the Directorate budget — not additional to it.</p>
@endsection
