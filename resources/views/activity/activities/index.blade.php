@extends('layouts.app')
@section('title', 'Activities')

@section('content')
    <div class="page-head">
        <div>
            <h1>Activities</h1>
            <p>Approved proposals in implementation and completed activities.</p>
        </div>
    </div>

    <div class="card">
        <form class="card-body border-bottom row g-2 align-items-end" method="GET">
            <div class="col-md-5">
                <div class="input-icon">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search by tracking number, activity title…" aria-label="Search">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="district" class="form-select" aria-label="District">
                    <option value="">All Districts</option>
                    @foreach ($districts as $d)
                        <option value="{{ $d->id }}" @selected(request('district') == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select" aria-label="Status">
                    <option value="">All Status</option>
                    @foreach (config('activity.activity_statuses') as $key => [$label])
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Apply</button></div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>#</th><th>Tracking No.</th><th>Activity Title</th><th>District</th><th>Status</th><th>Date</th><th class="text-end">Participants</th><th class="text-end">Spent (PKR)</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse ($activities as $a)
                    <tr>
                        <td class="text-muted-2 small">{{ $activities->firstItem() + $loop->index }}</td>
                        <td class="text-nowrap"><a href="{{ route('activity.activities.show', $a) }}" class="fw-medium">{{ $a->proposal->tracking_no }}</a></td>
                        <td>{{ $a->proposal->title }}<div class="small-2 text-muted-2">{{ $a->proposal->applicant_name }}</div></td>
                        <td>{{ $a->district->name }}</td>
                        <td>@include('activity.partials.badge', ['type' => 'activity', 'status' => $a->status])</td>
                        <td class="small text-nowrap">{{ ($a->actual_start ?? $a->proposal->proposed_start)?->format('d M Y') ?? '—' }}</td>
                        <td class="text-end">{{ $a->participantsTotal() ?: '—' }}</td>
                        <td class="text-end">{{ $a->expenses_sum_amount ? number_format($a->expenses_sum_amount) : '—' }}</td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="Actions for {{ $a->proposal->tracking_no }}"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><a class="dropdown-item" href="{{ route('activity.activities.show', $a) }}"><i class="bi bi-eye me-2"></i>Open</a></li>
                                    <li><a class="dropdown-item" href="{{ route('activity.activities.report', $a) }}" target="_blank"><i class="bi bi-printer me-2"></i>Print report</a></li>
                                    <li><a class="dropdown-item" href="{{ route('activity.proposals.show', $a->proposal) }}"><i class="bi bi-file-earmark-text me-2"></i>View proposal</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted-2 py-5">No activities yet. An activity record is created automatically when a proposal is approved.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
            <span class="small text-muted-2">Showing {{ $activities->firstItem() ?? 0 }}–{{ $activities->lastItem() ?? 0 }} of {{ $activities->total() }}</span>
            {{ $activities->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
