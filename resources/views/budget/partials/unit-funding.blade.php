{{-- Decision field for a unit budget (Jawan Markaz center, hostel): which central allocation funds it. --}}
@php($options = $record->fundingOptionsForDecision())
<div class="col-12">
    <label class="form-label" for="wf-allocation">Fund from central allocation <span class="text-danger">*</span></label>
    @if ($options->isEmpty())
        <div class="alert alert-warning small mb-0">No approved “{{ $record->fundingHeadLabel() }}” allocation for
            {{ $record->financialYear->label }} has @pkr($record->amountToFund()) available. Ask Finance to allocate or transfer funds in Budget &amp; Planning, or return this budget.</div>
    @else
        <select id="wf-allocation" name="allocation_id" class="form-select @error('allocation_id') is-invalid @enderror">
            @foreach ($options as $a)
                <option value="{{ $a->id }}" @selected(old('allocation_id') == $a->id)>{{ $a->reference_no }} · {{ $a->office->name }} — available PKR {{ number_format($a->available()) }}</option>
            @endforeach
        </select>
        @error('allocation_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Approving commits @pkr($record->amountToFund()) in the central budget for this {{ $record->unitNoun() }}.</div>
    @endif
</div>
