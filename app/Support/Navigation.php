<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class Navigation
{
    public static function groups(User $user, ?int $companyId): Collection
    {
        $visibleModules = collect(config('modules.visible', []));

        return collect(config('modules.nav', []))
            ->map(fn (array $group) => [
                'label' => $group['label'],
                'items' => self::filterItems(collect($group['items']), $user, $companyId, $visibleModules),
            ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();
    }

    private static function filterItems(Collection $items, User $user, ?int $companyId, Collection $visibleModules): Collection
    {
        return $items->map(function (array $item) use ($user, $companyId, $visibleModules) {
            if (! empty($item['children'])) {
                return $item;
            }
            if (! empty($item['permission']) && ! $user->hasPermission($item['permission'], (int) $companyId)) {
                return null;
            }
            if (! self::isVisibleForModule((string) $item['href'], $visibleModules)) {
                return null;
            }

            return $item;
        })->filter()->values();
    }

    private static function isVisibleForModule(string $href, Collection $visibleModules): bool
    {
        if ($href === '/dashboard') {
            return true;
        }
        $module = match (true) {
            preg_match('#^/admin/(inventory|procurement|operations|manufacturing)#', $href) === 1 => 'manufacturing',
            preg_match('#^/admin/(finance|billing|cash-bank|project-costing|fixed-assets|procurement-accounting)#', $href) === 1,
            str_starts_with($href, '/admin/reports/finance') => 'accounting',
            default => 'other',
        };

        return $module === 'other' || $visibleModules->isEmpty() || $visibleModules->contains($module);
    }
}
