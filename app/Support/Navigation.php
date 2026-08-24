<?php

namespace App\Support;

use App\Models\CompanyExperience;
use App\Models\User;
use Illuminate\Support\Collection;

class Navigation
{
    public static function groups(User $user, ?int $companyId): Collection
    {
        $visibleModules = collect(config('modules.visible', []));
        // Navigation Composer (ADR-061): override HANYA presentasi — permission
        // tetap difilter di bawah, direct URL tetap berlaku normal.
        $cfg = $companyId ? (array) (CompanyExperience::find($companyId)?->nav_config ?? []) : [];
        $hidden = collect($cfg['hidden'] ?? []);
        $labels = collect($cfg['labels'] ?? []);
        $order = collect($cfg['order'] ?? []);

        return collect(config('modules.nav', []))
            ->map(fn (array $group) => [
                'label' => $group['label'],
                'items' => self::filterItems(collect($group['items']), $user, $companyId, $visibleModules),
            ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->filter(fn (array $group, int $idx) => ! $hidden->contains($idx))
            ->map(function (array $group, int $idx) use ($labels, $companyId) {
                if ($labels->has($idx)) {
                    $group['label'] = $labels[$idx];
                }
                if ($companyId !== null) {
                    $group['label'] = Term::t($companyId, $group['label']);
                }

                return $group;
            })
            ->sortBy(fn (array $group, int $idx) => $order->search($idx) === false ? 99 + $idx : $order->search($idx))
            ->values();
    }

    private static function filterItems(Collection $items, User $user, ?int $companyId, Collection $visibleModules): Collection
    {
        return $items->map(function (array $item) use ($user, $companyId, $visibleModules) {
            if (! empty($item['permission']) && ! $user->hasPermission($item['permission'], (int) $companyId)) {
                return null;
            }
            if (! empty($item['children'])) {
                return $item;
            }
            if (! self::isVisibleForModule((string) $item['href'], $visibleModules)) {
                return null;
            }

            return $item;
        })->filter()->values()->map(function (array $item) use ($companyId) {
            if ($companyId !== null && ! empty($item['label'])) {
                $item['label'] = Term::t($companyId, $item['label']);
            }

            return $item;
        });
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
