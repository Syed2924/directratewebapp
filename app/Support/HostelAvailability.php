<?php

namespace App\Support;

use App\Models\Hostel;
use App\Models\HostelBooking;
use App\Models\HostelRoom;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Room availability. A stay occupies the nights from check-in up to (not
 * including) the check-out date. Only approved and checked-in bookings hold
 * rooms; drafts, pending, returned, rejected, cancelled and checked-out
 * bookings never do. Rooms under maintenance or inactive are never offered.
 */
class HostelAvailability
{
    /** Bookings holding rooms in the hostel for any night between $from and $to. */
    public static function holding(int $hostelId, $from, $to, ?int $exceptBookingId = null)
    {
        return HostelBooking::where('hostel_id', $hostelId)
            ->whereIn('status', config('hostel.holding_statuses'))
            ->where('check_in', '<', Carbon::parse($to)->toDateString())
            ->where('check_out', '>', Carbon::parse($from)->toDateString())
            ->when($exceptBookingId, fn ($q) => $q->whereKeyNot($exceptBookingId));
    }

    /** room_id => 'occupied' | 'reserved' for rooms held by bookings in the range. */
    public static function heldRooms(int $hostelId, $from, $to, ?int $exceptBookingId = null): Collection
    {
        $held = collect();
        self::holding($hostelId, $from, $to, $exceptBookingId)->with('rooms:id')->get(['id', 'status'])
            ->each(function ($b) use ($held) {
                foreach ($b->rooms as $r) {
                    $held[$r->id] = ($held[$r->id] ?? null) === 'occupied' || $b->status === 'checked_in' ? 'occupied' : 'reserved';
                }
            });

        return $held;
    }

    /** Bookable rooms that are free for the whole stay. */
    public static function freeRooms(int $hostelId, $from, $to, ?int $exceptBookingId = null): Collection
    {
        $held = self::heldRooms($hostelId, $from, $to, $exceptBookingId);

        return HostelRoom::where('hostel_id', $hostelId)->where('status', 'available')
            ->orderByRaw('LENGTH(room_number), room_number')->get()
            ->reject(fn ($r) => $held->has($r->id))->values();
    }

    /**
     * Can the request be met? Checks room count for the whole stay and that the
     * largest free rooms together hold the guests. Returns [ok, message, freeRooms].
     */
    public static function check(int $hostelId, $from, $to, int $rooms, int $guests, ?int $exceptBookingId = null): array
    {
        $free = self::freeRooms($hostelId, $from, $to, $exceptBookingId);
        if ($free->count() < $rooms) {
            return [false, "Only {$free->count()} room(s) are free for the whole stay; {$rooms} requested.", $free];
        }
        $capacity = $free->sortByDesc('capacity')->take($rooms)->sum('capacity');
        if ($capacity < $guests) {
            return [false, "{$rooms} room(s) can hold at most {$capacity} guests here; {$guests} requested. Request more rooms.", $free];
        }

        return [true, "{$free->count()} room(s) free for these dates.", $free];
    }

    /** Suggest rooms for a booking: smallest set of free rooms (by capacity) that fits the guests. */
    public static function suggest(Collection $free, int $rooms, int $guests): Collection
    {
        $pick = $free->sortBy('capacity')->values();
        for ($start = 0; $start + $rooms <= $pick->count(); $start++) {
            $set = $pick->slice($start, $rooms);
            if ($set->sum('capacity') >= $guests) {
                return $set->values();
            }
        }

        return $free->sortByDesc('capacity')->take($rooms)->values();
    }

    /** Today's position for a set of hostels: bookable rooms, occupied, reserved, free, guests staying. */
    public static function today(Collection $hostels): array
    {
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $out = ['rooms' => 0, 'occupied' => 0, 'reserved' => 0, 'free' => 0, 'guests' => 0, 'by_hostel' => []];
        foreach ($hostels as $h) {
            $bookable = HostelRoom::where('hostel_id', $h->id)->where('status', 'available')->count();
            $held = self::heldRooms($h->id, $today, $tomorrow);
            $occupied = $held->filter(fn ($s) => $s === 'occupied')->count();
            $reserved = $held->count() - $occupied;
            $guests = (int) HostelBooking::where('hostel_id', $h->id)->where('status', 'checked_in')->sum('arrived_guests');
            $out['by_hostel'][$h->id] = compact('bookable', 'occupied', 'reserved', 'guests') + ['free' => max(0, $bookable - $held->count())];
            $out['rooms'] += $bookable;
            $out['occupied'] += $occupied;
            $out['reserved'] += $reserved;
            $out['guests'] += $guests;
        }
        $out['free'] = max(0, $out['rooms'] - $out['occupied'] - $out['reserved']);

        return $out;
    }
}
