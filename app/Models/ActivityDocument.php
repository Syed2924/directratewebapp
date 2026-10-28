<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A file attached to a proposal or an activity. Files live on the private
 * "local" disk and are only served through an authorized download route.
 */
class ActivityDocument extends Model
{
    protected $fillable = ['kind', 'original_name', 'path', 'mime', 'size', 'uploaded_by'];

    /** Top-level storage folder on the private disk. */
    protected static string $folder = 'activity';

    public function documentable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function storeFor(Model $owner, UploadedFile $file, string $kind, User $user): self
    {
        $folder = static::$folder . '/' . strtolower(class_basename($owner)) . '-' . $owner->getKey();

        return $owner->documents()->create([
            'kind' => $kind,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'path' => $file->store($folder, 'local'),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $user->id,
        ]);
    }

    public function kindLabel(): string
    {
        return config("activity.document_kinds.{$this->kind}", ucfirst($this->kind));
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function icon(): string
    {
        return match (true) {
            $this->isImage() => 'bi-file-earmark-image',
            str_contains((string) $this->mime, 'pdf') => 'bi-file-earmark-pdf',
            default => 'bi-file-earmark-text',
        };
    }

    public function humanSize(): string
    {
        $kb = $this->size / 1024;

        return $kb >= 1024 ? number_format($kb / 1024, 1) . ' MB' : number_format(max($kb, 1)) . ' KB';
    }

    protected static function booted()
    {
        static::deleted(fn (self $doc) => Storage::disk('local')->delete($doc->path));
    }
}
