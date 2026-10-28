@extends('layouts.app')
@section('title', 'Booking ' . $booking->booking_no)

@php
    $b = $booking;
    $user = auth()->user();
    $decide = Gate::inspect('decide', $b);
@endphp

@section('content')
    <div class="page-head">
        <div>
            <h1>Booking {{ $b->booking_no }} @include('hostel.partials.status', ['status' => $b->status])</h1>
            <p>{{ $b->guest_name }} · {{ $b->hostel->name }} · {{ $b->check_in->format('d M') }} – {{ $b->check_out->format('d M Y') }} ({{ $b->nights() }} night{{ $b->nights() === 1 ? '' : 's' }})</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('hostel.bookings.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Booking List</a>
            @can('update', $b)<a href="{{ route('hostel.bookings.edit', $b) }}" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>@endcan
        </div>
    </div>

    @if (in_array($b->status, ['returned', 'rejected', 'cancelled'], true) && ($last = $b->approvals->where('action', $b->status)->last()))
        <div class="alert {{ $b->status === 'returned' ? 'alert-warning' : 'alert-danger' }}"><strong>{{ $b->statusLabel() }}</strong> by {{ $last->user->name ?? '—' }} on {{ $last->created_at->format('d M Y') }}:
            <div class="mt-1" style="white-space:pre-line">{{ $last->remarks }}</div></div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            {{-- Submit --}}
            @can('submit', $b)
                <div class="card action-card mb-3">
                    <form method="POST" action="{{ route('hostel.bookings.submit', $b) }}" class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                        @csrf
                        <span class="small">This request is {{ strtolower($b->statusLabel()) }}. {{ $b->documents->isEmpty() ? 'Attach the supporting document, then submit.' : 'Submit it for review when ready.' }}</span>
                        <button class="btn btn-primary" @disabled($b->documents->isEmpty())><i class="bi bi-send me-1"></i>{{ $b->status === 'returned' ? 'Resubmit' : 'Submit' }} Request</button>
                    </form>
                    @foreach (['document', 'check_in'] as $f)@error($f)<div class="px-3 pb-3 text-danger small">{{ $message }}</div>@enderror @endforeach
                </div>
            @endcan

            {{-- Availability --}}
            @if ($check)
                <div class="alert {{ $check['ok'] ? 'alert-success' : 'alert-danger' }} d-flex gap-2 small">
                    <i class="bi {{ $check['ok'] ? 'bi-check-circle-fill' : 'bi-x-octagon-fill' }} mt-1"></i>
                    <div><strong>Availability for the whole stay:</strong> {{ $check['message'] }}</div>
                </div>
            @endif

            {{-- Decision --}}
            @if ($b->awaitingDecision())
                @if ($decide->allowed())
                    <div class="card action-card mb-3">
                        <div class="card-header">Booking Approval</div>
                        <form method="POST" action="{{ route('hostel.bookings.decide', $b) }}" class="card-body row g-3" novalidate>
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label" for="decision">Decision <span class="text-danger">*</span></label>
                                <select id="decision" name="decision" class="form-select">
                                    <option value="approve" @selected(old('decision') === 'approve') @disabled(! $check['ok'])>Approve</option>
                                    <option value="return" @selected(old('decision') === 'return')>Return for Correction</option>
                                    <option value="reject" @selected(old('decision') === 'reject' || ! $check['ok'])>Reject</option>
                                </select>
                            </div>
                            <div class="col-md-8" data-approve>
                                <label class="form-label">Assign {{ $b->room_count }} room(s) for {{ $b->guest_count }} guest(s) <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-2" style="max-height:160px;overflow:auto">
                                    @php($selected = old('room_ids', $check['suggested']))
                                    @foreach ($check['free'] as $room)
                                        <input type="checkbox" class="btn-check" name="room_ids[]" id="room-{{ $room->id }}" value="{{ $room->id }}" autocomplete="off" @checked(in_array($room->id, $selected))>
                                        <label class="btn btn-sm btn-outline-primary" for="room-{{ $room->id }}" data-cap="{{ $room->capacity }}">{{ $room->room_number }} <span class="small opacity-75">· {{ $room->capacity }} bed{{ $room->capacity > 1 ? 's' : '' }}</span></label>
                                    @endforeach
                                </div>
                                <div class="form-text" id="roomTally"></div>
                                @error('room_ids')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="remarks">Remarks</label>
                                <textarea id="remarks" name="remarks" rows="2" class="form-control @error('remarks') is-invalid @enderror" placeholder="Required when rejecting or returning — e.g. Please verify the organization details.">{{ old('remarks') }}</textarea>
                                @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 text-end"><button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Decision</button></div>
                        </form>
                    </div>
                @else
                    <div class="alert alert-info d-flex gap-2"><i class="bi bi-hourglass-split"></i><div>Pending review — not confirmed yet.
                        @if ($user->hasPermission('hostel.approve') && $decide->message())<br><span class="small">{{ $decide->message() }}</span>@endif</div></div>
                @endif
            @endif

            {{-- Check-in / check-out --}}
            @can('checkIn', $b)
                <div class="card action-card mb-3" id="stay">
                    <div class="card-header">Guest Check-in</div>
                    <form method="POST" action="{{ route('hostel.bookings.check-in', $b) }}" class="card-body row g-2 align-items-end" novalidate>
                        @csrf
                        <div class="col-md-4"><label class="form-label small" for="ci-at">Actual Arrival</label>
                            <input type="datetime-local" id="ci-at" name="actual_check_in_at" class="form-control @error('actual_check_in_at') is-invalid @enderror" value="{{ old('actual_check_in_at', now()->format('Y-m-d\TH:i')) }}"></div>
                        <div class="col-6 col-md-2"><label class="form-label small" for="ci-g">Guests Arrived</label>
                            <input type="number" min="1" id="ci-g" name="arrived_guests" class="form-control @error('arrived_guests') is-invalid @enderror" value="{{ old('arrived_guests', $b->guest_count) }}"></div>
                        <div class="col-6 col-md-2"><label class="form-label small" for="ci-r">Rooms Occupied</label>
                            <input type="number" min="1" id="ci-r" name="occupied_rooms" class="form-control @error('occupied_rooms') is-invalid @enderror" value="{{ old('occupied_rooms', $b->rooms->count()) }}"></div>
                        <div class="col-md-4"><label class="form-label small" for="ci-rem">Remarks</label><input id="ci-rem" name="stay_remarks" class="form-control" maxlength="1000"></div>
                        @foreach (['actual_check_in_at', 'arrived_guests', 'occupied_rooms'] as $f)@error($f)<div class="col-12 text-danger small">{{ $message }}</div>@enderror @endforeach
                        <div class="col-12 text-end"><button class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Record Check-in</button></div>
                    </form>
                </div>
            @endcan
            @can('checkOut', $b)
                <div class="card action-card mb-3" id="stay">
                    <div class="card-header">Guest Check-out</div>
                    <form method="POST" action="{{ route('hostel.bookings.check-out', $b) }}" class="card-body row g-2 align-items-end" novalidate>
                        @csrf
                        <div class="col-md-4"><label class="form-label small" for="co-at">Actual Departure</label>
                            <input type="datetime-local" id="co-at" name="actual_check_out_at" class="form-control @error('actual_check_out_at') is-invalid @enderror" value="{{ old('actual_check_out_at', now()->format('Y-m-d\TH:i')) }}"></div>
                        <div class="col-md-6"><label class="form-label small" for="co-rem">Remarks</label><input id="co-rem" name="stay_remarks" class="form-control" maxlength="1000"></div>
                        <div class="col-md-2 d-grid"><button class="btn btn-primary">Check Out</button></div>
                        @error('actual_check_out_at')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
                    </form>
                </div>
            @endcan

            {{-- Details --}}
            <div class="card mb-3">
                <div class="card-header">Booking Details</div>
                <div class="card-body">
                    <dl class="row kv mb-0">
                        <dt class="col-sm-3">Guest Name</dt><dd class="col-sm-3">{{ $b->guest_name }}</dd>
                        <dt class="col-sm-3">CNIC / Passport</dt>
                        <dd class="col-sm-3">
                            @if ($revealed)
                                <span class="fw-semibold">{{ $b->cnic_passport }}</span> <span class="small-2 text-muted-2">(viewing logged)</span>
                            @else
                                {{ $b->maskedCnic() }}
                                @can('viewIdentity', $b)
                                    <form method="POST" action="{{ route('hostel.bookings.identity', $b) }}" class="d-inline">@csrf<button class="btn btn-link btn-sm p-0 ms-1 align-baseline">Show</button></form>
                                @endcan
                            @endif
                        </dd>
                        <dt class="col-sm-3">Mobile</dt><dd class="col-sm-3">{{ $b->mobile }}</dd>
                        <dt class="col-sm-3">Organization</dt><dd class="col-sm-3">{{ $b->organization ?? '—' }}</dd>
                        <dt class="col-sm-3">Referred By</dt><dd class="col-sm-3">{{ $b->referred_by ?? '—' }}{{ $b->referrer_designation ? ', ' . $b->referrer_designation : '' }}</dd>
                        <dt class="col-sm-3">Requested By</dt><dd class="col-sm-3">{{ $b->requested_by_email }}</dd>
                        <dt class="col-sm-3">District / HQ</dt><dd class="col-sm-3">{{ $b->district->name }}</dd>
                        <dt class="col-sm-3">Purpose</dt><dd class="col-sm-3">{{ $b->purposeLabel() }}</dd>
                        <dt class="col-sm-3">Guests / Rooms</dt><dd class="col-sm-3">{{ $b->guest_count }} / {{ $b->room_count }}</dd>
                        <dt class="col-sm-3">Request Date</dt><dd class="col-sm-3">{{ $b->request_date->format('d M Y') }}</dd>
                        @if ($b->purpose_details)<dt class="col-sm-3">Purpose Details</dt><dd class="col-sm-9">{{ $b->purpose_details }}</dd>@endif
                        @if ($b->remarks)<dt class="col-sm-3">Remarks</dt><dd class="col-sm-9">{{ $b->remarks }}</dd>@endif
                        <dt class="col-sm-3">Assigned Rooms</dt><dd class="col-sm-9">{{ $b->rooms->isEmpty() ? 'None (rooms are assigned on approval)' : $b->rooms->map(fn ($r) => $r->room_number . ' (' . $r->typeLabel() . ')')->implode(', ') }}</dd>
                        @if ($b->actual_check_in_at)
                            <dt class="col-sm-3">Actual Stay</dt><dd class="col-sm-9">In {{ $b->actual_check_in_at->format('d M Y H:i') }}{{ $b->actual_check_out_at ? ' · Out ' . $b->actual_check_out_at->format('d M Y H:i') : '' }} · {{ $b->arrived_guests }} guest(s), {{ $b->occupied_rooms }} room(s)</dd>
                        @endif
                        <dt class="col-sm-3">Submitted By</dt><dd class="col-sm-9 mb-0">{{ $b->submitter->name ?? '—' }}{{ $b->submitted_at ? ' on ' . $b->submitted_at->format('d M Y H:i') : '' }}</dd>
                    </dl>
                </div>
            </div>

            {{-- Documents --}}
            <div class="card mb-3">
                <div class="card-header">Supporting Documents</div>
                <div class="card-body py-2">
                    @forelse ($b->documents as $doc)
                        <div class="doc-row">
                            <i class="bi {{ $doc->icon() }} doc-icon"></i>
                            <div class="flex-grow-1 min-w-0"><a href="{{ route('budget.documents.show', [$doc, 'inline' => 1]) }}" target="_blank" rel="noopener" class="fw-medium text-truncate d-block">{{ $doc->original_name }}</a>
                                <div class="small-2 text-muted-2">{{ $doc->kindLabel() }} · {{ $doc->humanSize() }}</div></div>
                            <a href="{{ route('budget.documents.show', $doc) }}" class="btn btn-sm btn-light" aria-label="Download"><i class="bi bi-download"></i></a>
                            @if ($user->can('update', $b) && $doc->uploaded_by === $user->id)
                                <form method="POST" action="{{ route('hostel.bookings.documents.destroy', [$b, $doc]) }}" data-confirm="Remove {{ $doc->original_name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" aria-label="Remove"><i class="bi bi-trash"></i></button></form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted-2 small my-2">No documents attached.</p>
                    @endforelse
                </div>
                @can('update', $b)
                    <form method="POST" action="{{ route('hostel.bookings.documents.store', $b) }}" enctype="multipart/form-data" class="card-body border-top row g-2 align-items-end">
                        @csrf
                        <div class="col-md-3"><select name="kind" class="form-select form-select-sm" aria-label="Type"><option value="referral">Referral Letter</option><option value="supporting">Supporting Document</option></select></div>
                        <div class="col-md-7"><input type="file" name="files[]" multiple class="form-control form-control-sm" aria-label="Files"></div>
                        <div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-primary">Upload</button></div>
                    </form>
                @endcan
            </div>

            @can('cancel', $b)
                <div class="card mb-3">
                    <div class="card-header">Cancel Booking</div>
                    <form method="POST" action="{{ route('hostel.bookings.cancel', $b) }}" class="card-body row g-2 align-items-end" data-confirm="Cancel booking {{ $b->booking_no }}?">
                        @csrf
                        <div class="col-md-10"><input name="remarks" class="form-control @error('remarks') is-invalid @enderror" placeholder="Reason for cancellation (required)" maxlength="2000" aria-label="Reason"></div>
                        <div class="col-md-2 d-grid"><button class="btn btn-outline-danger">Cancel</button></div>
                    </form>
                </div>
            @endcan
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-body small">
                    <div class="fw-semibold mb-1">{{ $b->hostel->name }}</div>
                    <div class="text-muted-2">{{ $b->hostel->address }}</div>
                    <a href="{{ route('hostel.hostels.show', [$b->hostel, 'from' => $b->check_in->toDateString(), 'to' => $b->check_out->toDateString()]) }}" class="d-inline-block mt-2">Room availability for these dates</a>
                </div>
            </div>
            @include('budget.partials.history', ['approvals' => $b->approvals])
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var d = document.getElementById('decision'), box = document.querySelector('[data-approve]'), tally = document.getElementById('roomTally');
            if (!d || !box) return;
            var need = {{ $b->room_count }}, guests = {{ $b->guest_count }};
            function sync() {
                box.hidden = d.value !== 'approve';
                var n = 0, beds = 0;
                box.querySelectorAll('input:checked').forEach(function (i) { n++; beds += +document.querySelector('label[for="' + i.id + '"]').dataset.cap; });
                tally.textContent = n + ' of ' + need + ' room(s) selected · ' + beds + ' bed(s) for ' + guests + ' guest(s)';
                tally.className = 'form-text ' + (n === need && beds >= guests ? 'text-primary' : 'text-danger');
            }
            d.addEventListener('change', sync); box.addEventListener('change', sync); sync();
        })();
    </script>
@endpush
