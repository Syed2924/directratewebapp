<?php

namespace App\Http\Controllers\Budget;

use App\Http\Controllers\Controller;
use App\Models\BudgetCommitment;
use App\Models\FinancialDocument;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Budget documents, approvals and receipts. Private files: every request
 * re-checks access to the owning record.
 */
class DocumentController extends Controller
{
    public function show(Request $request, FinancialDocument $document)
    {
        $this->authorize('view', $document->documentable);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $inline = $request->boolean('inline') && ($document->isImage() || $document->mime === 'application/pdf');

        return Storage::disk('local')->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], $inline ? 'inline' : 'attachment');
    }

    public function store(Request $request, string $type, int $id)
    {
        $record = $type === 'commitment' ? BudgetCommitment::findOrFail($id) : WorkflowController::resolve($type, $id);
        abort_unless(self::canAttach($request->user(), $record), 403);
        $request->validate([
            'kind' => ['required', Rule::in(array_keys(config('budget.document_kinds')))],
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:' . config('activity.upload_max_kb'), 'mimes:' . implode(',', config('activity.upload_mimes'))],
        ], ['files.required' => 'Choose at least one file to upload.']);

        foreach ($request->file('files') as $file) {
            FinancialDocument::storeFor($record, $file, $request->input('kind'), $request->user());
        }
        Audit::log('budget.document_uploaded', $record, ['entity_label' => $record->referenceLabel(), 'after' => ['kind' => $request->input('kind'), 'files' => count($request->file('files'))]]);

        return back()->with('status', 'Document uploaded.');
    }

    public function destroy(Request $request, FinancialDocument $document)
    {
        $record = $document->documentable;
        abort_unless($document->uploaded_by === $request->user()->id && self::canAttach($request->user(), $record) && ! in_array($record->status, ['approved', 'verified'], true), 403);

        $document->delete();
        Audit::log('budget.document_removed', $record, ['entity_label' => $record->referenceLabel(), 'description' => "Removed \"{$document->original_name}\""]);

        return back()->with('status', 'Document removed.');
    }

    /** Anyone who prepares or records financial entries in scope may attach evidence until the entry is rejected. */
    public static function canAttach($user, Model $record): bool
    {
        return $user->can('view', $record) && $record->isWithinScopeOf($user)
            && ($user->hasPermission('budget.edit') || $user->hasPermission('budget.create') || $user->hasPermission('budget.record_payment'))
            && ! in_array($record->status, ['rejected', 'cancelled'], true);
    }
}
