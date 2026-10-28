{{-- @include('activity.partials.badge', ['type' => 'proposal'|'activity', 'status' => ...]) --}}
@php([$badgeLabel, $badgeClass] = config("activity.{$type}_statuses.{$status}", [ucfirst($status), 'badge-draft']))
<span class="badge-status {{ $badgeClass }} text-nowrap">{{ $badgeLabel }}</span>
