<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Invalidate cache permission saat role/membership/permission berubah.
 * WAJIB dipanggil setiap mutasi authorization agar tidak ada stale access.
 */
class PermissionInvalidator
{
    public static function forUser(int $userId): void
    {
        foreach (Company::pluck('id') as $companyId) {
            cache()->forget("perm:{$userId}:{$companyId}");
        }
    }

    public static function forCompany(int $companyId): void
    {
        $userIds = DB::table('company_user')
            ->where('company_id', $companyId)
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            cache()->forget("perm:{$userId}:{$companyId}");
        }
    }

    /** Global permission change — hapus semua key perm:* yang dikenal. */
    public static function all(): void
    {
        foreach (User::pluck('id') as $userId) {
            foreach (Company::pluck('id') as $companyId) {
                cache()->forget("perm:{$userId}:{$companyId}");
            }
        }
    }
}
