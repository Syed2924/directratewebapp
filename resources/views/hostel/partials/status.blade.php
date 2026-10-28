{{-- @include('hostel.partials.status', ['status' => ..., 'map' => 'booking'|'expense']) --}}
@php([$hText, $hClass] = config('hostel.' . ($map ?? 'booking') . "_statuses.{$status}", [ucfirst((string) $status), 'badge-draft']))
<span class="badge-status {{ $hClass }} text-nowrap">{{ $hText }}</span>
