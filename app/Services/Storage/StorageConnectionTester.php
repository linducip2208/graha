<?php

namespace App\Services\Storage;

use App\Models\CompanyStorageProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StorageConnectionTester
{
    public function __construct(private CompanyStorageManager $manager) {}

    public function test(CompanyStorageProfile $profile): array
    {
        $checks = ['bucket' => 'FAIL', 'write' => 'FAIL', 'read' => 'FAIL', 'delete' => 'FAIL', 'temporary_url' => 'UNSUPPORTED'];
        $key = '.healthcheck/'.($profile->company?->uuid ?? 'company-'.$profile->company_id).'/'.Str::uuid().'.txt';
        $payload = 'graha-storage-health-'.Str::random(24);
        $disk = null;
        try {
            $disk = $this->manager->build($profile);
            $disk->files('.healthcheck');
            $checks['bucket'] = 'PASS';
            $disk->put($key, $payload);
            $checks['write'] = 'PASS';
            $read = (string) $disk->get($key);
            $checks['read'] = hash_equals($payload, $read) ? 'PASS' : 'FAIL';
            if ($profile->driver === 's3') {
                try {
                    $checks['temporary_url'] = filled($disk->temporaryUrl($key, now()->addMinutes(5))) ? 'PASS' : 'FALLBACK';
                } catch (\Throwable) {
                    $checks['temporary_url'] = 'FALLBACK';
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Storage profile connection test failed', ['company_id' => $profile->company_id, 'profile_id' => $profile->id, 'exception' => $e::class]);
        } finally {
            if ($disk) {
                try {
                    $disk->delete($key);
                    $checks['delete'] = $disk->exists($key) ? 'FAIL' : 'PASS';
                } catch (\Throwable) {
                }
            }
        }
        $required = collect($checks)->only(['bucket', 'write', 'read', 'delete']);
        $status = $required->every(fn ($v) => $v === 'PASS') ? 'CONNECTED' : ($required->contains('PASS') ? 'PARTIAL' : 'FAILED');

        return ['status' => $status, 'checks' => $checks, 'message' => $this->message($status, $checks)];
    }

    private function message(string $status, array $checks): string
    {
        if ($status === 'CONNECTED' && $checks['temporary_url'] === 'FALLBACK') {
            return 'Terhubung. Temporary URL tidak didukung — aplikasi menggunakan secure streaming.';
        }

        return match ($status) {
            'CONNECTED' => 'Bucket dapat diakses; write, read, delete, dan cleanup berhasil.',
            'PARTIAL' => 'Koneksi sebagian berhasil. Periksa izin write/delete bucket.',
            default => 'Endpoint tidak dapat dijangkau, bucket tidak ditemukan, atau credential ditolak.',
        };
    }
}
