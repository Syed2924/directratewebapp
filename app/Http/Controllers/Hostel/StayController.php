<?php

namespace App\Http\Controllers\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\HostelBooking;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Check-in / check-out: actual arrival and departure of guests.
 */
class StayController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', HostelBooking::class);
        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->addDays(7)->toDateString());

        $bookings = HostelBooking::visibleTo($request->user())->with('hostel', 'rooms')
            ->when($request->filled('hostel'), fn ($q) => $q->where('hostel_id', (int) $request->input('hostel')))
            ->where(fn ($q) => $q->where('status', 'checked_in')
                ->orWhere(fn ($w) => $w->where('status', 'approved')->where('check_in', '<=', $to)->where('check_out', '>', $from))
                ->orWhere(fn ($w) => $w->where('status', 'checked_out')->whereBetween('actual_check_out_at', [Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay()])))
            ->orderByRaw("FIELD(status, 'checked_in', 'approved', 'checked_out')")->orderBy('check_in')->get();

        return view('hostel.stays.index', [
            'bookings' => $bookings, 'from' => $from, 'to' => $to,
            'hostels' => Hostel::withinCoverageOf($request->user())->orderBy('name')->get(),
        ]);
    }

    public function checkIn(Request $request, HostelBooking $booking)
    {
        $this->authorize('checkIn', $booking);
        $assigned = $booking->rooms()->count();
        $data = $request->validate([
            'actual_check_in_at' => ['required', 'date', 'before_or_equal:now'],
            'arrived_guests' => ['required', 'integer', 'min:1', 'max:' . max(1, $booking->rooms()->sum('capacity'))],
            'occupied_rooms' => ['required', 'integer', 'min:1', 'max:' . max(1, $assigned)],
            'stay_remarks' => ['nullable', 'string', 'max:1000'],
        ], ['arrived_guests.max' => 'More guests than the assigned rooms can hold.', 'occupied_rooms.max' => "Only {$assigned} room(s) are assigned."]);
        $arrival = Carbon::parse($data['actual_check_in_at']);
        if ($arrival->toDateString() < $booking->check_in->toDateString() || $arrival->toDateString() >= $booking->check_out->toDateString()) {
            throw ValidationException::withMessages(['actual_check_in_at' => 'Arrival must fall within the booked stay (' . $booking->check_in->format('d M') . ' – ' . $booking->check_out->format('d M Y') . ').']);
        }

        $booking->forceFill(['actual_check_in_at' => $arrival, 'arrived_guests' => $data['arrived_guests'], 'occupied_rooms' => $data['occupied_rooms'], 'stay_remarks' => $data['stay_remarks'] ?? null]);
        $booking->recordAction('checked_in', 'checked_in', $request->user(), "{$data['arrived_guests']} guest(s), {$data['occupied_rooms']} room(s)" . (filled($data['stay_remarks'] ?? null) ? ' — ' . $data['stay_remarks'] : ''));
        Audit::log('hostel.checked_in', $booking, ['entity_label' => $booking->booking_no]);

        return back()->with('status', "{$booking->guest_name} checked in.");
    }

    public function checkOut(Request $request, HostelBooking $booking)
    {
        $this->authorize('checkOut', $booking);
        $data = $request->validate([
            'actual_check_out_at' => ['required', 'date', 'before_or_equal:now', 'after:' . $booking->actual_check_in_at->toDateTimeString()],
            'stay_remarks' => ['nullable', 'string', 'max:1000'],
        ], ['actual_check_out_at.after' => 'Departure must be after the recorded arrival.']);

        $booking->forceFill(['actual_check_out_at' => Carbon::parse($data['actual_check_out_at'])]);
        if (filled($data['stay_remarks'] ?? null)) {
            $booking->stay_remarks = trim(($booking->stay_remarks ? $booking->stay_remarks . "\n" : '') . $data['stay_remarks']);
        }
        $booking->recordAction('checked_out', 'checked_out', $request->user(), $data['stay_remarks'] ?? null);
        Audit::log('hostel.checked_out', $booking, ['entity_label' => $booking->booking_no]);

        return back()->with('status', "{$booking->guest_name} checked out. Rooms released.");
    }
}
