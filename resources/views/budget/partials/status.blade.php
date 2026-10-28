@php([$statusText, $statusClass] = config("budget.statuses.{$status}", [ucfirst((string) $status), 'badge-draft']))
<span class="badge-status {{ $statusClass }} text-nowrap">{{ $statusText }}</span>
