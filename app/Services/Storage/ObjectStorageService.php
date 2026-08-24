<?php

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Abstraksi penyimpanan objek generik (ADR-048).
 *
 * Semua akses file fisik melewati service ini — TIDAK ada dependency ke
 * provider tertentu (AWS/R2/MinIO). Disk dipilih dari config/filesystems.php
 * sehingga berpindah provider hanya mengubah .env.
 */
class ObjectStorageService
{
    public function disk(?string $name = null): Filesystem
    {
        $name ??= (string) config('filesystems.default', 'local');
        throw_unless(array_key_exists($name, (array) config('filesystems.disks', [])), RuntimeException::class, "Disk storage '{$name}' tidak dikenal.");

        return Storage::disk($name);
    }

    public function exists(string $key, ?string $disk = null): bool
    {
        return $this->disk($disk)->exists($key);
    }

    public function put(string $key, string $contents, ?string $disk = null): void
    {
        $this->disk($disk)->put($key, $contents);
    }

    public function get(string $key, ?string $disk = null): string
    {
        return (string) $this->disk($disk)->get($key);
    }

    public function delete(string $key, ?string $disk = null): void
    {
        $this->disk($disk)->delete($key);
    }

    public function size(string $key, ?string $disk = null): int
    {
        return (int) $this->disk($disk)->size($key);
    }

    /**
     * True bila disk mendukung temporary URL native (semua driver S3-compatible).
     * Disk lokal selalu streaming terotorisasi — tidak pernah expose path publik.
     */
    public function supportsTemporaryUrl(?string $disk = null): bool
    {
        $config = (array) config('filesystems.disks.'.($disk ?? config('filesystems.default')), []);

        return ($config['driver'] ?? null) === 's3';
    }

    /**
     * Temporary URL privat berbatas waktu. Mengembalikan null bila driver tidak
     * mendukung — pemanggil wajib fallback ke streaming terotorisasi.
     */
    public function temporaryUrl(string $key, ?string $disk = null, ?\DateTimeInterface $expiresAt = null, array $options = []): ?string
    {
        if (! $this->supportsTemporaryUrl($disk)) {
            return null;
        }
        try {
            return $this->disk($disk)->temporaryUrl(
                $key,
                $expiresAt ?? now()->addMinutes((int) config('objectstorage.temporary_url_minutes', 15)),
                $options
            );
        } catch (\Throwable) {
            // Provider error/network failure → fallback streaming oleh pemanggil.
            return null;
        }
    }
}
