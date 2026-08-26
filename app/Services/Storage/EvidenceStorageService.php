<?php

namespace App\Services\Storage;

use App\Models\BoredPile;
use App\Models\Project;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Evidence Storage (ADR-048): menyimpan foto/dokumen pile ke object storage
 * privat dengan struktur key UUID immutable, checksum SHA-256, dan varian
 * thumb/preview. Database hanya menyimpan metadata.
 */
class EvidenceStorageService
{
    public function __construct(
        private ObjectStorageService $storage,
        private FileValidationService $validator,
        private ImageProcessor $images,
        private AuditTrail $audit,
        private CompanyStorageManager $storageManager,
    ) {}

    /**
     * Simpan foto evidence terkategorisasi untuk satu bored pile.
     * Object key: companies/{company_uuid}/projects/{project_uuid}/bored-piles/{pile_public_uuid}/photos/{category}/{uuid}.{ext}
     */
    public function storePilePhoto(BoredPile $pile, string $category, UploadedFile $file, User $actor, array $meta = []): StoredFile
    {
        throw_unless(array_key_exists($category, StoredFile::PHOTO_CATEGORIES), ValidationException::withMessages(['category' => 'Kategori foto tidak dikenal.']));

        return DB::transaction(function () use ($pile, $category, $file, $actor, $meta) {
            $mime = $this->validator->validateImage($file);
            $contents = (string) file_get_contents($file->getRealPath());
            $sha = hash('sha256', $contents);
            $uuid = (string) Str::uuid();
            $extension = $this->validator->extensionFromName($file->getClientOriginalName()) ?: $this->extensionFromMime($mime);
            $key = sprintf('companies/%s/projects/%s/bored-piles/%s/photos/%s/%s.%s',
                $pile->project->company?->uuid ?? 'c'.$pile->project->company_id,
                $pile->project->uuid,
                $pile->public_uuid,
                $category,
                $uuid,
                $extension
            );
            $target = $this->storageManager->resolve($pile->project->company_id, 'evidence');
            $disk = $target['disk'];

            $original = StoredFile::create([
                'uuid' => $uuid,
                'company_id' => $pile->project->company_id,
                'project_id' => $pile->project_id,
                'bored_pile_id' => $pile->id,
                'category' => 'photo',
                'sub_category' => $category,
                'disk' => $disk,
                'storage_profile_id' => $target['profile']?->id,
                'storage_locator' => $target['locator'],
                'object_key' => $key,
                'original_name' => $file->getClientOriginalName(),
                'extension' => $extension,
                'mime_type' => $mime,
                'size_bytes' => strlen($contents),
                'sha256' => $sha,
                'status' => 'ready',
                'uploaded_by' => $actor->id,
                'captured_at' => $meta['captured_at'] ?? null,
                'latitude' => $meta['latitude'] ?? null,
                'longitude' => $meta['longitude'] ?? null,
                'caption' => $meta['caption'] ?? null,
                'metadata' => array_filter($meta, fn ($v) => ! in_array($v, ['captured_at', 'latitude', 'longitude', 'caption'], true)),
            ]);

            try {
                $target['filesystem']->put($key, $contents);
                $this->generateVariants($original);
            } catch (\Throwable $e) {
                $target['filesystem']->delete($key);
                $original->delete();

                throw $e;
            }

            $this->audit->record($pile->project->company_id, $actor->id, 'file_uploaded', $original);

            return $original;
        }, 3);
    }

    /** Varian turunan original: preview + thumb (WebP). Original tidak pernah dimodifikasi. */
    public function generateVariants(StoredFile $original): void
    {
        if ($original->variant_type !== null || $original->category !== 'photo') {
            return;
        }
        foreach ([['preview', ImageProcessor::PREVIEW_MAX], ['thumb', ImageProcessor::THUMB_MAX]] as [$type, $max]) {
            $variant = $this->images->makeVariant($this->storage->diskFor($original), $original->object_key, $max);
            $key = preg_replace('/(\.[a-z0-9]+)$/i', "-{$type}.".$variant['extension'], $original->object_key) ?? $original->object_key.".{$type}.{$variant['extension']}";
            $this->storage->diskFor($original)->put($key, $variant['contents']);
            StoredFile::create([
                'company_id' => $original->company_id,
                'project_id' => $original->project_id,
                'bored_pile_id' => $original->bored_pile_id,
                'document_id' => $original->document_id,
                'document_version_id' => $original->document_version_id,
                'category' => $original->category,
                'sub_category' => $original->sub_category,
                'disk' => $original->disk,
                'storage_profile_id' => $original->storage_profile_id,
                'storage_locator' => $original->storage_locator,
                'object_key' => $key,
                'original_name' => $original->original_name,
                'extension' => $variant['extension'],
                'mime_type' => $variant['mime'],
                'size_bytes' => strlen($variant['contents']),
                'sha256' => hash('sha256', $variant['contents']),
                'status' => 'ready',
                'uploaded_by' => $original->uploaded_by,
                'caption' => $original->caption,
                'original_file_id' => $original->id,
                'variant_type' => $type,
            ]);
        }
    }

