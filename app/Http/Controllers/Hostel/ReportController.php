<?php

namespace App\Http\Controllers\Hostel;

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\FinancialYear;
use App\Models\Hostel;
use App\Models\HostelBooking;
use App\Models\HostelBudget;
use App\Models\HostelRoom;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Booking, occupancy and financial reports. One build() feeds both the screen
 * and the CSV export. Guest identity numbers are never exported.
 */
class ReportController extends Controller
{
    public const TYPES = ['bookings' => 'Booking Register', 'occupancy' => 'Occupancy & Availability', 'financial' => 'Financial Summary'];

    public function index(Request $request)
    {
        $this->authorize('hostel.view');
        $type = array_key_exists($request->input('type'), self::TYPES) ? $request->input('type') : 'bookings';

        return view('hostel.reports.index', [
            'type' => $type,
            'report' => $this->build($type, $request),
            'years' => FinancialYear::orderByDesc('starts_on')->get(),
            'hostels' => Hostel::withinCoverageOf($request->user())->orderBy('name')->get(),
            'districts' => District::whereIn('id', Hostel::withinCoverageOf($request->user())->pluck('district_id'))->orderBy('name')->get(),
            'from' => $this->from($request), 'to' => $this->to($request),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('hostel.export');
        $type = array_key_exists($request->input('type'), self::TYPES) ? $request->input('type') : 'bookings';
        $report = $this->build($type, $request);
        Audit::log('hostel.report_exported', null, ['description' => self::TYPES[$type] . ' (' . count($report['rows']) . ' rows)']);

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $report['head']);
            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(fn ($v) => AuditLogController::csvSafe(is_float($v) ? number_format($v, 2, '.', '') : $v), $row));
            }
            fclose($out);
        }, "hostel-{$type}-" . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function from(Request $request): Carbon
    {
        return Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()));
    }

    protected function to(Request $request): Carbon
    {
        $to = Carbon::parse($request->input('to', now()->endOfMonth()->toDateString()));

        return $to->lt($this->from($request)) ? $this->from($request) : $to;
    }

    protected function hostels(Request $request)
    {
        return Hostel::withinCoverageOf($request->user())
            ->when($request->filled('hostel'), fn ($q) => $q->whereKey($request->integer('hostel')))
            ->when($request->filled('district'), fn ($q) => $q->where('district_id', $request->integer('district')))
            ->with('district')->orderBy('name')->get();
    }

    protected function build(string $type, Request $request): array
    {
        $hostels = $this->hostels($request);
        [$from, $to] = [$this->from($request), $this->to($request)];

        if ($type === 'occupancy') {
            // Room-nights: rooms held by approved / checked-in / checked-out bookings, clipped to the range.
            $end = $to->copy()->addDay();
            $nights = $from->diffInDays($end);
            $rows = $hostels->map(function ($h) use ($from, $end, $nights) {
                $bookable = HostelRoom::where('hostel_id', $h->id)->where('status', 'available')->count();
                $used = HostelBooking::where('hostel_id', $h->id)->whereIn('status', ['approved', 'checked_in', 'checked_out'])
                    ->where('check_in', '<', $end->toDateString())->where('check_out', '>', $from->toDateString())->withCount('rooms')->get()
                    ->sum(function ($b) use ($from, $end) {
                        $s = $b->check_in->gt($from) ? $b->check_in : $from;
                        $e = $b->check_out->lt($end) ? $b->check_out : $end;

                        return $b->rooms_count * max(0, $s->diffInDays($e, false));
                    });
                $capacity = $bookable * $nights;
                $guests = HostelBooking::where('hostel_id', $h->id)->whereIn('status', ['checked_in', 'checked_out'])
                    ->whereBetween('actual_check_in_at', [$from->copy()->startOfDay(), $end])->sum('arrived_guests');

                return [$h->name, $h->district->name, $bookable, $capacity, $used, $capacity - $used, $capacity > 0 ? round($used / $capacity * 100, 1) : 0.0, (int) $guests];
            })->all();

            return ['head' => ['Hostel', 'District', 'Bookable Rooms', 'Room-Nights Available', 'Room-Nights Booked', 'Room-Nights Free', 'Occupancy %', 'Guests Checked In'], 'rows' => $rows, 'pct' => 6];
        }

        if ($type === 'financial') {
            $fy = FinancialYear::fromRequest($request);
            $rows = $hostels->map(function ($h) use ($fy) {
                $b = $fy ? HostelBudget::where('hostel_id', $h->id)->where('financial_year_id', $fy->id)->first() : null;
                $f = $b ? $b->figures() : null;

                return [$h->name, $h->district->name, $b?->statusLabel() ?? 'No budget', $f['original'] ?? 0.0, $f['revisions'] ?? 0.0, $f['allocated'] ?? 0.0,
                    $f['paid'] ?? 0.0, $f['unpaid'] ?? 0.0, $f['remaining'] ?? 0.0, $f['available'] ?? 0.0];
            })->all();

            return ['head' => ['Hostel', 'District', 'Budget Status', 'Original Budget', 'Revisions', 'Revised Budget', 'Expenditure (Paid)', 'Outstanding Commitments', 'Remaining Budget', 'Available to Commit'], 'rows' => $rows, 'money' => [3, 4, 5, 6, 7, 8, 9]];
        }

        $bookings = HostelBooking::visibleTo($request->user())->whereIn('hostel_id', $hostels->pluck('id'))->with('hostel', 'district')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->where('check_in', '<=', $to->toDateString())->where('check_out', '>', $from->toDateString())
            ->orderBy('check_in')->get();
        $rows = $bookings->map(fn ($b) => [
            $b->booking_no, $b->request_date->format('Y-m-d'), $b->requested_by_email, $b->district->name, $b->hostel->name, $b->guest_name, $b->maskedCnic(),
            $b->mobile, $b->organization, $b->referred_by, $b->referrer_designation, $b->purposeLabel(), $b->check_in->format('Y-m-d'), $b->check_out->format('Y-m-d'),
            $b->guest_count, $b->room_count, $b->remarks, $b->statusLabel(),
        ])->all();

        return ['head' => ['Booking ID', 'Request Date', 'Requested By Email', 'District/HQ', 'Youth Hostel', 'Guest Name', 'CNIC/Passport', 'Mobile', 'Organization',
            'Referred By', 'Referrer Designation', 'Purpose', 'Check-in', 'Check-out', 'Guests', 'Rooms', 'Remarks', 'Status'], 'rows' => $rows,
            'statusCounts' => $bookings->countBy('status')];
    }
}
