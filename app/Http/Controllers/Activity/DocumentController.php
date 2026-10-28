<?php

namespace App\Http\Controllers\Activity;

use App\Http\Controllers\Controller;
use App\Models\ActivityDocument;
use App\Models\ActivityProposal;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Files are never publicly reachable: every download re-checks access to the owning record.
 */
class DocumentController extends Controller
{
    public function show(Request $request, ActivityDocument $document)
    {
        $this->authorize('view', $document->documentable);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $inline = $request->boolean('inline') && ($document->isImage() || $document->mime === 'application/pdf');
        $disposition = $inline ? 'inline' : 'attachment';

        return Storage::disk('local')->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }

    public function destroy(Request $request, ActivityDocument $document)
    {
        $owner = $document->documentable;
        $this->authorize('update', $owner);

        $document->delete();
        $tracking = $owner instanceof ActivityProposal ? $owner->tracking_no : $owner->proposal->tracking_no;
        Audit::log('activity.document_removed', $owner, ['entity_label' => $tracking, 'description' => "{$tracking}: removed {$document->kindLabel()} \"{$document->original_name}\""]);

        return back()->with('status', 'File removed.');
    }
}
