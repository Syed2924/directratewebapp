@extends('layouts.app')
@section('title', 'Budget Allocations')

@php($s = $summary)
@section('content')
    <div class="page-head">
        <div>
            <h1>District / Office Budget Allocation</h1>
            <p>How the Directorate budget is distributed across offices and budget heads.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @include('budget.partials.fy-picker')
            @can('create', App\Models\BudgetAllocation::class)
                <a href="{{ route('budget.allocations.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Allocation</a>
            @endcan
        </div>
    </div>

    @if ($s['revised'] !== null)
        <div class="card mb-3">
            <div class="summary-strip">
                <div><div class="k">Revised Directorate Budget</div><div class="v">@pkr($s['revised'])</div></div>
                <div><div class="k">Total Allocated</div><div class="v">@pkr($s['allocated'])</div></div>
                <div><div class="k">Not Yet Allocated</div><div class="v">@pkr($s['unallocated'])</div></div>
                <div><div class="k">Committed</div><div class="v">@pkr($s['committed'])</div></div>
                <div><div class="k">Spent</div><div class="v">@pkr($s['spent'])</div></div>
            </div>
            <div class="px-3 pb-3 small text-muted-2"><i class="bi bi-info-circle me-1"></i>“Not yet allocated” is budget not assigned in this plan — not cash in the bank.</div>
        </div>
    @endif

    <div class="card">
        <div class="card-header">District / Office Allocation Summary {{ $fy ? '— ' . $fy->label : '' }}</div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Reference</th><th>Office</th><th>Budget Head</th><th class="text-end">Planned</th><th class="text-end">Approved</th><th class="text-end">Transfers In</th><th class="text-end">Transfers Out</th><th class="text-end">Revised</th><th class="text-end">Committed</th><th class="text-end">Spent</th><th class="text-end">Remaining</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($s['allocations'] as $a)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('budget.allocations.show', $a) }}" class="fw-medium">{{ $a->reference_no }}</a></td>
                        <td>{{ $a->office->name }}</td>
                        <td class="small">{{ $a->headLabel() }}</td>
                        <td class="text-end">{{ number_format($a->planned_amount) }}</td>
                        <td class="text-end">{{ $a->status === 'approved' ? number_format($a->approved_amount) : '—' }}</td>
                        <td class="text-end">{{ $a->transfersInTotal() ? number_format($a->transfersInTotal()) : '—' }}</td>
                        <td class="text-end">{{ $a->transfersOutTotal() ? number_format($a->transfersOutTotal()) : '—' }}</td>
                        <td class="text-end fw-semibold">{{ $a->status === 'approved' ? number_format($a->revised()) : '—' }}</td>
                        <td class="text-end">{{ number_format($a->committed()) }}</td>
                        <td class="text-end">{{ number_format($a->spent()) }}</td>
                        <td class="text-end {{ $a->available() < 0 ? 'text-danger fw-semibold' : '' }}">{{ $a->status === 'approved' ? number_format($a->available()) : '—' }}</td>
                        <td>@include('budget.partials.status', ['status' => $a->status])</td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="text-center text-muted-2 py-5">No allocations for this financial year.</td></tr>
                @endforelse
                </tbody>
                @if ($s['allocations']->isNotEmpty())
                    @php($ok = $s['allocations']->where('status', 'approved'))
                    <tfoot><tr class="fw-semibold">
                        <td colspan="3">Total (approved)</td>
                        <td class="text-end">{{ number_format($s['allocations']->sum('planned_amount')) }}</td>
                        <td class="text-end">{{ number_format($ok->sum('approved_amount')) }}</td>
                        <td class="text-end">{{ number_format($ok->sum(fn ($a) => $a->transfersInTotal())) }}</td>
                        <td class="text-end">{{ number_format($ok->sum(fn ($a) => $a->transfersOutTotal())) }}</td>
                        <td class="text-end">{{ number_format($s['allocated']) }}</td>
                        <td class="text-end">{{ number_format($s['committed']) }}</td>
                        <td class="text-end">{{ number_format($s['spent']) }}</td>
                        <td class="text-end">{{ number_format($s['available']) }}</td>
                        <td></td>
                    </tr></tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
