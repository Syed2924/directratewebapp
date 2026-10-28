@extends('layouts.app')
@section('title', $booking->exists ? 'Edit Booking' : 'Hostel Booking Request')

@section('content')
    <div class="page-head">
        <div>
            <h1>Hostel Booking Request</h1>
            <p>{{ $booking->exists ? 'Correct the request and resubmit it.' : 'Fill in the details below to submit a new hostel booking request.' }}</p>
        </div>
        <a href="{{ $booking->exists ? route('hostel.bookings.show', $booking) : route('hostel.bookings.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <div class="alert alert-info small d-flex gap-2"><i class="bi bi-info-circle"></i>
        <div>Please ensure all information is correct. The request will be reviewed by authorized staff; it is <strong>not confirmed</strong> and holds no rooms until approved.</div></div>
    @if ($booking->status === 'returned' && ($ret = $booking->approvals->where('action', 'returned')->last()))
        <div class="alert alert-warning"><strong>Returned for correction</strong> by {{ $ret->user->name ?? '—' }}: {{ $ret->remarks }}</div>
    @endif

    <form method="POST" enctype="multipart/form-data" novalidate action="{{ $booking->exists ? route('hostel.bookings.update', $booking) : route('hostel.bookings.store') }}">
        @csrf
        @if ($booking->exists) @method('PUT') @endif
        <div class="card">
            <div class="card-body p-4">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label d-flex justify-content-between" for="ref">Booking ID <span class="auto-tag">{{ $booking->exists ? 'Assigned' : 'Auto Generated' }}</span></label>
                        <input id="ref" class="form-control bg-light fw-medium" value="{{ $reference }}" readonly>
                    </div>
                    <div class="col-md-4"><label class="form-label">Request Date</label><input class="form-control bg-light" value="{{ ($booking->request_date ?? now())->format('d M Y') }}" readonly></div>
                    <div class="col-md-4">
                        <label class="form-label" for="requested_by_email">Requested By Email <span class="text-danger">*</span></label>
                        <input type="email" id="requested_by_email" name="requested_by_email" class="form-control @error('requested_by_email') is-invalid @enderror" value="{{ old('requested_by_email', $booking->requested_by_email) }}" maxlength="150">
                        @error('requested_by_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="section-title">Guest Information</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label" for="guest_name">Guest Name <span class="text-danger">*</span></label>
                        <input id="guest_name" name="guest_name" class="form-control @error('guest_name') is-invalid @enderror" value="{{ old('guest_name', $booking->guest_name) }}" maxlength="150" placeholder="Guest or responsible representative">
                        @error('guest_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="cnic_passport">CNIC / Passport {!! $booking->exists ? '' : '<span class="text-danger">*</span>' !!}</label>
                        <input id="cnic_passport" name="cnic_passport" class="form-control @error('cnic_passport') is-invalid @enderror" value="{{ old('cnic_passport') }}" maxlength="30" autocomplete="off"
                               placeholder="{{ $booking->exists ? 'Stored securely — leave blank to keep ' . $booking->maskedCnic() : '12345-1234567-1' }}">
                        @error('cnic_passport')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text"><i class="bi bi-shield-lock me-1"></i>Encrypted and shown masked to other users.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="mobile">Mobile <span class="text-danger">*</span></label>
                        <input id="mobile" name="mobile" class="form-control @error('mobile') is-invalid @enderror" value="{{ old('mobile', $booking->mobile) }}" maxlength="30" placeholder="+92 300 1234567">
                        @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="organization">Organization</label>
                        <input id="organization" name="organization" class="form-control" value="{{ old('organization', $booking->organization) }}" maxlength="200" placeholder="Organization represented">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="referred_by">Referred By</label>
                        <input id="referred_by" name="referred_by" class="form-control" value="{{ old('referred_by', $booking->referred_by) }}" maxlength="150" placeholder="Person or office making the referral">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="referrer_designation">Referrer Designation</label>
                        <input id="referrer_designation" name="referrer_designation" class="form-control" value="{{ old('referrer_designation', $booking->referrer_designation) }}" maxlength="150">
                    </div>
                </div>

                <div class="section-title">Booking Details</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label" for="district_id">District / HQ <span class="text-danger">*</span></label>
                        <select id="district_id" name="district_id" class="form-select @error('district_id') is-invalid @enderror">
                            <option value="">Select district</option>
                            @foreach ($districts as $d)<option value="{{ $d->id }}" @selected(old('district_id', $booking->district_id) == $d->id)>{{ $d->name }}</option>@endforeach
                        </select>
                        @error('district_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="hostel_id">Youth Hostel <span class="text-danger">*</span></label>
                        <select id="hostel_id" name="hostel_id" class="form-select @error('hostel_id') is-invalid @enderror">
                            <option value="">Select hostel</option>
                            @foreach ($hostels as $h)<option value="{{ $h->id }}" @selected(old('hostel_id', $booking->hostel_id) == $h->id)>{{ $h->name }} ({{ $h->rooms_count }} rooms)</option>@endforeach
                        </select>
                        @error('hostel_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="purpose">Purpose of Visit <span class="text-danger">*</span></label>
                        <select id="purpose" name="purpose" class="form-select @error('purpose') is-invalid @enderror">
                            <option value="">Select purpose</option>
                            @foreach (config('hostel.purposes') as $k => $l)<option value="{{ $k }}" @selected(old('purpose', $booking->purpose) === $k)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="check_in">Check-in Date <span class="text-danger">*</span></label>
                        <input type="date" id="check_in" name="check_in" min="{{ now()->toDateString() }}" class="form-control @error('check_in') is-invalid @enderror" value="{{ old('check_in', $booking->check_in?->toDateString()) }}">
                        @error('check_in')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="check_out">Check-out Date <span class="text-danger">*</span></label>
                        <input type="date" id="check_out" name="check_out" class="form-control @error('check_out') is-invalid @enderror" value="{{ old('check_out', $booking->check_out?->toDateString()) }}">
                        @error('check_out')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="guest_count">Guests <span class="text-danger">*</span></label>
                        <input type="number" min="1" id="guest_count" name="guest_count" class="form-control @error('guest_count') is-invalid @enderror" value="{{ old('guest_count', $booking->guest_count) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="room_count">Rooms <span class="text-danger">*</span></label>
                        <input type="number" min="1" id="room_count" name="room_count" class="form-control @error('room_count') is-invalid @enderror" value="{{ old('room_count', $booking->room_count) }}">
                        @error('room_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="purpose_details">Purpose Details</label>
                        <input id="purpose_details" name="purpose_details" class="form-control" value="{{ old('purpose_details', $booking->purpose_details) }}" maxlength="500" placeholder="e.g. Participants of the provincial debate competition">
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Supporting Document <span class="text-muted-2 fw-normal small">(required to submit)</span></label>
                        @include('activity.partials.dropzone', ['name' => 'document', 'id' => 'document', 'hint' => 'Referral letter or supporting document (PDF, JPG, PNG)'])
                        @error('document')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @if ($booking->exists && $booking->documents->isNotEmpty())<div class="small mt-1">Attached: {{ $booking->documents->pluck('original_name')->implode(', ') }}</div>@endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" rows="5" class="form-control" maxlength="2000" placeholder="Enter any additional remarks">{{ old('remarks', $booking->remarks) }}</textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2 py-3">
                <button name="intent" value="draft" class="btn btn-outline-secondary">Save Draft</button>
                <button name="intent" value="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>{{ $booking->status === 'returned' ? 'Resubmit Request' : 'Submit Request' }}</button>
            </div>
        </div>
    </form>
@endsection
