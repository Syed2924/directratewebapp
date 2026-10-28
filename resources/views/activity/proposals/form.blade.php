@extends('layouts.app')
@section('title', $proposal->exists ? 'Edit Proposal' : 'Activity Proposal Receiving')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $proposal->exists ? 'Edit Proposal ' . $proposal->tracking_no : 'Activity Proposal Receiving' }}</h1>
            <p>{{ $proposal->exists ? 'Correct or complete the details of this proposal.' : 'Enter the details of the proposal and register it in the system.' }}</p>
        </div>
        <a href="{{ $proposal->exists ? route('activity.proposals.show', $proposal) : route('activity.proposals.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    @if ($proposal->exists && $proposal->status === 'incomplete' && $proposal->missing_information)
        <div class="alert alert-warning"><strong>Missing information:</strong> {{ $proposal->missing_information }}</div>
    @endif

    <form method="POST" enctype="multipart/form-data" novalidate
          action="{{ $proposal->exists ? route('activity.proposals.update', $proposal) : route('activity.proposals.store') }}">
        @csrf
        @if ($proposal->exists) @method('PUT') @endif

        <div class="card">
            <div class="card-body p-4">
                <div class="section-title">Applicant / Organization Information</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label d-flex justify-content-between" for="tracking">Tracking Number
                            <span class="auto-tag"><i class="bi bi-magic me-1"></i>{{ $proposal->exists ? 'Assigned' : 'Auto Generated' }}</span></label>
                        <input id="tracking" class="form-control bg-light fw-medium" value="{{ $trackingPreview }}" readonly>
                        @unless ($proposal->exists)<div class="form-text">Reserved when you save; the final number may differ if another proposal is saved first.</div>@endunless
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="received_on">Date Received <span class="text-danger">*</span></label>
                        <input type="date" id="received_on" name="received_on" max="{{ now()->toDateString() }}" class="form-control @error('received_on') is-invalid @enderror"
                               value="{{ old('received_on', $proposal->received_on?->toDateString()) }}" required>
                        @error('received_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="applicant_name">Applicant / Organization Name <span class="text-danger">*</span></label>
                        <input id="applicant_name" name="applicant_name" class="form-control @error('applicant_name') is-invalid @enderror" value="{{ old('applicant_name', $proposal->applicant_name) }}" placeholder="Enter name of organization / applicant" required maxlength="150">
                        @error('applicant_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="contact_person">Contact Person Name</label>
                        <input id="contact_person" name="contact_person" class="form-control @error('contact_person') is-invalid @enderror" value="{{ old('contact_person', $proposal->contact_person) }}" placeholder="Enter contact person name" maxlength="120">
                        @error('contact_person')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input id="phone" name="phone" type="tel" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $proposal->phone) }}" placeholder="+92 300 1234567" maxlength="30">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email Address</label>
                        <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $proposal->email) }}" placeholder="example@domain.com" maxlength="150">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <span class="form-label d-block">Submission Method <span class="text-danger">*</span></span>
                        @php($method = old('submission_method', $proposal->submission_method))
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="submission_method" id="sm-physical" value="physical" @checked($method === 'physical')>
                            <label class="form-check-label" for="sm-physical">Physical Paper</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="submission_method" id="sm-digital" value="digital" @checked($method === 'digital')>
                            <label class="form-check-label" for="sm-digital">Digital Document</label>
                        </div>
                        @error('submission_method')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="section-title">Activity Details</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="title">Activity Title <span class="text-danger">*</span></label>
                        <input id="title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $proposal->title) }}" placeholder="Enter activity title" required maxlength="200">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="category">Category <span class="text-danger">*</span></label>
                        <select id="category" name="category" class="form-select @error('category') is-invalid @enderror" required>
                            <option value="">Select category</option>
                            @foreach (config('activity.categories') as $key => $label)
                                <option value="{{ $key }}" @selected(old('category', $proposal->category) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <span class="form-label d-block">Proposed Date Range</span>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="small text-muted-2" for="proposed_start">From</label>
                                <input type="date" id="proposed_start" name="proposed_start" class="form-control @error('proposed_start') is-invalid @enderror" value="{{ old('proposed_start', $proposal->proposed_start?->toDateString()) }}">
                            </div>
                            <div class="col-6">
                                <label class="small text-muted-2" for="proposed_end">To</label>
                                <input type="date" id="proposed_end" name="proposed_end" class="form-control @error('proposed_end') is-invalid @enderror" value="{{ old('proposed_end', $proposal->proposed_end?->toDateString()) }}">
                            </div>
                        </div>
                        @error('proposed_end')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <span class="form-label d-block">Expected Participant Breakdown</span>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="small text-muted-2" for="participants_male">Male</label>
                                <input type="number" min="0" id="participants_male" name="participants_male" class="form-control @error('participants_male') is-invalid @enderror" value="{{ old('participants_male', $proposal->participants_male ?? 0) }}" data-sum="exp">
                            </div>
                            <div class="col-4">
                                <label class="small text-muted-2" for="participants_female">Female</label>
                                <input type="number" min="0" id="participants_female" name="participants_female" class="form-control @error('participants_female') is-invalid @enderror" value="{{ old('participants_female', $proposal->participants_female ?? 0) }}" data-sum="exp">
                            </div>
                            <div class="col-4">
                                <label class="small text-muted-2" for="exp-total">Total</label>
                                <input id="exp-total" class="form-control bg-light" readonly data-sum-total="exp">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="district_id">Proposed District <span class="text-danger">*</span></label>
                        <select id="district_id" name="district_id" class="form-select @error('district_id') is-invalid @enderror" required>
                            <option value="">Select district</option>
                            @foreach ($districts as $d)
                                <option value="{{ $d->id }}" @selected(old('district_id', $proposal->district_id) == $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                        @error('district_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if ($districts->isEmpty())<div class="form-text text-danger">No districts are in your scope. Ask an administrator to assign one.</div>@endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="estimated_budget">Estimated Budget (PKR) <span class="text-danger">*</span></label>
                        <input type="number" min="0" step="0.01" id="estimated_budget" name="estimated_budget" class="form-control @error('estimated_budget') is-invalid @enderror" value="{{ old('estimated_budget', $proposal->estimated_budget) }}" placeholder="Enter estimated budget" required>
                        @error('estimated_budget')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="venue">Proposed Venue</label>
                        <input id="venue" name="venue" class="form-control @error('venue') is-invalid @enderror" value="{{ old('venue', $proposal->venue) }}" placeholder="e.g. Mardan Community Center" maxlength="200">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="description">Brief Description / Objectives</label>
                        <textarea id="description" name="description" rows="2" class="form-control @error('description') is-invalid @enderror" maxlength="5000">{{ old('description', $proposal->description) }}</textarea>
                    </div>
                </div>

                <div class="section-title">Upload Documents</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Scanned Proposal</label>
                        @include('activity.partials.dropzone', ['name' => 'proposal_file', 'id' => 'proposal_file'])
                        @error('proposal_file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Attachments <span class="text-muted-2 fw-normal">(optional, up to 10)</span></label>
                        @include('activity.partials.dropzone', ['name' => 'attachments[]', 'id' => 'attachments', 'multiple' => true])
                        @foreach ($errors->get('attachments*') as $messages)
                            @foreach ($messages as $m)<div class="text-danger small mt-1">{{ $m }}</div>@endforeach
                        @endforeach
                    </div>
                    @if ($proposal->exists && $proposal->documents->isNotEmpty())
                        <div class="col-12">
                            <div class="small fw-semibold mb-1">Already uploaded</div>
                            @include('activity.partials.documents', ['documents' => $proposal->documents, 'canDelete' => false])
                        </div>
                    @endif
                </div>
            </div>

            <div class="card-footer bg-white d-flex flex-wrap justify-content-end gap-2 py-3">
                @if (! $proposal->exists || $proposal->status === 'draft')
                    <button name="intent" value="draft" class="btn btn-outline-secondary"><i class="bi bi-save me-1"></i>Save Draft</button>
                    <button name="intent" value="register" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Register Proposal</button>
                @else
                    <button name="intent" value="save" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save Changes</button>
                @endif
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            var inputs = document.querySelectorAll('[data-sum="exp"]'), total = document.querySelector('[data-sum-total="exp"]');
            function sum() { var t = 0; inputs.forEach(function (i) { t += parseInt(i.value, 10) || 0; }); total.value = t; }
            inputs.forEach(function (i) { i.addEventListener('input', sum); });
            sum();
        })();
    </script>
@endpush
