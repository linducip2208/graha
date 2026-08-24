<?php

namespace App\Support;

use App\Models\CompanyExperience;
use App\Models\User;

/**
 * App Launcher (ADR-076): kartu per WORKSPACE dari effective navigation
 * (Navigation::groups — sudah difilter permission + edition + nav composer
 * + terminology). Registry config/app-launcher.php HANYA metadata presentasi:
 * cover & deskripsi. Fallback: company custom cover -> registry -> gradient.
 */
class AppLauncher
{
    private const CAPABILITY_PREVIEW = 3;

    /** @return array<int, array{key:string,label:string,href:string,icon:string,cover:?string,description:string,accent:?string,capabilities:array<int,string>,more:int,items:int}> */
    public static function workspaces(User $user, int $companyId): array
    {
        $registry = (array) config('app-launcher.workspaces', []);
        $custom = self::customCovers($companyId);

        return Navigation::groups($user, $companyId)
            ->map(function (array $group) use ($registry, $custom) {
                $key = (string) ($group['key'] ?? str($group['label'])->slug());
                $items = collect($group['items']);
                if ($items->isEmpty()) {
                    return null;
                }
                $first = $items->first();
                $meta = $registry[$key] ?? [];
                $children = $items->flatMap(fn (array $item) => empty($item['children'])
                    ? [$item['label']]
                    : array_map(fn (array $child) => $child['label'], $item['children']));
                $preview = $children->take(self::CAPABILITY_PREVIEW)->values();

                return [
                    'key' => $key,
                    'label' => (string) $group['label'],
                    'href' => (string) $first['href'],
                    'icon' => (string) ($first['icon'] ?? 'dashboard'),
                    'cover' => $custom[$key] ?? ($meta['cover'] ?? null),
                    'description' => (string) ($meta['description'] ?? $items->count().' aplikasi dalam workspace ini.'),
                    'accent' => $meta['accent'] ?? null,
                    'capabilities' => $preview->all(),
                    'more' => max(0, $children->count() - $preview->count()),
                    'items' => $items->count(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** Cover custom company: map key -> path di disk local (privat). */
    public static function customCovers(int $companyId): array
    {
        $covers = CompanyExperience::find($companyId)?->launcher_covers ?? [];

        // Path tersimpan sebagai 'branding/{id}/launcher-covers/<file>' oleh
        // ExperienceVersionService (atau 'launcher-covers/<file>' pada data lama).
        return collect((array) $covers)
            ->filter(fn ($path) => is_string($path) && str_contains($path, 'launcher-covers/'))
            ->map(fn ($path) => '/branding/'.$companyId.'/'.basename($path))
            ->all();
    }

    /** Preferensi launcher company (style/covers_enabled/density). */
    public static function config(int $companyId): array
    {
        return array_merge(
            ['style' => 'visual', 'covers_enabled' => true, 'density' => 'comfortable'],
            (array) (CompanyExperience::find($companyId)?->launcher_config ?? [])
        );
    }
}
