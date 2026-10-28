@extends('layouts.app')
@section('title', 'Audit Entry #' . $log->id)

@section('content')
    <div class="page-head">
        <div>
            <h1>Audit Entry #{{ $log->id }}</h1>
            <p>{{ $log->eventLabel() }} · {{ $log->created_at->format('d M Y H:i:s') }}</p>
        </div>
        <a href="{{ url()->previous() === url()->current() ? route('admin.audit.index') : url()->previous() }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <dl class="kv mb-0">
                        <dt>Event</dt><dd><code>{{ $log->event }}</code> — {{ $log->eventLabel() }}</dd>
                        <dt>Outcome</dt><dd>@include('partials.status-badge', ['status' => $log->outcome === 'success' ? 'active' : 'inactive']) {{ ucfirst($log->outcome) }}</dd>
                        <dt>Performed by</dt><dd>{{ $log->actor_name ?? 'Guest (not signed in)' }} @if($log->actor_id)<span class="text-muted-2 small">(user #{{ $log->actor_id }})</span>@endif</dd>
                        <dt>Affected record</dt><dd>{{ $log->entity_type ? $log->entity_type . ' #' . $log->entity_id : '—' }} {{ $log->entity_label ? '· ' . $log->entity_label : '' }}</dd>
                        <dt>Details</dt><dd>{{ $log->description ?: '—' }}</dd>
                        <dt>Reason</dt><dd>{{ $log->reason ?: '—' }}</dd>
                        <dt>IP address</dt><dd>{{ $log->ip_address ?: '—' }}</dd>
                        <dt>User agent</dt><dd class="small text-muted-2">{{ $log->user_agent ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">Before</div>
                <div class="card-body"><div class="json-box">{{ $log->before ? json_encode($log->before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '—' }}</div></div>
            </div>
            <div class="card">
                <div class="card-header">After</div>
                <div class="card-body"><div class="json-box">{{ $log->after ? json_encode($log->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '—' }}</div></div>
            </div>
        </div>
    </div>
@endsection
