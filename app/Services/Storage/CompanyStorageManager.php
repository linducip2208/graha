<?php

namespace App\Services\Storage;

use App\Models\CompanyStorageProfile;
use App\Models\DocumentVersion;
use App\Models\StoredFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class CompanyStorageManager
{
    public function profile(int $companyId, string $usage): ?CompanyStorageProfile
    {
        $column = $usage === 'document' ? 'is_default_document' : 'is_default_evidence';

        return CompanyStorageProfile::query()->where('company_id', $companyId)
            ->where('is_active', true)->where($column, true)->first();
    }

    public function resolve(int $companyId, string $usage): array
    {
        if ($profile = $this->profile($companyId, $usage)) {
            return ['filesystem' => $this->build($profile), 'disk' => 'profile:'.$profile->id, 'profile' => $profile, 'locator' => $this->snapshot($profile)];
        }
        $configured = (string) config('objectstorage.'.($usage === 'document' ? 'document_disk' : 'evidence_disk'), 'local');
        $disk = array_key_exists($configured, (array) config('filesystems.disks')) ? $configured : 'local';

        return ['filesystem' => Storage::disk($disk), 'disk' => $disk, 'profile' => null, 'locator' => null];
    }

    public function build(CompanyStorageProfile $profile, ?array $locator = null): Filesystem
    {
        if ($profile->driver === 'local') {
            return Storage::build(['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true]);
        }

        return Storage::build($this->config($profile, $locator));
    }

    public function config(CompanyStorageProfile $profile, ?array $locator = null): array
    {
        return [
            'driver' => 's3', 'key' => $profile->access_key_encrypted, 'secret' => $profile->secret_key_encrypted,
            'region' => $locator['region'] ?? $profile->region ?? 'auto', 'bucket' => $locator['bucket'] ?? $profile->bucket,
            'endpoint' => $locator['endpoint'] ?? $profile->endpoint, 'url' => $locator['base_url'] ?? $profile->base_url,
            'use_path_style_endpoint' => (bool) ($locator['use_path_style_endpoint'] ?? $profile->use_path_style_endpoint),
            'throw' => true, 'report' => false,
        ];
    }

    public function forStoredFile(StoredFile $file): Filesystem
    {
        if ($file->storageProfile) {
            return $this->build($file->storageProfile, $file->storage_locator);
        }

        return Storage::disk($file->disk);
    }

    public function forDocumentVersion(DocumentVersion $version): Filesystem
    {
        if ($version->storageProfile) {
            return $this->build($version->storageProfile, $version->storage_locator);
        }

        return Storage::disk($version->disk);
    }

    public function snapshot(CompanyStorageProfile $profile): array
    {
        return array_filter(['driver' => $profile->driver, 'endpoint' => $profile->endpoint, 'region' => $profile->region,
            'bucket' => $profile->bucket, 'base_url' => $profile->base_url, 'use_path_style_endpoint' => $profile->use_path_style_endpoint], fn ($v) => $v !== null);
    }
}
