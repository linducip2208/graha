<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\Storage\CompanyStorageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentVersionService
{
    public function __construct(private AuditTrail $audit, private CompanyStorageManager $storageManager) {}

    public function add(Document $document, UploadedFile $file, User $actor, string $reason): DocumentVersion
    {
        if (! in_array($file->getMimeType(), ['application/pdf', 'image/jpeg', 'image/png'], true) || $file->getSize() > 20 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'File harus PDF/JPG/PNG dan maksimal 20 MB.']);
        }

        return $this->addFromContents(
            $document,
            (string) file_get_contents($file->getRealPath()),
            $file->getClientOriginalName(),
            $file->getMimeType(),
            $actor,
            $reason
        );
    }

    /**
     * Tambah versi dokumen dari konten memori (hasil generate server seperti
     * as-built/dossier). Disk dari config objectstorage.document_disk —
     * tetap 'local' secara default agar perilaku existing tidak berubah.
     */
    public function addFromContents(Document $document, string $contents, string $originalName, string $mime, User $actor, string $reason, ?string $disk = null, array $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png']): DocumentVersion
    {
        if (! in_array($mime, $allowedMimes, true) || strlen($contents) > 20 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'File tidak sesuai whitelist atau melebihi 20 MB.']);
        }
        $target = $disk === null ? $this->storageManager->resolve($document->company_id, 'document') : null;
        $disk ??= $target['disk'];
        $filesystem = $target['filesystem'] ?? \Storage::disk($disk);

        return DB::transaction(function () use ($document, $contents, $originalName, $mime, $actor, $reason, $disk, $filesystem, $target) {
            $document = Document::lockForUpdate()->findOrFail($document->id);
            $next = ((int) $document->versions()->max('version')) + 1;
            $uuid = (string) Str::uuid();
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'pdf';
            $path = "companies/{$document->company->uuid}/documents/{$document->id}/{$uuid}.{$extension}";
            try {
                $filesystem->put($path, $contents);
                $version = $document->versions()->create([
                    'version' => $next, 'revision' => (string) ($next - 1), 'disk' => $disk, 'path' => $path,
                    'storage_profile_id' => ($target['profile'] ?? null)?->id, 'storage_locator' => $target['locator'] ?? null,
                    'sha256' => hash('sha256', $contents), 'size_bytes' => strlen($contents),
                    'mime_type' => $mime, 'change_reason' => $reason, 'created_by' => $actor->id,
                ]);
                $document->update(['workflow_status' => 'draft', 'signature_status' => 'unsigned']);
                $this->audit->record($document->company_id, $actor->id, 'document.version_created', $version);

                return $version;
            } catch (\Throwable $exception) {
                $filesystem->delete($path);

                throw $exception;
            }
        }, 3);
    }

    public function lockSigned(DocumentVersion $version): void
    {
        $version->forceFill(['is_signed' => true, 'locked_at' => now()])->saveQuietly();
    }
}
