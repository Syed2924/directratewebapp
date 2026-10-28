{{-- @include('budget.partials.documents', ['record' => $model, 'type' => 'allocation']) --}}
@php($canAttach = App\Http\Controllers\Budget\DocumentController::canAttach(auth()->user(), $record))
<div class="card mb-3">
    <div class="card-header">Documents</div>
    <div class="card-body py-2">
        @forelse ($record->documents as $doc)
            <div class="doc-row">
                <i class="bi {{ $doc->icon() }} doc-icon"></i>
                <div class="flex-grow-1 min-w-0">
                    <a href="{{ route('budget.documents.show', [$doc, 'inline' => 1]) }}" target="_blank" rel="noopener" class="fw-medium text-truncate d-block">{{ $doc->original_name }}</a>
                    <div class="small-2 text-muted-2">{{ $doc->kindLabel() }} · {{ $doc->humanSize() }} · {{ $doc->created_at->format('d M Y') }}{{ $doc->uploader ? ' · ' . $doc->uploader->name : '' }}</div>
                </div>
                <a href="{{ route('budget.documents.show', $doc) }}" class="btn btn-sm btn-light" aria-label="Download {{ $doc->original_name }}"><i class="bi bi-download"></i></a>
                @if ($canAttach && $doc->uploaded_by === auth()->id() && ! in_array($record->status, ['approved', 'verified'], true))
                    <form method="POST" action="{{ route('budget.documents.destroy', $doc) }}" data-confirm="Remove {{ $doc->original_name }}?">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-light text-danger" aria-label="Remove {{ $doc->original_name }}"><i class="bi bi-trash"></i></button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-muted-2 small my-2">No documents attached.</p>
        @endforelse
    </div>
    @if ($canAttach)
        <form method="POST" action="{{ route('budget.documents.store', [$type, $record->id]) }}" enctype="multipart/form-data" class="card-body border-top row g-2 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label small" for="doc-kind-{{ $type }}">Type</label>
                <select id="doc-kind-{{ $type }}" name="kind" class="form-select form-select-sm">
                    @foreach (config('budget.document_kinds') as $k => $l)
                        <option value="{{ $k }}" @selected($k === ($defaultKind ?? 'supporting'))>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small" for="doc-files-{{ $type }}">Files</label>
                <input type="file" id="doc-files-{{ $type }}" name="files[]" multiple class="form-control form-control-sm" accept=".{{ implode(',.', config('activity.upload_mimes')) }}">
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-sm btn-outline-primary">Upload</button></div>
        </form>
    @endif
</div>
