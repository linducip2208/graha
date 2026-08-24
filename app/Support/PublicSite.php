<?php

namespace App\Support;

use App\Models\CompanyExperience;

/**
 * Public Site (UI V3): resolusi branding + konten homepage publik per company.
 * Fallback berlapis: public_site company → kolom experience existing → default app.
 * TANPA data palsu — section memakai screenshot & capability nyata.
 */
class PublicSite
{
    /** @return array{enabled:bool, system_name:string, logo_url:?string, footer_text:string, support_email:?string, hero_title:string, hero_subtitle:string, cta1_label:string, cta1_url:string, cta2_label:string, cta2_url:string, hero_image:?string, sections:array<string,bool>} */
    public static function resolve(): array
    {
        try {
            $row = CompanyExperience::query()->where('is_published', true)->first();
        } catch (\Throwable) {
            // Tabel belum tersedia (fresh install / test tanpa migrasi) — pakai default.
            $row = null;
        }
        $site = (array) ($row?->public_site ?? []);
        $sections = array_merge([
            'proof' => true, 'flow' => true, 'modules' => true, 'foundation' => true,
            'passport' => true, 'finance' => true, 'supply' => true, 'workshop' => true,
            'qhse' => true, 'documents' => true, 'security' => true, 'multicompany' => true,
            'implementation' => true,
        ], (array) ($site['sections'] ?? []));

        return [
            'enabled' => (bool) ($site['enabled'] ?? true),
            'system_name' => $row?->system_name ?: config('app.name', 'Graha Pondasi ERP'),
            'logo_url' => $row?->logo_path ? '/branding/'.($row?->company_id ?? 0).'/'.basename($row->logo_path) : null,
            'footer_text' => $site['footer_text'] ?? $row?->footer_text ?? 'ERP konstruksi pondasi — satu jejak data dari tender sampai handover.',
            'support_email' => $row?->support_email,
            'hero_title' => $site['hero_title'] ?? 'Kendalikan tender, proyek, bored pile, keuangan, dan mutu dalam satu sistem.',
            'hero_subtitle' => $site['hero_subtitle'] ?? 'ERP multi-company untuk kontraktor pondasi: tender → kontrak → proyek, field operations, procurement & inventory, jurnal berimbang, QMS/HSE, document control, dan audit trail hash-chain.',
            'cta1_label' => $site['cta1_label'] ?? 'Lihat Sistem',
            'cta1_url' => $site['cta1_url'] ?? '/login',
            'cta2_label' => $site['cta2_label'] ?? 'Pelajari Modul',
            'cta2_url' => $site['cta2_url'] ?? '/docs',
            'hero_image' => isset($site['hero_image']) && is_string($site['hero_image']) && $site['hero_image'] !== ''
                ? '/branding/'.($row?->company_id ?? 0).'/'.basename($site['hero_image'])
                : null,
            'sections' => $sections,
        ];
    }
}