    /** Simpan berkas PDF hasil generate server (as-built / dossier / handover). */
    public function storePdfBytes(BoredPile $pile, string $subCategory, string $filename, string $contents, User $actor, array $link = []): StoredFile
    {
        $sha = hash('sha256', $contents);
        $uuid = (string) Str::uuid();
        $key = sprintf('companies/%s/projects/%s/bored-piles/%s/%s/%s.pdf',
            $pile->project->company?->uuid ?? 'c'.$pile->project->company_id,
            $pile->project->uuid,
            $pile->public_uuid,
            $subCategory,
            $uuid
        );
        $target = $this->storageManager->resolve($pile->project->company_id, 'evidence');
        $disk = $target['disk'];

        return DB::transaction(function () use ($pile, $subCategory, $filename, $contents, $actor, $link, $sha, $uuid, $key, $disk, $target) {
            $stored = StoredFile::create([
                'uuid' => $uuid,
                'company_id' => $pile->project->company_id,
                'project_id' => $pile->project_id,
                'bored_pile_id' => $pile->id,
                'category' => $link['category'] ?? 'as_built',
                'sub_category' => $subCategory,
                'disk' => $disk,
                'storage_profile_id' => $target['profile']?->id,
                'storage_locator' => $target['locator'],
                'object_key' => $key,
                'original_name' => $filename,
                'extension' => 'pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($contents),
                'sha256' => $sha,
                'status' => 'ready',
                'uploaded_by' => $actor->id,
                'document_id' => $link['document_id'] ?? null,
                'document_version_id' => $link['document_version_id'] ?? null,
            ]);
            try {
                $target['filesystem']->put($key, $contents);
            } catch (\Throwable $e) {
                $stored->delete();

                throw $e;
            }
            $this->audit->record($pile->project->company_id, $actor->id, $link['audit_event'] ?? 'asbuilt_generated', $stored);

            return $stored;
        }, 3);
    }

    private function extensionFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }

    /** Arsip level project (mis. handover package ZIP) — tanpa relasi pile tunggal. */
    public function storeProjectArchive(Project $project, string $subCategory, string $filename, string $contents, User $actor, array $link = []): StoredFile
    {
        $sha = hash('sha256', $contents);
        $uuid = (string) Str::uuid();
        $key = sprintf('companies/%s/projects/%s/%s/%s.zip', $project->company?->uuid ?? 'c'.$project->company_id, $project->uuid, $subCategory, $uuid);
        $target = $this->storageManager->resolve($project->company_id, 'evidence');
        $disk = $target['disk'];

        return DB::transaction(function () use ($project, $subCategory, $filename, $contents, $actor, $link, $sha, $uuid, $key, $disk, $target) {
            $stored = StoredFile::create([
                'uuid' => $uuid,
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'category' => $link['category'] ?? 'handover',
                'sub_category' => $subCategory,
                'disk' => $disk,
                'storage_profile_id' => $target['profile']?->id,
                'storage_locator' => $target['locator'],
                'object_key' => $key,
                'original_name' => $filename,
                'extension' => 'zip',
                'mime_type' => 'application/zip',
                'size_bytes' => strlen($contents),
                'sha256' => $sha,
                'status' => 'ready',
                'uploaded_by' => $actor->id,
                'document_id' => $link['document_id'] ?? null,
                'document_version_id' => $link['document_version_id'] ?? null,
            ]);
            try {
                $target['filesystem']->put($key, $contents);
            } catch (\Throwable $e) {
                $stored->delete();

                throw $e;
            }
            $this->audit->record($project->company_id, $actor->id, $link['audit_event'] ?? 'handover_package_generated', $stored);

            return $stored;
        }, 3);
    }
}
