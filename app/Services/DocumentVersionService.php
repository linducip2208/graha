<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentVersionService
{
    public function __construct(private AuditTrail $audit) {}

    public function add(Document $document, UploadedFile $file, User $actor, string $reason): DocumentVersion
    {
        if (! in_array($file->getMimeType(), ['application/pdf', 'image/jpeg', 'image/png'], true) || $file->getSize() > 20 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'File harus PDF/JPG/PNG dan maksimal 20 MB.']);
        }

        return DB::transaction(function () use ($document, $file, $actor, $reason) {
            $document = Document::lockForUpdate()->findOrFail($document->id);
            $next = ((int) $document->versions()->max('version')) + 1;
            $path = $file->store("companies/{$document->company_id}/documents/{$document->id}", 'local');
            try {
                $version = $document->versions()->create([
                    'version' => $next, 'revision' => (string) ($next - 1), 'disk' => 'local', 'path' => $path,
                    'sha256' => hash_file('sha256', Storage::disk('local')->path($path)), 'size_bytes' => $file->getSize(),
                    'mime_type' => $file->getMimeType(), 'change_reason' => $reason, 'created_by' => $actor->id,
                ]);
                $document->update(['workflow_status' => 'draft', 'signature_status' => 'unsigned']);
                $this->audit->record($document->company_id, $actor->id, 'document.version_created', $version);

                return $version;
            } catch (\Throwable $exception) {
                Storage::disk('local')->delete($path);
                throw $exception;
            }
        }, 3);
    }

    public function lockSigned(DocumentVersion $version): void
    {
        $version->forceFill(['is_signed' => true, 'locked_at' => now()])->saveQuietly();
    }
}
