<?php

namespace App\Http\Controllers\Hostel;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\HostelRoom;
use App\Support\Audit;
use App\Support\HostelAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Hostel records and room inventory. Hostels live in the shared organization
 * master list; profile and rooms are maintained with organization.manage.
 */
class HostelController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('hostel.view');
        $hostels = Hostel::withinCoverageOf($request->user())->with('district')
            ->withCount(['rooms', 'rooms as bookable_count' => fn ($r) => $r->where('status', 'available')])
            ->withSum('rooms', 'capacity')->orderBy('name')->get();

        return view('hostel.hostels.index', ['hostels' => $hostels, 'today' => HostelAvailability::today($hostels)]);
    }

    /** Hostel profile with room availability for a date range. */
    public function show(Request $request, Hostel $hostel)
    {
        $this->authorize('hostel.view');
        abort_unless($hostel->isWithinScopeOf($request->user()), 403);
        $from = Carbon::parse($request->input('from', now()->toDateString()));
        $to = Carbon::parse($request->input('to', now()->addDay()->toDateString()));
        if ($to->lte($from)) {
            $to = $from->copy()->addDay();
        }
        $hostel->load('district', 'rooms');
        $held = HostelAvailability::heldRooms($hostel->id, $from, $to);

        $rooms = $hostel->rooms->map(function ($r) use ($held) {
            $r->availability = $r->status !== 'available' ? $r->status : ($held[$r->id] ?? 'available');

            return $r;
        });

        return view('hostel.hostels.show', [
            'hostel' => $hostel,
            'rooms' => $rooms,
            'from' => $from,
            'to' => $to,
            'byType' => $rooms->groupBy('room_type')->map(fn ($g) => ['total' => $g->count(), 'free' => $g->where('availability', 'available')->count(), 'capacity' => $g->first()->capacity]),
        ]);
    }

    public function edit(Request $request, Hostel $hostel)
    {
        $this->authorize('organization.manage');
        abort_unless($hostel->isWithinScopeOf($request->user()), 403);

        return view('hostel.hostels.edit', ['hostel' => $hostel]);
    }

    public function update(Request $request, Hostel $hostel)
    {
        $this->authorize('organization.manage');
        abort_unless($hostel->isWithinScopeOf($request->user()), 403);
        $data = $request->validate([
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'is_active' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $before = array_map('strval', $hostel->only(array_keys($data)));
        $hostel->update($data);
        [$old, $new] = Audit::diff($before, array_map('strval', $hostel->only(array_keys($data))));
        if ($new) {
            Audit::log('organization.changed', $hostel, ['description' => "Hostel profile updated: {$hostel->name}", 'before' => $old, 'after' => $new]);
        }

        return redirect()->route('hostel.hostels.show', $hostel)->with('status', 'Hostel information saved.');
    }

    public function storeRoom(Request $request, Hostel $hostel)
    {
        $this->authorize('organization.manage');
        abort_unless($hostel->isWithinScopeOf($request->user()), 403);
        $data = $request->validate([
            'room_number' => ['required', 'string', 'max:20', Rule::unique('hostel_rooms')->where('hostel_id', $hostel->id)],
            'room_type' => ['required', Rule::in(array_keys(config('hostel.room_types')))],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ], ['room_number.unique' => 'This hostel already has a room with that number.']);
        $room = $hostel->rooms()->create($data + ['status' => 'available']);
        Audit::log('organization.changed', $hostel, ['description' => "Room {$room->room_number} added to {$hostel->name}", 'after' => $data]);

        return back()->with('status', "Room {$room->room_number} added.");
    }

    /** Change a room's status (e.g. under maintenance). Rooms held by a current booking cannot be taken out of service. */
    public function updateRoom(Request $request, Hostel $hostel, HostelRoom $room)
    {
        $this->authorize('organization.manage');
        abort_unless($hostel->isWithinScopeOf($request->user()) && $room->hostel_id === $hostel->id, 403);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(config('hostel.room_statuses')))]]);
        if ($data['status'] !== 'available') {
            $future = HostelAvailability::heldRooms($hostel->id, now()->toDateString(), now()->addYears(2)->toDateString());
            if ($future->has($room->id)) {
                return back()->with('status_error', "Room {$room->room_number} is assigned to a current or upcoming booking; reassign or cancel it first.");
            }
        }
        $old = $room->status;
        $room->update($data);
        Audit::log('organization.changed', $hostel, ['description' => "Room {$room->room_number}: {$old} → {$room->status}"]);

        return back()->with('status', "Room {$room->room_number} is now " . strtolower($room->statusLabel()) . '.');
    }
}
