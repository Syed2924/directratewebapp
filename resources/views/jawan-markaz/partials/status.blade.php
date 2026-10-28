@php([$jmText, $jmClass] = config("jawan_markaz.expense_statuses.{$status}", [ucfirst((string) $status), 'badge-draft']))
<span class="badge-status {{ $jmClass }} text-nowrap">{{ $jmText }}</span>
