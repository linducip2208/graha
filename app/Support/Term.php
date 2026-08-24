<?php

namespace App\Support;

use App\Models\CompanyExperience;

/**
 * Terminology Engine (ADR-062): kamus istilah per company, diterapkan
 * server-side (bukan DOM-hack). Tanpa static cache agar aman multi-test;
 * bila perlu, cache per-company via Cache facade di tempat pemanggil panas.
 */
class Term
{
    public static function load(int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }

        return (array) (CompanyExperience::find($companyId)?->terminology ?? []);
    }

    public static function t(int $companyId, string $text): string
    {
        $map = self::load($companyId);

        return $map[$text] ?? $map[strtolower($text)] ?? $text;
    }

    /** Dipanggil setelah perubahan kamus (Experience Studio save). */
    public static function flush(): void
    {
        // Tanpa cache persisten — metode disediakan untuk kontrak pemanggil.
    }
}
