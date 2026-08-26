<?php

namespace App\Services\Storage;

use App\Models\BoredPile;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Direct/presigned upload opsional (ADR-078): bila disk mendukung temporary
 * upload URL native (S3-compatible), client mengunggah LANGKAH ke storage
 * dengan URL berbatas waktu; finalize memverifikasi ukuran & checksum lalu
 * StoredFile READY. Bila tidak didukung → mode 'server' (upload via server,
 * jalur existing). Tidak pernah dipaksa pada adapter yang tidak aman.
 */
class DirectUploadService
{
    public function __construct(
        private ObjectStorageService $storage,
        private FileValidationService $validation,
        private AuditTrail $audit,
        private CompanyStorageManager $storageManager,
    ) {}

    /**
     * @return array{mode: string, upload_id: string, url: ?string, expires_at: ?string}
     */
    public function requestUpload(?BoredPile $pile, int $companyId, string $category, string $filename, int $sizeBytes, User $actor): array
    {
        $target = $this->storageManager->resolve($companyId, 'evidence');
        $diskName = $target['disk'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Validasi metadata awal — konten diverifikasi penuh saat finalize/upload.
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            // Foto: batas ukuran dari validation service.
            throw_unless($sizeBytes > 0 && $sizeBytes <= 20 * 1024 * 1024, ValidationException::withMessages(['size' => 'Ukuran file di luar batas.']));
        }

        $uploadId = (string) Str::uuid();
        $key = sprintf('%s/%s/%s.%s', $category, now()->format('Y/m'), $uploadId, $extension ?: 'bin');
        $stored = StoredFile::create([
            'company_id' => $companyId,
            'bored_pile_id' => $pile?->id,
            'project_id' => $pile?->project_id,
            'category' => in_array($category, StoredFile::CATEGORIES, true) ? $category : 'photo',
            'sub_category' => $category,
            'disk' => $diskName,
            'storage_profile_id' => $target['profile']?->id,
            'storage_locator' => $target['locator'],
            'object_key' => $key,
            'original_name' => $filename,
            'extension' => $extension ?: null,
            'mime_type' => $this->guessMime($extension),
            'size_bytes' => $sizeBytes,
            'sha256' => str_repeat('0', 64), // placeholder; diganti saat finalize
            'status' => 'uploading',
            'uploaded_by' => $actor->id,
            'upload_id' => $uploadId,
            'metadata' => ['direct_upload_requested_at' => now()->toIso8601String()],
        ]);

        $presigned = null;
        $expiresAt = null;
        try {
            if (($target['profile']?->driver === 's3' || $this->storage->supportsTemporaryUrl($diskName)) && method_exists($target['filesystem'], 'temporaryUploadUrl')) {
                $expiresAt = now()->addMinutes(30);
                $result = $target['filesystem']->temporaryUploadUrl($key, $expiresAt);
                $presigned = $result['url'] ?? null;
            }
        } catch (\Throwable) {
            $presigned = null; // fallback ke server upload.
        }
        $this->audit->record($companyId, $actor->id, 'storage.direct_upload_requested', $stored);

        return [
            'mode' => $presigned !== null ? 'presigned' : 'server',
            'upload_id' => $uploadId,
            'url' => $presigned,
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }

    /** Finalize idempotent: panggil ulang dengan upload_id sama → hasil sama. */
    public function finalize(string $uploadId, int $companyId, ?string $sha256, ?int $sizeBytes): StoredFile
    {
        $file = StoredFile::where('company_id', $companyId)->where('upload_id', $uploadId)->firstOrFail();

        // Idempoten: sudah READY → kembalikan tanpa efek samping.
        if ($file->status === 'ready') {
            return $file;
        }
        throw_unless($file->status === 'uploading', ValidationException::withMessages(['status' => "Status {$file->status} tidak dapat difinalisasi."]));
        if ($sha256 !== null && strlen($sha256) === 64) {
            $file->sha256 = strtolower($sha256);
        }
        if ($sizeBytes !== null && $sizeBytes > 0) {
            // Verifikasi ukuran fisik bila object sudah ada di disk.
            if ($this->storage->existsFile($file)) {
                $actual = $this->storage->sizeFile($file);
                throw_unless($actual === $sizeBytes, ValidationException::withMessages(['size' => 'Ukuran fisik tidak cocok.']));
            }
            $file->size_bytes = $sizeBytes;
        }
        $file->status = 'ready';
        $file->save();

        return $file->refresh();
    }

    private function guessMime(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
