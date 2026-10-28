{{-- @include('budget.partials.history', ['approvals' => $record->approvals]) --}}
<div class="card">
    <div class="card-header">Approval History</div>
    <div class="card-body">
        <ul class="timeline">
            @forelse ($approvals as $step)
                <li>
                    <span class="dot"><i class="bi {{ $step->icon() }}"></i></span>
                    <div class="fw-medium">{{ $step->label() }}</div>
                    <div class="meta">{{ $step->user->name ?? 'System' }} · {{ $step->created_at->format('d M Y, H:i') }}</div>
                    @if ($step->remarks)<div class="remarks">{{ $step->remarks }}</div>@endif
                </li>
            @empty
                <li class="text-muted-2 small ps-0">No history yet.</li>
            @endforelse
        </ul>
    </div>
</div>
