<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit;
use App\Support\Authority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        $query = $this->filtered($request);

        return view('admin.audit.index', [
            'logs' => $query->latest('id')->paginate(15)->withQueryString(),
            'events' => AuditLog::EVENTS,
            'actors' => Authority::visibleUsers($actor, User::query())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, AuditLog $log)
    {
        $query = AuditLog::query()->whereKey($log->id);
        self::restrictToAuthority($request->user(), $query);
        abort_unless($query->exists(), 404);

        return view('admin.audit.show', ['log' => $log]);
    }

    /** CSV export (opens in Excel). Applies the same filters and visibility as the screen. */
    public function export(Request $request)
    {
        $query = $this->filtered($request)->latest('id');
        Audit::log('audit.exported', null, ['description' => 'Exported audit log (' . $query->count() . ' rows)', 'after' => array_filter($request->only('from', 'to', 'event', 'actor', 'outcome', 'q'))]);

        $filename = 'audit-log-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, ['ID', 'Date & Time', 'User', 'Event', 'Outcome', 'Entity', 'Details', 'Reason', 'IP Address']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $log) {
                    fputcsv($out, array_map([self::class, 'csvSafe'], [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->actor_name ?? 'Guest',
                        $log->eventLabel(),
                        $log->outcome,
                        trim(($log->entity_type ?? '') . ' ' . ($log->entity_label ?? '')),
                        $log->description,
                        $log->reason,
                        $log->ip_address,
                    ]));
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formula injection. */
    public static function csvSafe($value)
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }

    /**
     * Administrators without global scope see only events performed by, or
     * about, users within their authority ("authorized subset", spec §9).
     */
    public static function restrictToAuthority(User $actor, Builder $query): Builder
    {
        if ($actor->scopeCoverage()->global) {
            return $query;
        }

        $ids = Authority::visibleUsers($actor, User::query())->pluck('id');

        return $query->where(fn ($q) => $q->whereIn('actor_id', $ids)
            ->orWhere(fn ($w) => $w->where('entity_type', 'User')->whereIn('entity_id', $ids)));
    }

    protected function filtered(Request $request): Builder
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'actor' => ['nullable', 'integer'],
            'outcome' => ['nullable', 'in:success,failure'],
        ]);

        $query = AuditLog::query();
        self::restrictToAuthority($request->user(), $query);

        return $query
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', Carbon::parse($request->input('from'))->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', Carbon::parse($request->input('to'))->endOfDay()))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->input('event')))
            ->when($request->filled('actor'), fn ($q) => $q->where('actor_id', $request->integer('actor')))
            ->when($request->filled('outcome'), fn ($q) => $q->where('outcome', $request->input('outcome')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . str_replace(['%', '_'], ['\%', '\_'], $request->input('q')) . '%';
                $q->where(fn ($w) => $w->where('description', 'like', $term)
                    ->orWhere('entity_label', 'like', $term)
                    ->orWhere('actor_name', 'like', $term)
                    ->orWhere('ip_address', 'like', $term));
            });
    }
}
