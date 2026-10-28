{{-- @include('activity.partials.documents', ['documents' => ..., 'canDelete' => bool]) --}}
@forelse ($documents as $doc)
    <div class="doc-row">
        <i class="bi {{ $doc->icon() }} doc-icon"></i>
        <div class="flex-grow-1 min-w-0">
            <a href="{{ route('activity.documents.show', [$doc, 'inline' => 1]) }}" target="_blank" rel="noopener" class="fw-medium text-truncate d-block">{{ $doc->original_name }}</a>
            <div class="small-2 text-muted-2">{{ $doc->kindLabel() }} · {{ $doc->humanSize() }} · {{ $doc->created_at->format('d M Y') }}{{ $doc->uploader ? ' · ' . $doc->uploader->name : '' }}</div>
        </div>
        <a href="{{ route('activity.documents.show', $doc) }}" class="btn btn-sm btn-light" title="Download" aria-label="Download {{ $doc->original_name }}"><i class="bi bi-download"></i></a>
        @if ($canDelete ?? false)
            <form method="POST" action="{{ route('activity.documents.destroy', $doc) }}" data-confirm="Remove {{ $doc->original_name }}?">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-light text-danger" title="Remove" aria-label="Remove {{ $doc->original_name }}"><i class="bi bi-trash"></i></button>
            </form>
        @endif
    </div>
@empty
    <p class="text-muted-2 small mb-0">No documents uploaded.</p>
@endforelse
