<?php

namespace App\Support\Experience;

/**
 * Registry preset theme admin (ADR-058). Setiap preset = nilai token
 * lengkap (bukan hanya warna): font, radius, shadow, density, sidebar.
 * Semua whitelist — tidak ada input bebas di sini.
 */
class ThemePresets
{
    public const FONTS = ['Instrument Sans', 'Inter', 'Manrope', 'IBM Plex Sans', 'Source Sans 3', 'System UI'];

    public static function all(): array
    {
        return [
            'executive-navy' => [
                'label' => 'Executive Navy',
                'tokens' => [
                    '--brand-primary' => '#0f2a52', '--brand-primary-hover' => '#16386b', '--brand-accent' => '#38bdf8',
                    '--surface-sidebar' => '#0f1e3d', '--surface-topbar' => '#ffffff',
                    '--font-ui' => 'Instrument Sans', '--font-heading' => 'Instrument Sans',
                    '--radius-button' => '.6rem', '--radius-card' => '.9rem',
                    '--density-pad' => '.65rem', '--shadow-card' => '0 1px 2px rgba(15,23,42,.05),0 8px 24px -16px rgba(15,23,42,.18)',
                ],
            ],
            'corporate-teal' => [
                'label' => 'Corporate Teal',
                'tokens' => [
                    '--brand-primary' => '#0e7490', '--brand-primary-hover' => '#155e75', '--brand-accent' => '#22d3ee',
                    '--surface-sidebar' => '#083344', '--surface-topbar' => '#ffffff',
                    '--font-ui' => 'Inter', '--font-heading' => 'Inter',
                    '--radius-button' => '.55rem', '--radius-card' => '.8rem',
                    '--density-pad' => '.6rem', '--shadow-card' => '0 1px 3px rgba(8,51,68,.12)',
                ],
            ],
            'minimal-light' => [
                'label' => 'Minimal Light',
                'tokens' => [
                    '--brand-primary' => '#334155', '--brand-primary-hover' => '#1e293b', '--brand-accent' => '#0ea5e9',
                    '--surface-sidebar' => '#f8fafc', '--surface-topbar' => '#ffffff',
                    '--font-ui' => 'System UI', '--font-heading' => 'System UI',
                    '--radius-button' => '.4rem', '--radius-card' => '.55rem',
                    '--density-pad' => '.85rem', '--shadow-card' => '0 1px 2px rgba(15,23,42,.06)',
                ],
            ],
            'graphite-dark' => [
                'label' => 'Graphite Dark',
                'tokens' => [
                    '--brand-primary' => '#e11d48', '--brand-primary-hover' => '#be123c', '--brand-accent' => '#fb7185',
                    '--surface-sidebar' => '#111827', '--surface-topbar' => '#111827',
                    '--font-ui' => 'Manrope', '--font-heading' => 'Manrope',
                    '--radius-button' => '.5rem', '--radius-card' => '.7rem',
                    '--density-pad' => '.6rem', '--shadow-card' => '0 2px 10px rgba(0,0,0,.35)',
                ],
            ],
            'industrial-amber' => [
                'label' => 'Industrial Amber',
                'tokens' => [
                    '--brand-primary' => '#b45309', '--brand-primary-hover' => '#92400e', '--brand-accent' => '#f59e0b',
                    '--surface-sidebar' => '#1c1917', '--surface-topbar' => '#fafaf9',
                    '--font-ui' => 'IBM Plex Sans', '--font-heading' => 'IBM Plex Sans',
                    '--radius-button' => '.5rem', '--radius-card' => '.65rem',
                    '--density-pad' => '.6rem', '--shadow-card' => '0 1px 3px rgba(28,25,23,.15)',
                ],
            ],
            'modern-indigo' => [
                'label' => 'Modern Indigo',
                'tokens' => [
                    '--brand-primary' => '#4f46e5', '--brand-primary-hover' => '#4338ca', '--brand-accent' => '#818cf8',
                    '--surface-sidebar' => '#1e1b4b', '--surface-topbar' => '#ffffff',
                    '--font-ui' => 'Source Sans 3', '--font-heading' => 'Manrope',
                    '--radius-button' => '.7rem', '--radius-card' => '1rem',
                    '--density-pad' => '.7rem', '--shadow-card' => '0 4px 20px -10px rgba(79,70,229,.35)',
                ],
            ],
        ];
    }

    public static function get(string $key): array
    {
        return self::all()[$key] ?? self::all()['executive-navy'];
    }
}
