<?php

namespace App\Support;

use App\Models\CompanyExperience;
use Illuminate\Support\Collection;

/**
 * Edition Builder (ADR-065): edition = paket konfigurasi modul. Menonaktifkan
 * modul HANYA menyembunyikan navigasi/UI — data historis tetap utuh, backend
 * (route+permission) tetap source of truth.
 */
class Edition
{
    public static function all(): array
    {
        return config('editions', []);
    }

    public static function current(int $companyId): ?string
    {
        return CompanyExperience::find($companyId)?->edition;
    }

    /** Modul terlihat untuk edition company: null = semua. */
    public static function visibleModules(int $companyId): ?Collection
    {
        $key = self::current($companyId);
        if (! $key || empty(self::all()[$key]['modules'])) {
            return null;
        }

        return collect(self::all()[$key]['modules']);
    }

    public static function label(string $key): string
    {
        return self::all()[$key]['label'] ?? $key;
    }
}
