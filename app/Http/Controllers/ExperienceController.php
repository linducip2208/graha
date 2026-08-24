<?php

namespace App\Http\Controllers;

use App\Models\CompanyExperience;
use App\Models\ExperienceVersion;
use App\Services\ExperienceVersionService;
use App\Services\ThemeService;
use App\Support\Experience\ThemePresets;
use App\Support\Navigation;
use App\Support\Tenancy\CurrentCompany;
use App\Support\Term;
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
        $experienceRow = CompanyExperience::find($companyId);

        return view('experience.studio', [
            'resolved' => $service->resolve($companyId),
            'row' => $experienceRow,
            'presets' => collect(ThemePresets::all())->map(fn ($p, $k) => ['key' => $k, 'label' => $p['label']]),
            'navGroupsCfg' => config('modules.nav'),
            'navConfig' => (array) ($experienceRow?->nav_config ?? []),
            'terminologyMap' => (array) ($experienceRow?->terminology ?? []),
            'launcherConfig' => array_merge(['style' => 'visual', 'covers_enabled' => true, 'density' => 'comfortable'], (array) ($experienceRow?->launcher_config ?? [])),
            'launcherCovers' => (array) ($experienceRow?->launcher_covers ?? []),
            // Cover manager hanya untuk workspace yang EFFECTIVE bagi user ini
            // (permission + edition + navigation composer) — bukan seluruh config.
            'launcherWorkspaceKeys' => Navigation::groups($request->user(), $companyId)
                ->map(fn ($g) => ['key' => (string) ($g['key'] ?? str($g['label'])->slug()), 'label' => (string) preg_replace('/^[^\p{L}\d]+/u', '', $g['label'])])
                ->values(),
            'launcherRegistry' => (array) config('app-launcher.workspaces', []),
        ]);
    }

    /** Simpan preferensi App Launcher company (style/covers/density). */
    public function saveLauncherConfig(Request $request, CurrentCompany $current)
    {
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        $data = $request->validate([
            'style' => ['required', 'in:visual,compact,list'],
            'covers_enabled' => ['required', 'boolean'],
            'density' => ['required', 'in:comfortable,compact'],
        ]);
        CompanyExperience::updateOrCreate(['company_id' => $current->id()], ['launcher_config' => $data]);
        ThemeService::flush($current->id());

        return back()->with('status', 'Preferensi App Launcher disimpan.');
    }

    /** Upload custom cover satu workspace (JPEG/PNG/WebP, dioptimalkan ke WebP 16:9). */
    public function uploadLauncherCover(Request $request, CurrentCompany $current, ExperienceVersionService $service)
    {
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        $knownKeys = collect(config('modules.nav'))->pluck('key')->filter()->implode(',');
        $data = $request->validate([
            'workspace_key' => ['required', "in:{$knownKeys}"],
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ], ['file.max' => 'Cover maksimal 5 MB.']);
        $path = $service->storeLauncherCover($current->id(), $request->file('file'), $data['workspace_key'], $request->user());
        $row = CompanyExperience::firstOrNew(['company_id' => $current->id()]);
        $covers = (array) ($row->launcher_covers ?? []);
        $covers[$data['workspace_key']] = $path;
        $row->launcher_covers = $covers;
        $row->save();
        ThemeService::flush($current->id());

        return back()->with('status', 'Custom cover workspace tersimpan (dioptimalkan ke WebP 1200x675).');
    }

    /** Kembalikan cover workspace ke default registry. */
    public function deleteLauncherCover(Request $request, CurrentCompany $current, ExperienceVersionService $service)
    {
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        $knownKeys = collect(config('modules.nav'))->pluck('key')->filter()->implode(',');
        $data = $request->validate(['workspace_key' => ['required', "in:{$knownKeys}"]]);
        $service->deleteLauncherCover($current->id(), $data['workspace_key'], $request->user());

        return back()->with('status', 'Custom cover dihapus; default dikembalikan.');
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
            'nav_hidden' => ['nullable', 'array'],
            'nav_labels' => ['nullable', 'array'],
            'nav_labels.*' => ['nullable', 'max:60'],
            'terminology' => ['nullable', 'array'],
            'industry_pack' => ['nullable', 'in:'.implode(',', array_keys(config('industry-packs')))],
            'edition' => ['nullable', 'in:'.implode(',', array_keys(config('editions')))],
            'dash_enabled' => ['nullable', 'array'],
            'dash_width' => ['nullable', 'array'],
        ]);
        foreach (['primary_color', 'secondary_color', 'accent_color'] as $f) {
            if (isset($data[$f])) {
                $data[$f] = '#'.ltrim($service->sanitizeHex($data[$f]) ?? '', '#') ?: null;
                if ($data[$f] === '#') {
                    unset($data[$f]);
                }
            }
        }
        $navConfig = [
            'hidden' => array_values(array_map('intval', array_keys($request->input('nav_hidden', []) ?? []))),
            'labels' => collect($request->input('nav_labels', []))->filter(fn ($v) => filled($v))->all(),
        ];
        $terminology = collect($request->input('terminology', []))->filter(fn ($v) => filled($v))->map(fn ($v) => mb_substr((string) $v, 0, 60))->all();
        $registry = config('dashboard-widgets');
        $dash = [];
        foreach (array_keys((array) ($request->input('dash_enabled', []) ?? [])) as $wid) {
            if (isset($registry[$wid])) {
                $dash[] = ['id' => $wid, 'w' => (int) (($request->input('dash_width')[$wid] ?? null) ?: $registry[$wid]['width'])];
            }
        }

        $row = CompanyExperience::updateOrCreate(['company_id' => $current->id()], [
            ...collect($data)->filter(fn ($v) => filled($v))->all(),
            'nav_config' => $navConfig,
            'terminology' => $terminology,
            'industry_pack' => $data['industry_pack'] ?? null,
            'edition' => $data['edition'] ?? null,
            'dashboard_config' => $dash,
            'terminology' => $terminology,
            'industry_pack' => $data['industry_pack'] ?? null,
            'edition' => $data['edition'] ?? null,
            'dashboard_config' => $dash,
            'is_published' => true,
            'published_by' => $request->user()->id,
            'published_at' => now(),
        ]);
        ThemeService::flush($current->id());
        Term::flush();

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

    /** Mode pratinjau: session-scoped, hanya finance.manage, tidak menyentuh published. */
    public function startPreview(Request $request, ExperienceVersion $version, CurrentCompany $current)
    {
        abort_unless($version->company_id === $current->id(), 404);
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        session(['experience_preview_version' => $version->id]);

        return back()->with('status', "Mode pratinjau v{$version->version} aktif — tampilan berikut hanya terlihat oleh Anda.");
    }

    public function stopPreview(Request $request)
    {
        session()->forget('experience_preview_version');

        return back()->with('status', 'Mode pratinjau dimatikan.');
    }

    /** Export konfigurasi aktif sebagai JSON (tanpa kredensial/data sensitif). */
    public function export(Request $request, CurrentCompany $current)
    {
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        $row = CompanyExperience::find($current->id());
        $payload = [
            'schema' => 'graha-experience@1',
            'admin_theme' => $row?->admin_theme ?? ThemeService::DEFAULT_PRESET,
            'frontend_theme' => $row?->frontend_theme ?? 'corporate',
            'colors' => ['primary' => $row?->primary_color, 'secondary' => $row?->secondary_color, 'accent' => $row?->accent_color],
            'fonts' => ['ui' => $row?->font_ui, 'heading' => $row?->font_heading],
            'layout' => ['density' => $row?->density, 'button_style' => $row?->button_style, 'card_style' => $row?->card_style, 'sidebar_style' => $row?->sidebar_style, 'topbar_style' => $row?->topbar_style],
            'branding' => ['system_name' => $row?->system_name, 'company_display_name' => $row?->company_display_name, 'footer_text' => $row?->footer_text, 'support_email' => $row?->support_email, 'login_headline' => $row?->login_headline],
        ];

        return response()->json($payload)->header('Content-Disposition', 'attachment; filename="graha-experience-'.$current->id().'.json"');
    }

    /** Import JSON: validasi schema + whitelist nilai; hasilnya DRAFT, bukan langsung publish. */
    public function import(Request $request, CurrentCompany $current, ExperienceVersionService $service)
    {
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        $data = $request->validate(['file' => ['required', 'file', 'max:200']]);
        $json = json_decode($data['file']->getContent(), true);
        if (! is_array($json) || ($json['schema'] ?? '') !== 'graha-experience@1') {
            return back()->withErrors(['file' => 'Schema theme tidak dikenal — butuh graha-experience@1.']);
        }
        $config = $this->normalizeImported($json);
        $version = $service->saveDraft($current->id(), $config, $request->user());

        return back()->with('status', "Import diterima sebagai draft v{$version->version} — review lalu publish dari daftar versi.");
    }

    private function normalizeImported(array $json): array
    {
        $hex = fn ($v) => is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtoupper(substr($v, 1)) : null;
        $in = fn (array $list, $v) => in_array($v, $list, true) ? $v : null;
        $font = fn ($v) => in_array($v, ThemePresets::FONTS, true) ? $v : null;

        return [
            'admin_theme' => $in(array_keys(ThemePresets::all()), $json['admin_theme'] ?? null) ?? ThemeService::DEFAULT_PRESET,
            'frontend_theme' => $in(['corporate', 'construction', 'minimal', 'modern', 'professional'], $json['frontend_theme'] ?? null) ?? 'corporate',
            'primary_color' => $hex($json['colors']['primary'] ?? null),
            'secondary_color' => $hex($json['colors']['secondary'] ?? null),
            'accent_color' => $hex($json['colors']['accent'] ?? null),
            'font_ui' => $font($json['fonts']['ui'] ?? null),
            'font_heading' => $font($json['fonts']['heading'] ?? null),
            'density' => $in(['compact', 'comfortable'], $json['layout']['density'] ?? null),
            'button_style' => $in(['square', 'soft', 'rounded', 'pill'], $json['layout']['button_style'] ?? null),
            'card_style' => $in(['minimal', 'bordered', 'elevated', 'soft'], $json['layout']['card_style'] ?? null),
            'sidebar_style' => $in(['dark', 'light', 'brand'], $json['layout']['sidebar_style'] ?? null),
            'topbar_style' => $in(['light', 'dark', 'brand'], $json['layout']['topbar_style'] ?? null),
            'system_name' => isset($json['branding']['system_name']) ? mb_substr((string) $json['branding']['system_name'], 0, 80) : null,
            'company_display_name' => isset($json['branding']['company_display_name']) ? mb_substr((string) $json['branding']['company_display_name'], 0, 120) : null,
            'footer_text' => isset($json['branding']['footer_text']) ? mb_substr((string) $json['branding']['footer_text'], 0, 200) : null,
            'support_email' => isset($json['branding']['support_email']) && filter_var($json['branding']['support_email'], FILTER_VALIDATE_EMAIL) ? $json['branding']['support_email'] : null,
            'login_headline' => isset($json['branding']['login_headline']) ? mb_substr((string) $json['branding']['login_headline'], 0, 150) : null,
        ];
    }

    /** Serving asset branding: path harus terdaftar pada experience company tsb (anti traversal/enumeration). */
    public function serveAsset(int $companyId, string $file)
    {
        $row = CompanyExperience::where('company_id', $companyId)->first();
        abort_unless($row, 404);
        abort_if(! preg_match('/^[a-zA-Z0-9._-]+$/', $file), 404);

        // Custom launcher cover (dekoratif, publik-safe): /branding/{company}/cover-{key}-*.webp
        if (str_starts_with($file, 'cover-') && str_ends_with($file, '.webp')) {
            $cover = collect((array) ($row->launcher_covers ?? []))
                ->filter(fn ($p) => is_string($p) && str_ends_with($p, '/'.$file))
                ->first();
            abort_unless(is_string($cover) && Storage::disk('local')->exists($cover), 404);

            return Storage::disk('local')->response($cover, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=3600']);
        }

        $relative = collect([$row->logo_path, $row->favicon_path])->filter()->first(fn ($p) => str_ends_with((string) $p, '/'.$file));
        abort_unless($relative && ! preg_match('#[\\\\/]{2}|\.\./#', $file), 404);
        abort_unless(Storage::disk('local')->exists($relative), 404);

        $mime = str_ends_with($file, '.svg') ? 'image/svg+xml' : (str_ends_with($file, '.png') ? 'image/png' : (str_ends_with($file, '.webp') ? 'image/webp' : 'image/jpeg'));

        return Storage::disk('local')->response($relative, null, ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=3600']);
    }
}
