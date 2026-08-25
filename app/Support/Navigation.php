<?php

namespace App\Support;

use App\Models\CompanyExperience;
use App\Models\User;
use Illuminate\Support\Collection;

class Navigation
{
    public static function groups(User $user, ?int $companyId): Collection
    {
        $visibleModules = Edition::visibleModules((int) $companyId)
            ?? collect(config('modules.visible', []));
        // Navigation Composer (ADR-061): override HANYA presentasi — permission
        // tetap difilter di bawah, direct URL tetap berlaku normal.
        $cfg = $companyId ? (array) (CompanyExperience::find($companyId)?->nav_config ?? []) : [];
        $hidden = collect($cfg['hidden'] ?? []);
        $labels = collect($cfg['labels'] ?? []);
        $order = collect($cfg['order'] ?? []);

        return collect(config('modules.nav', []))
            ->map(fn (array $group) => [
                'key' => $group['key'] ?? null,
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
            // Module visibility (edition) berlaku untuk SEMUA item, termasuk
            // parent ber-children: tanpa ini edition tidak bisa menyembunyikan
            // workspace utuh (ADR-065).
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
            preg_match('#^/admin/(inventory|procurement|operations|manufacturing|calibrations)#', $href) === 1 => 'manufacturing',
            preg_match('#^/admin/(finance|billing|cash-bank|project-costing|fixed-assets|procurement-accounting)#', $href) === 1,
            str_starts_with($href, '/admin/reports/finance') => 'accounting',
            default => 'other',
        };

        return $module === 'other' || $visibleModules->isEmpty() || $visibleModules->contains($module);
    }

    // ===== Adaptive Workspace Navigation (ADR-077) =====
    // Resolver aktif reusable: mengecek group → item → children secara
    // recursive. Sidebar display BUKAN authorization — direct route tetap
    // divalidasi middleware permission backend.

    /** Normalisasi path/href: buang query + fragment, rapikan trailing slash. */
    public static function normalizePath(string $path): string
    {
        $path = trim(strtok($path, '#') ?: '', '?');
        $path = strtok($path, '?') ?: '/';
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    /** Path cocok dengan href? Exact atau prefix di batas segmen (/a/b vs /a/b/c ✓, /a/bc ✗). */
    public static function isPathActive(string $href, string $path): bool
    {
        if ($href === '' || $href === '/' || str_contains($href, '#')) {
            return false; // anchor in-page bukan halaman — tidak pernah auto-active.
        }
        $path = self::normalizePath($path);
        $href = self::normalizePath($href);

        return $path === $href || str_starts_with($path.'/', $href.'/');
    }

    /** Item aktif bila href-nya sendiri ATAU salah satu children-nya cocok. */
    public static function isItemActive(array $item, string $path): bool
    {
        if (self::isPathActive((string) ($item['href'] ?? ''), $path)) {
            return true;
        }
        foreach (self::nodes($item['children'] ?? []) as $child) {
            if (self::isItemActive($child, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Item-item dalam group yang aktif untuk path — hanya yang paling spesifik
     * (href terpanjang) agar /admin/inventory tidak ikut menyala saat user di
     * /admin/inventory/lots.
     *
     * @return Collection<int, array{item: array, depth: int}>
     */
    public static function activeItems(array $group, string $path): Collection
    {
        $matched = collect();
        foreach (self::nodes($group['items'] ?? []) as $item) {
            foreach (array_merge([$item], self::nodes($item['children'] ?? [])->all()) as $node => $candidate) {
                $candidate = is_array($candidate) ? $candidate : ['href' => (string) $candidate];
                if (self::isItemActive($candidate, $path)) {
                    $matched->push(['item' => $candidate, 'depth' => $node === 0 ? 0 : 1]);
                }
            }
        }
        $deepest = $matched->max(fn (array $m) => mb_strlen(self::normalizePath((string) $m['item']['href'])));

        return $matched->filter(fn (array $m) => mb_strlen(self::normalizePath((string) $m['item']['href'])) === $deepest)->values();
    }

    /** Group aktif bila SALAH SATU descendant-nya cocok dengan path. */
    public static function isGroupActive(array $group, string $path): bool
    {
        foreach (self::nodes($group['items'] ?? []) as $item) {
            if (self::isItemActive($item, $path)) {
                return true;
            }
        }

        return false;
    }

    /** Normalisasi daftar node (array | Collection) tanpa (array)-cast yang merusak Collection. */
    private static function nodes(mixed $list): Collection
    {
        return collect(Collection::wrap($list)->filter(fn ($node) => is_array($node))->values());
    }

    /** Key workspace yang aktif untuk path saat ini (null = tidak ada). */
    public static function activeGroupKey(Collection $groups, string $path): ?string
    {
        foreach ($groups as $group) {
            if (($group['key'] ?? null) && self::isGroupActive($group, $path)) {
                return (string) $group['key'];
            }
        }

        return null;
    }
}
