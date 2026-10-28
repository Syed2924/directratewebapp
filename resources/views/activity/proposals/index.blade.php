@extends('layouts.app')
@section('title', 'Activity Proposals')

@section('content')
    <div class="page-head">
        <div>
            <h1>Activity Proposals</h1>
            <p>View, search and manage all activity proposals.</p>
        </div>
        @can('create', App\Models\ActivityProposal::class)
            <a href="{{ route('activity.proposals.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New Proposal</a>
        @endcan
    </div>

    @php($statuses = config('activity.proposal_statuses'))
    <div class="status-chips mb-3">
        <a href="{{ route('activity.proposals.index', request()->except('status', 'page')) }}" class="{{ request('status') ? '' : 'active' }}">All<span class="n">{{ $counts->sum() }}</span></a>
        @foreach ($statuses as $key => [$label])
            @if ($counts->has($key))
                <a href="{{ route('activity.proposals.index', ['status' => $key] + request()->except('status', 'page')) }}" class="{{ request('status') === $key ? 'active' : '' }}">{{ $label }}<span class="n">{{ $counts[$key] }}</span></a>
            @endif
        @endforeach
    </div>

    <div class="card">
        <form class="card-body border-bottom row g-2 align-items-end" method="GET">
            <div class="col-12 col-xl-3">
                <label class="form-label small text-muted-2 mb-1" for="q">Search</label>
                <div class="input-icon">
                    <i class="bi bi-search"></i>
                    <input type="search" id="q" name="q" value="{{ request('q') }}" class="form-control" placeholder="Tracking number, applicant, title…">
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small text-muted-2 mb-1" for="f-district">District</label>
                <select id="f-district" name="district" class="form-select">
                    <option value="">All Districts</option>
                    @foreach ($districts as $d)
                        <option value="{{ $d->id }}" @selected(request('district') == $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small text-muted-2 mb-1" for="f-category">Category</label>
                <select id="f-category" name="category" class="form-select">
                    <option value="">All Categories</option>
                    @foreach (config('activity.categories') as $key => $label)
                        <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small text-muted-2 mb-1" for="from">Received from</label>
                <input type="date" id="from" name="from" value="{{ request('from') }}" class="form-control">
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label small text-muted-2 mb-1" for="to">to</label>
                <input type="date" id="to" name="to" value="{{ request('to') }}" class="form-control">
            </div>
            @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <div class="col-12 col-xl-1 d-flex gap-2">
                <button class="btn btn-outline-primary flex-grow-1">Apply</button>
                @if (request()->hasAny(['q', 'district', 'category', 'from', 'to', 'status']))
                    <a href="{{ route('activity.proposals.index') }}" class="btn btn-light" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr><th>#</th><th>Tracking No.</th><th>Applicant / Organization</th><th>Activity Title</th><th>District</th><th>Officer</th><th>Status</th><th>Received</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                @forelse ($proposals as $p)
                    <tr>
                        <td class="text-muted-2 small">{{ $proposals->firstItem() + $loop->index }}</td>
                        <td class="text-nowrap"><a href="{{ route('activity.proposals.show', $p) }}" class="fw-medium">{{ $p->tracking_no }}</a></td>
                        <td>{{ $p->applicant_name }}</td>
                        <td>{{ $p->title }}<div class="small-2 text-muted-2">{{ $p->categoryLabel() }}</div></td>
                        <td>{{ $p->district->name }}</td>
                        <td class="small">{{ $p->assignee->name ?? '—' }}</td>
                        <td>@include('activity.partials.badge', ['type' => 'proposal', 'status' => $p->status])</td>
                        <td class="small text-nowrap">{{ $p->received_on->format('d M Y') }}</td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="Actions for {{ $p->tracking_no }}"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><a class="dropdown-item" href="{{ route('activity.proposals.show', $p) }}"><i class="bi bi-eye me-2"></i>View &amp; workflow</a></li>
                                    @can('update', $p)
                                        <li><a class="dropdown-item" href="{{ route('activity.proposals.edit', $p) }}"><i class="bi bi-pencil me-2"></i>Edit details</a></li>
                                    @endcan
                                </ul>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted-2 py-5">
                        @if (request()->hasAny(['q', 'district', 'category', 'from', 'to', 'status']))
                            No proposals match your filters.
                        @else
                            No proposals yet.
                            @can('create', App\Models\ActivityProposal::class)
                                <a href="{{ route('activity.proposals.create') }}">Register the first one</a>.
                            @endcan
                        @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2 border-top">
            <span class="small text-muted-2">Showing {{ $proposals->firstItem() ?? 0 }}–{{ $proposals->lastItem() ?? 0 }} of {{ $proposals->total() }}</span>
            {{ $proposals->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
