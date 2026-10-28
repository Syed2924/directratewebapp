{{-- @include('activity.partials.timeline', ['actions' => $proposal->actions]) --}}
<ul class="timeline">
    @forelse ($actions as $step)
        <li>
            <span class="dot"><i class="bi {{ $step->icon() }}"></i></span>
            <div class="fw-medium">
                {{ $step->label() }}
                @if ($step->assignee) <span class="fw-normal">→ {{ $step->assignee->name }}</span> @endif
            </div>
            <div class="meta">
                {{ $step->user->name ?? 'System' }} · {{ $step->created_at->format('d M Y, H:i') }}
                @if ($step->action_date && ! $step->action_date->isSameDay($step->created_at)) · dated {{ $step->action_date->format('d M Y') }} @endif
            </div>
            @if ($step->remarks)<div class="remarks">{{ $step->remarks }}</div>@endif
        </li>
    @empty
        <li class="text-muted-2 small ps-0">No history yet.</li>
    @endforelse
</ul>
