@extends('layouts.app')
@section('title', 'Jawan Markaz Centers')

@section('content')
    <div class="page-head">
        <div>
            <h1>Jawan Markaz Center Management</h1>
            <p>Centers, their status and budget position{{ $fy ? ' for ' . $fy->label : '' }}.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @include('budget.partials.fy-picker')
            @can('organization.manage')
                <a href="{{ route('admin.organization.index') }}" class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Add Center</a>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Center</th><th>District</th><th>In-charge</th><th>Status</th><th class="text-end">Allocated</th><th class="text-end">Approved</th><th class="text-end">Paid</th><th class="text-end">Available</th><th class="text-end">Pending</th></tr></thead>
                <tbody>
                @forelse ($summary['rows'] as $r)
                    @php($c = $r['center'])
                    <tr>
                        <td><a href="{{ route('jawan-markaz.centers.show', $c) }}" class="fw-medium">{{ $c->name }}</a><div class="small-2 text-muted-2">{{ $c->location ?? '' }}</div></td>
                        <td>{{ $c->district->name }}</td>
                        <td class="small">{{ $c->incharge->name ?? '—' }}</td>
                        <td>@include('partials.status-badge', ['status' => $c->is_active ? 'active' : 'inactive'])</td>
                        <td class="text-end">{{ $r['budget'] ? number_format($r['allocated']) : '—' }}</td>
                        <td class="text-end">{{ number_format($r['approved']) }}</td>
                        <td class="text-end">{{ number_format($r['paid']) }}</td>
                        <td class="text-end fw-semibold">{{ $r['budget'] ? number_format($r['available']) : '—' }}</td>
                        <td class="text-end">{{ $r['pending_count'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted-2 py-5">No centers in your scope.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted-2 mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Centers come from the shared Organization Units list, which is also used to assign staff to their center.</p>
@endsection
