@extends('layouts.app')
@section('title', 'Edit ' . $hostel->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>Hostel Management</h1>
            <p>Update {{ $hostel->name }}. The name and district are managed under Organization Units; rooms on the availability page.</p>
        </div>
        <a href="{{ route('hostel.hostels.show', $hostel) }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <form method="POST" action="{{ route('hostel.hostels.update', $hostel) }}" novalidate>
        @csrf @method('PUT')
        <div class="card">
            <div class="card-body p-4 row g-3">
                <div class="col-md-6"><label class="form-label">Hostel Name</label><input class="form-control bg-light" value="{{ $hostel->name }}" readonly></div>
                <div class="col-md-6"><label class="form-label">District / HQ</label><input class="form-control bg-light" value="{{ $hostel->district->name }}" readonly></div>
                <div class="col-md-8">
                    <label class="form-label" for="address">Address &amp; Location <span class="text-danger">*</span></label>
                    <input id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $hostel->address) }}" maxlength="255">
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="is_active">Operational Status</label>
                    <select id="is_active" name="is_active" class="form-select">
                        <option value="1" @selected(old('is_active', $hostel->is_active ? '1' : '0') === '1')>Active</option>
                        <option value="0" @selected(old('is_active', $hostel->is_active ? '1' : '0') === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="phone">Phone <span class="text-danger">*</span></label>
                    <input id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $hostel->phone) }}" maxlength="30">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $hostel->email) }}" maxlength="150">
                </div>
                <div class="col-12">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" rows="2" class="form-control" maxlength="2000">{{ old('remarks', $hostel->remarks) }}</textarea>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-end gap-2 py-3">
                <a href="{{ route('hostel.hostels.show', $hostel) }}" class="btn btn-light">Cancel</a>
                <button class="btn btn-primary">Save Hostel</button>
            </div>
        </div>
    </form>
@endsection
