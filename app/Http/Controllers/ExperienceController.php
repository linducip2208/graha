<?php

namespace App\Http\Controllers;

use App\Models\CompanyExperience;
use App\Models\ExperienceVersion;
use App\Services\AuditTrail;
use App\Services\ExperienceVersionService;
use App\Services\ThemeService;
use App\Support\Experience\ThemePresets;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Experience Studio (ADR-058): studio tampilan & white label per company.
 * Non-teknis: pilih preset, warna, font, nama — tanpa edit code.
 */
class ExperienceController extends Controller
{
    public function edit(Request $request, CurrentCompany $current, ThemeService $service)
    {
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        $companyId = $current->id();

        return view('experience.studio', [
            'resolved' => $service->resolve($companyId),
            'row' => CompanyExperience::find($companyId),
            'presets' => collect(ThemePresets::all())->map(fn ($p, $k) => ['key' => $k, 'label' => $p['label']]),
        ]);
    }

    public function update(Request $request, CurrentCompany $current, ThemeService $service)
    {
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        $data = $request->validate([
            'admin_theme' => ['nullable', 'in:'.implode(',', array_keys(ThemePresets::all()))],
            'frontend_theme' => ['nullable', 'in:corporate,construction,minimal,modern,professional'],
            'primary_color' => ['nullable', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'font_ui' => ['nullable', 'in:'.implode(',', ThemePresets::FONTS)],
            'font_heading' => ['nullable', 'in:'.implode(',', ThemePresets::FONTS)],
            'density' => ['nullable', 'in:compact,comfortable'],
            'button_style' => ['nullable', 'in:square,soft,rounded,pill'],
            'card_style' => ['nullable', 'in:minimal,bordered,elevated,soft'],
            'sidebar_style' => ['nullable', 'in:dark,light,brand'],
            'topbar_style' => ['nullable', 'in:light,dark,brand'],
            'system_name' => ['nullable', 'max:80'],
            'company_display_name' => ['nullable', 'max:120'],
            'footer_text' => ['nullable', 'max:200'],
            'support_email' => ['nullable', 'email', 'max:120'],
            'login_headline' => ['nullable', 'max:150'],
        ]);
        foreach (['primary_color', 'secondary_color', 'accent_color'] as $f) {
            if (isset($data[$f])) {
                $data[$f] = '#'.ltrim($service->sanitizeHex($data[$f]) ?? '', '#') ?: null;
                if ($data[$f] === '#') {
                    unset($data[$f]);
                }
            }
        }
        $row = CompanyExperience::updateOrCreate(['company_id' => $current->id()], [
            ...collect($data)->filter(fn ($v) => filled($v))->all(),
            'is_published' => true,
            'published_by' => $request->user()->id,
            'published_at' => now(),
        ]);
        ThemeService::flush($current->id());
        app(AuditTrail::class)->record($current->id(), $request->user()->id, 'experience.updated', $row);

        return back()->with('status', 'Tampilan & white label diterbitkan — berlaku untuk semua user company ini.');
    }

    /** Simpan konfigurasi aktif sebagai DRAFT versi baru (belum diterbitkan). */
    public function saveDraft(Request $request, CurrentCompany $current, ExperienceVersionService $service, ThemeService $theme)
    {
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        $active = CompanyExperience::find($current->id());
        $config = $active ? collect($active->only(['admin_theme', 'frontend_theme', 'primary_color', 'secondary_color', 'accent_color', 'font_ui', 'font_heading', 'density', 'button_style', 'card_style', 'sidebar_style', 'topbar_style', 'system_name', 'company_display_name', 'footer_text', 'support_email', 'login_headline']))->filter(fn ($v) => filled($v))->all() : ['admin_theme' => ThemeService::DEFAULT_PRESET];
        $version = $service->saveDraft($current->id(), $config, $request->user());

        return back()->with('status', "Draft v{$version->version} dibuat — preview lalu publish dari daftar versi.");
    }

    public function publishVersion(Request $request, ExperienceVersion $version, CurrentCompany $current, ExperienceVersionService $service)
    {
        abort_unless($version->company_id === $current->id(), 404);
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        $service->publish($version, $request->user());

        return back()->with('status', "Versi {$version->version} dipublikasikan; versi published sebelumnya diarsipkan.");
    }

    /** Rollback = publikasikan ulang versi arsip sebagai versi terbaru. */
    public function rollbackTo(Request $request, ExperienceVersion $version, CurrentCompany $current, ExperienceVersionService $service)
    {
        abort_unless($version->company_id === $current->id(), 404);
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        $new = $service->saveDraft($current->id(), $version->config, $request->user());
        $new->update(['logo_path' => $version->logo_path, 'favicon_path' => $version->favicon_path]);
        $service->publish($new->refresh(), $request->user());

        return back()->with('status', "Rollback ke konfigurasi v{$version->version} berhasil sebagai v{$new->version}.");
    }

    public function uploadAsset(Request $request, CurrentCompany $current, ExperienceVersionService $service)
    {
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        $data = $request->validate(['kind' => ['required', 'in:logo,favicon'], 'file' => ['required', 'file', 'max:2048']]);
        $path = $service->storeAsset($current->id(), $data['file'], $data['kind'], $request->user());
        CompanyExperience::updateOrCreate(['company_id' => $current->id()], [$data['kind'].'_path' => $path, 'is_published' => true]);
        ThemeService::flush($current->id());

        return back()->with('status', ucfirst($data['kind']).' tersimpan (storage privat).');
    }

    /** Serving asset branding: path harus terdaftar pada experience company tsb (anti traversal/enumeration). */
    public function serveAsset(int $companyId, string $file)
    {
        $row = CompanyExperience::where('company_id', $companyId)->first();
        abort_unless($row, 404);
        $relative = collect([$row->logo_path, $row->favicon_path])->filter()->first(fn ($p) => str_ends_with((string) $p, '/'.$file));
        abort_unless($relative && ! preg_match('#[\\\\/]{2}|\.\./#', $file), 404);
        abort_unless(Storage::disk('local')->exists($relative), 404);

        $mime = str_ends_with($file, '.svg') ? 'image/svg+xml' : (str_ends_with($file, '.png') ? 'image/png' : (str_ends_with($file, '.webp') ? 'image/webp' : 'image/jpeg'));

        return Storage::disk('local')->response($relative, null, ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=3600']);
    }
}
