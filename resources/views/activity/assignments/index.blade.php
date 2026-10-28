@extends('layouts.app')
@section('title', 'My Assignments')

@section('content')
    <div class="page-head">
        <div>
            <h1>My Assignments</h1>
            <p>Everything in the activity workflow that is waiting for you.</p>
        </div>
    </div>

    @php($total = collect($queues)->sum(fn ($q) => $q[3]->count()) + collect($activityQueues)->sum(fn ($q) => $q[3]->count()))
    @if ($total === 0)
        <div class="card"><div class="card-body text-center text-muted-2 py-5"><i class="bi bi-check2-circle fs-2 d-block mb-2 text-primary"></i>Nothing is waiting for you right now.</div></div>
    @endif

    <div class="row g-3">
        @foreach ($queues as $key => [$title, $icon, $hint, $items])
            @continue($items->isEmpty())
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center gap-2"><i class="bi {{ $icon }} text-primary"></i>{{ $title }}<span class="badge rounded-pill text-bg-light ms-1">{{ $items->count() }}</span>
                        <span class="ms-auto small fw-normal text-muted-2 d-none d-md-inline">{{ $hint }}</span></div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Tracking No.</th><th>Applicant</th><th>Activity Title</th><th>District</th><th>Officer</th><th>Status</th><th>Waiting since</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($items as $p)
                                <tr>
                                    <td class="text-nowrap"><a href="{{ route('activity.proposals.show', $p) }}" class="fw-medium">{{ $p->tracking_no }}</a></td>
                                    <td>{{ $p->applicant_name }}</td>
                                    <td>{{ $p->title }}</td>
                                    <td>{{ $p->district->name }}</td>
                                    <td class="small">{{ $p->assignee->name ?? '—' }}</td>
                                    <td>@include('activity.partials.badge', ['type' => 'proposal', 'status' => $p->status])</td>
                                    <td class="small text-nowrap" title="{{ $p->updated_at->format('d M Y H:i') }}">{{ $p->updated_at->diffForHumans() }}</td>
                                    <td class="text-end"><a href="{{ $key === 'drafts' ? route('activity.proposals.edit', $p) : route('activity.proposals.show', $p) }}" class="btn btn-sm btn-primary">Open</a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach

        @foreach ($activityQueues as $key => [$title, $icon, $hint, $items])
            @continue($items->isEmpty())
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center gap-2"><i class="bi {{ $icon }} text-primary"></i>{{ $title }}<span class="badge rounded-pill text-bg-light ms-1">{{ $items->count() }}</span>
                        <span class="ms-auto small fw-normal text-muted-2 d-none d-md-inline">{{ $hint }}</span></div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Tracking No.</th><th>Activity Title</th><th>District</th><th>Status</th><th>Updated</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($items as $a)
                                <tr>
                                    <td class="text-nowrap"><a href="{{ route('activity.activities.show', $a) }}" class="fw-medium">{{ $a->proposal->tracking_no }}</a></td>
                                    <td>{{ $a->proposal->title }}</td>
                                    <td>{{ $a->district->name }}</td>
                                    <td>@include('activity.partials.badge', ['type' => 'activity', 'status' => $a->status])</td>
                                    <td class="small text-nowrap">{{ $a->updated_at->diffForHumans() }}</td>
                                    <td class="text-end"><a href="{{ route('activity.activities.show', [$a, 'tab' => $key === 'verify' ? 'report' : 'details']) }}" class="btn btn-sm btn-primary">Open</a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
