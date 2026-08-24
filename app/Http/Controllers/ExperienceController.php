<?php

namespace App\Http\Controllers;

use App\Models\CompanyExperience;
use App\Services\AuditTrail;
use App\Services\ThemeService;
use App\Support\Experience\ThemePresets;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

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
}
