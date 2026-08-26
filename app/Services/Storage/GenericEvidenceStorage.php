<?php

namespace App\Services\Storage;

use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Evidence non-pile (tool/cage/casing): struktur key UUID perusahaan tetap
 * immutable, metadata tetap lewat StoredFile.
 */
class GenericEvidenceStorage
{
    public function __construct(
        private ObjectStorageService $storage,
        private FileValidationService $validator,
        private CompanyStorageManager $storageManager,
    ) {}

    public function store(int $companyId, string $type, Model $subject, UploadedFile $file, User $actor): StoredFile
    {
        return DB::transaction(function () use ($companyId, $type, $subject, $file, $actor) {
            $mime = $this->validator->validateImage($file);
            $contents = (string) file_get_contents($file->getRealPath());
            $uuid = (string) Str::uuid();
            $extension = $this->validator->extensionFromName($file->getClientOriginalName()) ?: 'jpg';
            $companyUuid = DB::table('companies')->where('id', $companyId)->value('uuid') ?? 'c'.$companyId;
            $key = sprintf('companies/%s/evidence/%s/%s/%s.%s', $companyUuid, $type, $subject->getKey(), $uuid, $extension);
            $target = $this->storageManager->resolve($companyId, 'evidence');
            $disk = $target['disk'];

            $stored = StoredFile::create([
                'uuid' => $uuid,
                'company_id' => $companyId,
                'category' => 'photo',
                'sub_category' => $type,
                'disk' => $disk,
                'storage_profile_id' => $target['profile']?->id,
                'storage_locator' => $target['locator'],
                'object_key' => $key,
                'original_name' => $file->getClientOriginalName(),
                'extension' => $extension,
                'mime_type' => $mime,
                'size_bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
                'status' => 'ready',
                'uploaded_by' => $actor->id,
                'metadata' => ['subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey()],
            ]);
            try {
                $target['filesystem']->put($key, $contents);
                app(EvidenceStorageService::class)->generateVariants($stored);
            } catch (\Throwable $e) {
                $target['filesystem']->delete($key);
                $stored->delete();

                throw $e;
            }

            return $stored;
        }, 3);
    }
}
