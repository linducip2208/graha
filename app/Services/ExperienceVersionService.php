<?php

namespace App\Services;

use App\Models\CompanyExperience;
use App\Models\ExperienceVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Draft / Publish / Rollback (ADR-059) + brand asset privat.
 * Publish = versi menjadi aktif: config di-mirror ke company_experiences
 * (sumber ThemeService) dan published sebelumnya di-archive. Rollback =
 * publish ulang versi lama sebagai versi baru. Asset disimpan di disk
 * privat; SVG disanitasi dari script/event handler.
 */
class ExperienceVersionService
{
    public function __construct(private AuditTrail $audit) {}

    public function saveDraft(int $companyId, array $config, User $actor): ExperienceVersion
    {
        return DB::transaction(function () use ($companyId, $config, $actor) {
            ExperienceVersion::where('company_id', $companyId)->where('status', 'draft')->update(['status' => 'archived']);
            $active = CompanyExperience::find($companyId);
            $version = ExperienceVersion::create([
                'company_id' => $companyId,
                'version' => (int) ExperienceVersion::where('company_id', $companyId)->max('version') + 1,
                'status' => 'draft',
                'config' => $config,
                'logo_path' => $active?->logo_path,
                'favicon_path' => $active?->favicon_path,
                'created_by' => $actor->id,
            ]);
            $this->audit->record($companyId, $actor->id, 'experience.draft_created', $version);

            return $version;
        }, 3);
    }

    public function publish(ExperienceVersion $version, User $actor): ExperienceVersion
    {
        return DB::transaction(function () use ($version, $actor) {
            $version = ExperienceVersion::lockForUpdate()->findOrFail($version->id);
            throw_if($version->status === 'published', ValidationException::withMessages(['status' => 'Versi ini sudah published.']));
            ExperienceVersion::where('company_id', $version->company_id)->where('status', 'published')->update(['status' => 'archived']);
            $version->update(['status' => 'published', 'published_by' => $actor->id, 'published_at' => now()]);

            $fields = ['admin_theme', 'frontend_theme', 'primary_color', 'secondary_color', 'accent_color', 'font_ui', 'font_heading', 'density', 'button_style', 'card_style', 'sidebar_style', 'topbar_style', 'system_name', 'company_display_name', 'footer_text', 'support_email', 'login_headline'];
            // Snapshot lengkap: kolom yang tak ada di config di-nol-kan agar nilai
            // versi sebelumnya tidak bocor ke hasil publish (rollback bersih).
            // Dua kolom NOT NULL diberi fallback aman.
            $mirror = collect($fields)->mapWithKeys(function ($f) use ($version) {
                $value = $version->config[$f] ?? null;

                return [$f => match ($f) {
                    'admin_theme' => $value ?? ThemeService::DEFAULT_PRESET,
                    'frontend_theme' => $value ?? 'corporate',
                    default => $value,
                }];
            })->all();
            CompanyExperience::updateOrCreate(['company_id' => $version->company_id], [
                ...$mirror,
                'logo_path' => $version->logo_path,
                'favicon_path' => $version->favicon_path,
                'is_published' => true,
                'published_by' => $actor->id,
                'published_at' => now(),
            ]);
            ThemeService::flush($version->company_id);
            $this->audit->record($version->company_id, $actor->id, 'experience.published', $version);

            return $version->refresh();
        }, 3);
    }

    /** Upload logo/favicon ke disk privat; SVG disanitasi; validasi MIME+size+isi gambar. */
    public function storeAsset(int $companyId, UploadedFile $file, string $kind, User $actor): string
    {
        $allowedMimes = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'image/svg' => 'svg', 'text/plain' => 'svg'];
        $ext = $allowedMimes[$file->getClientMimeType()] ?? null;
        if ($ext === null && str_ends_with(strtolower($file->getClientOriginalName()), '.svg')) {
            $ext = 'svg';
        }
        throw_unless($ext, ValidationException::withMessages(['file' => 'Format harus PNG/JPG/WebP/SVG.']));
        throw_if($file->getSize() > 2 * 1024 * 1024, ValidationException::withMessages([$kind => 'Maksimal 2 MB.']));

        $content = $file->getContent();
        if ($ext === 'svg') {
            $scrubbed = preg_replace(['#<script\b[^>]*>.*?</script>#is', '#\son\w+\s*=\s*(["\']).*?\1#is', '#<foreignObject\b[^>]*>.*?</foreignObject>#is'], '', (string) $content);
            if ($scrubbed === null || stripos((string) $scrubbed, '<script') !== false || stripos((string) $scrubbed, 'onload') !== false) {
                throw ValidationException::withMessages(['file' => 'SVG mengandung konten berbahaya.']);
            }
            $content = $scrubbed;
        } elseif (! getimagesizefromstring((string) $content)) {
            throw ValidationException::withMessages(['file' => 'Berkas gambar tidak valid.']);
        }

        $path = "branding/{$companyId}/{$kind}-".now()->format('YmdHis').'.'.$ext;
        Storage::disk('local')->put($path, $content);
        $subjectRow = CompanyExperience::firstOrNew(['company_id' => $companyId]);
        $this->audit->record($companyId, $actor->id, 'experience.asset_uploaded', $subjectRow);

        return $path;
    }
}
