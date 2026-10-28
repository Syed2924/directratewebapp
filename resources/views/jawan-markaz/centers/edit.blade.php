@extends('layouts.app')
@section('title', 'Edit ' . $center->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>Jawan Markaz Center Management</h1>
            <p>Update the information for {{ $center->name }}. The name and district are managed under Organization Units.</p>
        </div>
        <a href="{{ route('jawan-markaz.centers.show', $center) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" action="{{ route('jawan-markaz.centers.update', $center) }}" novalidate>
        @csrf @method('PUT')
        <div class="card">
            <div class="card-body p-4">
                <div class="section-title">Center Information</div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><label class="form-label">Center Name</label><input class="form-control bg-light" value="{{ $center->name }}" readonly></div>
                    <div class="col-md-6"><label class="form-label">District</label><input class="form-control bg-light" value="{{ $center->district->name }}" readonly></div>
                    <div class="col-md-6">
                        <label class="form-label" for="location">Location / Address <span class="text-danger">*</span></label>
                        <input id="location" name="location" class="form-control @error('location') is-invalid @enderror" value="{{ old('location', $center->location) }}" maxlength="255" placeholder="e.g. Main Road, Mardan, Khyber Pakhtunkhwa">
                        @error('location')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="incharge_id">Assigned Staff (Center In-charge)</label>
                        <select id="incharge_id" name="incharge_id" class="form-select">
                            <option value="">— Not assigned —</option>
                            @foreach ($staff as $s)<option value="{{ $s->id }}" @selected(old('incharge_id', $center->incharge_id) == $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                        @if ($staff->isEmpty())<div class="form-text">No Jawan Markaz staff are assigned to this center yet.</div>@endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="is_active">Status <span class="text-danger">*</span></label>
                        <select id="is_active" name="is_active" class="form-select">
                            <option value="1" @selected(old('is_active', $center->is_active ? '1' : '0') === '1')>Active</option>
                            <option value="0" @selected(old('is_active', $center->is_active ? '1' : '0') === '0')>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="section-title">Contact Details</div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone Number <span class="text-danger">*</span></label>
                        <input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $center->phone) }}" placeholder="+92 300 1234567" maxlength="30">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $center->email) }}" maxlength="150">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" rows="2" class="form-control" maxlength="2000" placeholder="Any additional information about the center.">{{ old('remarks', $center->remarks) }}</textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2 py-3">
                <a href="{{ route('jawan-markaz.centers.show', $center) }}" class="btn btn-light">Cancel</a>
                <button class="btn btn-primary">Save Center</button>
            </div>
        </div>
    </form>
@endsection
