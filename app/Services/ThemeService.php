<?php

namespace App\Services;

use App\Models\CompanyExperience;
use App\Models\ExperienceVersion;
use App\Support\Experience\ThemePresets;
use Illuminate\Support\Facades\Cache;

/**
 * Theme resolver (ADR-058): system defaults → preset → company override.
 * Output: daftar token CSS final untuk layout. Cache per company, invalid
 * saat publish. Semua nilai dari whitelist/validasi — tidak ada raw input.
 */
class ThemeService
{
    public const DEFAULT_PRESET = 'executive-navy';

    public function resolve(int $companyId): array
    {
        return Cache::remember("experience:{$companyId}", now()->addHours(6), function () use ($companyId) {
            return $this->build($companyId, CompanyExperience::find($companyId));
        });
    }

    /** Pratinjau versi draft/archived/published tanpa menyentuh cache aktif (ADR-060). */
    public function preview(int $companyId, ExperienceVersion $version): array
    {
        return $this->build($companyId, CompanyExperience::find($companyId), $version);
    }

    private function build(int $companyId, ?CompanyExperience $row, ?ExperienceVersion $version = null): array
    {
        $presetKey = $version?->config['admin_theme'] ?? $row?->admin_theme ?? self::DEFAULT_PRESET;
        $source = fn (string $f) => $version !== null ? ($version->config[$f] ?? null) : $row?->{$f};
        $tokens = ThemePresets::get($presetKey)['tokens'];
        $config = ['preset' => $presetKey, 'frontend_theme' => $source('frontend_theme') ?? 'corporate'];

        foreach (['primary_color' => '--brand-primary', 'secondary_color' => '--brand-secondary', 'accent_color' => '--brand-accent'] as $field => $token) {
            if ($value = $source($field)) {
                $tokens[$token] = $this->sanitizeHex($value) ? '#'.$this->sanitizeHex($value) : $tokens[$token];
            }
        }
        if (($fu = $source('font_ui')) && in_array($fu, ThemePresets::FONTS, true)) {
            $tokens['--font-ui'] = $fu;
        }
        if (($fh = $source('font_heading')) && in_array($fh, ThemePresets::FONTS, true)) {
            $tokens['--font-heading'] = $fh;
        }
        foreach (['density', 'button_style', 'card_style', 'sidebar_style', 'topbar_style'] as $f) {
            if (filled($source($f))) {
                $config[$f] = $source($f);
            }
        }
        foreach (['system_name', 'company_display_name', 'footer_text', 'support_email', 'login_headline'] as $f) {
            if (filled($source($f))) {
                $config[$f] = $source($f);
            }
        }
        foreach (['logo_path', 'favicon_path'] as $pf) {
            if (filled($p = $version ? $version->{$pf} : $row?->{$pf})) {
                $config[str_replace('_path', '_url', $pf)] = '/branding/'.$companyId.'/'.basename($p);
            }
        }

        return ['tokens' => $tokens, 'config' => $config];
    }

    public static function flush(int $companyId): void
    {
        Cache::forget("experience:{$companyId}");
    }

    /** Terima "#RRGGBB" atau "RRGGBB"; kembalikan RRGGBB uppercase atau null. */
    public function sanitizeHex(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        $hex = ltrim(trim($raw), '#');

        return preg_match('/^[0-9a-fA-F]{6}$/', $hex) ? strtoupper($hex) : null;
    }
}
