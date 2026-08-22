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
            ->filter(fn (array $group) => self::hasPermission($group, $user, $companyId))
            ->map(function (array $group) use ($user, $companyId, $visibleModules) {
                $items = self::filterItems(collect($group['items']), $user, $companyId, $visibleModules);

                return [
                    'key' => $group['key'] ?? str($group['label'])->slug(),
                    'label' => $group['label'],
                    'items' => $items,
                    'expanded' => $items->contains(fn (array $item) => $item['active'] || $item['expanded']),
                ];
            })
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();
    }

    private static function filterItems(Collection $items, User $user, ?int $companyId, Collection $visibleModules): Collection
    {
        return $items->map(function (array $item) use ($user, $companyId, $visibleModules) {
            if (! self::hasPermission($item, $user, $companyId)) {
                return null;
            }
            if (! self::isVisibleForModule((string) $item['href'], $visibleModules)) {
                return null;
            }

            $children = self::filterItems(collect($item['children'] ?? []), $user, $companyId, $visibleModules);
            $active = self::isActive($item);

            if (! empty($item['children']) && $children->isEmpty() && ! $active) {
                return null;
            }

            return array_merge($item, [
                'children' => $children,
                'active' => $active,
                'expanded' => $active || $children->contains(fn (array $child) => $child['active'] || $child['expanded']),
            ]);
        })->filter()->values();
    }

    private static function hasPermission(array $entry, User $user, ?int $companyId): bool
    {
        $permissions = array_values(array_filter((array) ($entry['permission_any'] ?? [])));

        if (! empty($entry['permission'])) {
            $permissions[] = $entry['permission'];
        }

        return $permissions === []
            || collect($permissions)->contains(fn (string $permission) => $user->hasPermission($permission, (int) $companyId));
    }

    private static function isActive(array $item): bool
    {
        $target = '/'.ltrim((string) parse_url($item['href'], PHP_URL_PATH), '/');
        $current = '/'.ltrim(request()->path(), '/');

        if (($item['exact'] ?? false) === true) {
            return $current === $target;
        }

        return $current === $target || ($target !== '/' && str_starts_with($current, rtrim($target, '/').'/'));
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
