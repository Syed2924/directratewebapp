<?php

namespace App\Http\Controllers\Hostel;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\FinancialDocument;
use App\Models\Hostel;
use App\Models\HostelBooking;
use App\Support\Audit;
use App\Support\HostelAvailability;
use App\Support\ReferenceNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Booking requests, the booking register and booking decisions.
 */
class BookingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', HostelBooking::class);
        $user = $request->user();
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim((string) $request->input('q'))) . '%';

        $query = HostelBooking::visibleTo($user)
            ->when($request->filled('hostel'), fn ($q) => $q->where('hostel_id', (int) $request->input('hostel')))
            ->when($request->filled('district'), fn ($q) => $q->where('district_id', (int) $request->input('district')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('check_out', '>', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('check_in', '<=', $request->input('to')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('booking_no', 'like', $like)
                ->orWhere('guest_name', 'like', $like)->orWhere('organization', 'like', $like)->orWhere('requested_by_email', 'like', $like)));

        return view('hostel.bookings.index', [
            'bookings' => (clone $query)->with('hostel', 'district')->latest('id')->paginate(20)->withQueryString(),
            // Rooms booked = rooms of valid reservations (approved / checked in) in the selected range.
            'roomsBooked' => (clone $query)->whereIn('status', config('hostel.holding_statuses'))->sum('room_count'),
            'counts' => HostelBooking::visibleTo($user)->selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status'),
            'hostels' => Hostel::withinCoverageOf($user)->orderBy('name')->get(),
            'districts' => District::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', HostelBooking::class);

        return view('hostel.bookings.form', $this->formData($request, new HostelBooking([
            'request_date' => now(), 'requested_by_email' => $request->user()->email, 'guest_count' => 1, 'room_count' => 1,
            'hostel_id' => $request->integer('hostel') ?: null,
        ])) + ['reference' => ReferenceNumber::preview(HostelBooking::class, 'booking_no', 'HB')]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', HostelBooking::class);
        $data = $this->validated($request, null);
        $user = $request->user();

        $booking = DB::transaction(function () use ($data, $user) {
            $booking = new HostelBooking($data);
            $booking->request_date = now();
            $booking->status = 'draft';
            $booking->created_by = $booking->updated_by = $booking->submitted_by = $user->id;
            ReferenceNumber::saveWith($booking, 'booking_no', 'HB');
            $booking->recordAction('created', null, $user);

            return $booking;
        });
        $this->storeFiles($request, $booking);
        Audit::log('hostel.booking_created', $booking, ['entity_label' => $booking->booking_no, 'description' => "{$booking->booking_no}: {$booking->guest_name}, {$booking->room_count} room(s) {$booking->check_in->format('d M')}–{$booking->check_out->format('d M Y')}"]);

        return $data['intent'] === 'submit'
            ? $this->doSubmit($request, $booking)
            : redirect()->route('hostel.bookings.show', $booking)->with('status', "Booking {$booking->booking_no} saved as draft.");
    }

    public function show(Request $request, HostelBooking $booking)
    {
        $this->authorize('view', $booking);
        $booking->load('hostel.district', 'district', 'rooms', 'approvals.user', 'documents.uploader', 'submitter', 'reviewer');
        $user = $request->user();

        $check = null;
        if (in_array($booking->status, ['draft', 'pending', 'returned'], true)) {
            [$ok, $message, $free] = HostelAvailability::check($booking->hostel_id, $booking->check_in, $booking->check_out, $booking->room_count, $booking->guest_count, $booking->id);
            $check = ['ok' => $ok, 'message' => $message, 'free' => $free, 'suggested' => HostelAvailability::suggest($free, $booking->room_count, $booking->guest_count)->pluck('id')->all()];
        }

        return view('hostel.bookings.show', [
            'booking' => $booking,
            'check' => $check,
            'revealed' => session('reveal_booking') === $booking->id && $user->can('viewIdentity', $booking),
        ]);
    }

    public function edit(Request $request, HostelBooking $booking)
    {
        $this->authorize('update', $booking);
        $booking->load('documents', 'approvals.user');

        return view('hostel.bookings.form', $this->formData($request, $booking) + ['reference' => $booking->booking_no]);
    }

    public function update(Request $request, HostelBooking $booking)
    {
        $this->authorize('update', $booking);
        $data = $this->validated($request, $booking);
        if (blank($data['cnic_passport'] ?? null)) {
            unset($data['cnic_passport']);   // blank = keep the stored value
        }
        $booking->fill($data);
        $booking->recordAction('updated', null, $request->user());
        $this->storeFiles($request, $booking);
        Audit::log('hostel.booking_updated', $booking, ['entity_label' => $booking->booking_no]);

        return $data['intent'] === 'submit'
            ? $this->doSubmit($request, $booking)
            : redirect()->route('hostel.bookings.show', $booking)->with('status', 'Booking saved.');
    }

    public function submit(Request $request, HostelBooking $booking)
    {
        return $this->doSubmit($request, $booking);
    }

    /** Validate documents and availability, then send for review. Pending requests do not hold rooms. */
    protected function doSubmit(Request $request, HostelBooking $booking)
    {
        $this->authorize('submit', $booking);
        if (! $booking->documents()->exists()) {
            throw ValidationException::withMessages(['document' => 'Attach the referral letter or supporting document before submitting.']);
        }
        [$ok, $message] = HostelAvailability::check($booking->hostel_id, $booking->check_in, $booking->check_out, $booking->room_count, $booking->guest_count, $booking->id);
        if (! $ok) {
            throw ValidationException::withMessages(['check_in' => "Not available: {$message}"]);
        }
        $resubmit = $booking->status === 'returned';
        $booking->submitted_by = $request->user()->id;
        $booking->submitted_at = now();
        $booking->recordAction($resubmit ? 'resubmitted' : 'submitted', 'pending', $request->user());
        Audit::log('hostel.booking_submitted', $booking, ['entity_label' => $booking->booking_no]);

        return redirect()->route('hostel.bookings.show', $booking)->with('status', "Booking {$booking->booking_no} submitted for review. It is not confirmed until approved.");
    }

    /** Approve (assigning specific rooms), reject with reason, or return for correction. */
    public function decide(Request $request, HostelBooking $booking)
    {
        $this->authorize('decide', $booking);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'return'])],
            'room_ids' => ['required_if:decision,approve', 'array'],
            'room_ids.*' => ['integer'],
            'remarks' => ['required_if:decision,reject,return', 'nullable', 'string', 'max:2000'],
        ], ['remarks.required_if' => 'A reason is required when rejecting or returning a booking.', 'room_ids.required_if' => 'Select the rooms to assign.']);
        $user = $request->user();
        $to = ['approve' => 'approved', 'reject' => 'rejected', 'return' => 'returned'][$data['decision']];

        DB::transaction(function () use ($booking, $data, $user, $to) {
            if ($to === 'approved') {
                // Serialize approvals per hostel so two bookings can never take the same room.
                Hostel::whereKey($booking->hostel_id)->lockForUpdate()->first();
                $free = HostelAvailability::freeRooms($booking->hostel_id, $booking->check_in, $booking->check_out, $booking->id)->keyBy('id');
                $ids = array_values(array_unique(array_map('intval', $data['room_ids'])));
                if (count($ids) !== $booking->room_count) {
                    throw ValidationException::withMessages(['room_ids' => "Select exactly {$booking->room_count} room(s)."]);
                }
                if ($busy = array_diff($ids, $free->keys()->all())) {
                    throw ValidationException::withMessages(['room_ids' => 'Some selected rooms are no longer free for these dates. Refresh and choose again.']);
                }
                $capacity = collect($ids)->sum(fn ($id) => $free[$id]->capacity);
                if ($capacity < $booking->guest_count) {
                    throw ValidationException::withMessages(['room_ids' => "The selected rooms hold {$capacity} guests; {$booking->guest_count} requested."]);
                }
                $booking->rooms()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $user->id, 'assigned_at' => now()]])->all());
            }
            $booking->reviewed_by = $user->id;
            $booking->decided_at = now();
            $booking->recordAction($to, $to, $user, $data['remarks'] ?? null);
        });
        Audit::log("hostel.booking_{$to}", $booking, ['entity_label' => $booking->booking_no,
            'description' => "{$booking->booking_no}: {$booking->statusLabel()}" . ($to === 'approved' ? ' — rooms ' . $booking->rooms()->pluck('room_number')->implode(', ') : ''),
            'reason' => $data['remarks'] ?? null]);

        return redirect()->route('hostel.bookings.show', $booking)->with('status', match ($to) {
            'approved' => 'Booking approved and rooms reserved.',
            'rejected' => 'Booking rejected.',
            'returned' => 'Booking returned for correction.',
        });
    }

    public function cancel(Request $request, HostelBooking $booking)
    {
        $this->authorize('cancel', $booking);
        $data = $request->validate(['remarks' => ['required', 'string', 'max:2000']], ['remarks.required' => 'Give the reason for cancelling.']);
        $booking->recordAction('cancelled', 'cancelled', $request->user(), $data['remarks']);
        Audit::log('hostel.booking_cancelled', $booking, ['entity_label' => $booking->booking_no, 'reason' => $data['remarks']]);

        return redirect()->route('hostel.bookings.show', $booking)->with('status', 'Booking cancelled. Any reserved rooms are released.');
    }

    /** Show the full CNIC/passport once; every reveal is written to the audit log. */
    public function revealIdentity(Request $request, HostelBooking $booking)
    {
        $this->authorize('viewIdentity', $booking);
        Audit::log('hostel.identity_viewed', $booking, ['entity_label' => $booking->booking_no, 'description' => "Viewed guest identity for {$booking->booking_no}"]);

        return redirect()->route('hostel.bookings.show', $booking)->with('reveal_booking', $booking->id);
    }

    public function storeDocuments(Request $request, HostelBooking $booking)
    {
        $this->authorize('update', $booking);
        $request->validate([
            'kind' => ['required', Rule::in(['referral', 'supporting'])],
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ]);
        foreach ($request->file('files') as $file) {
            FinancialDocument::storeFor($booking, $file, $request->input('kind'), $request->user());
        }

        return redirect()->route('hostel.bookings.show', $booking)->with('status', 'Documents uploaded.');
    }

    public function destroyDocument(Request $request, HostelBooking $booking, FinancialDocument $document)
    {
        $this->authorize('update', $booking);
        abort_unless($document->documentable_type === HostelBooking::class && $document->documentable_id === $booking->id && $document->uploaded_by === $request->user()->id, 403);
        $document->delete();

        return back()->with('status', 'Document removed.');
    }

    protected function formData(Request $request, HostelBooking $booking): array
    {
        return [
            'booking' => $booking,
            'hostels' => Hostel::active()->withinCoverageOf($request->user())->with('district')->withCount(['rooms' => fn ($r) => $r->where('status', 'available')])->orderBy('name')->get(),
            'districts' => District::active()->orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request, ?HostelBooking $existing): array
    {
        $user = $request->user();

        return $request->validate([
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->where('is_active', true)],
            'hostel_id' => ['required', 'integer', Rule::exists('hostels', 'id')->where('is_active', true), function ($a, $v, $fail) use ($user) {
                if (! $user->scopeCoverage()->covers('hostel', $v)) {
                    $fail('You cannot book that hostel.');
                }
            }],
            'requested_by_email' => ['required', 'email', 'max:150'],
            'guest_name' => ['required', 'string', 'max:150'],
            'cnic_passport' => [$existing ? 'nullable' : 'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
            'mobile' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'organization' => ['nullable', 'string', 'max:200'],
            'referred_by' => ['nullable', 'string', 'max:150'],
            'referrer_designation' => ['nullable', 'string', 'max:150'],
            'purpose' => ['required', Rule::in(array_keys(config('hostel.purposes')))],
            'purpose_details' => ['nullable', 'string', 'max:500'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:500'],
            'room_count' => ['required', 'integer', 'min:1', 'max:100', 'lte:guest_count'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
            'intent' => ['required', Rule::in(['draft', 'submit'])],
        ], [
            'check_out.after' => 'The check-out date must be later than the check-in date.',
            'room_count.lte' => 'Rooms cannot exceed the number of guests.',
            'cnic_passport.regex' => 'Use letters, digits and dashes only.',
        ]);
    }

    protected function storeFiles(Request $request, HostelBooking $booking): void
    {
        if ($request->hasFile('document')) {
            FinancialDocument::storeFor($booking, $request->file('document'), 'referral', $request->user());
        }
    }
}
