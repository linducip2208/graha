<x-layouts.app title="Experience Studio"><section class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-2xl font-bold tracking-tight">Experience Studio</h1>
<p class="mt-1 text-sm text-slate-500">Ubah identitas & tema ERP perusahaan tanpa menyentuh code. Perubahan langsung diterbitkan untuk semua user company ini.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<form method="post" action="/admin/experience" class="mt-6 space-y-5" @class(['bg-white dark:bg-[#101a2c] rounded-2xl border p-6'])>
@csrf
@php($cfg = $row ?? null)
<fieldset class="rounded-xl border p-4"><legend class="px-2 text-sm font-bold">Preset Admin Theme</legend>
<div class="grid gap-2 sm:grid-cols-3">@foreach($presets as $p)
<label class="flex items-center gap-2 rounded-xl border p-3 text-sm cursor-pointer has-checked:border-sky-500"><input type="radio" name="admin_theme" value="{{ $p['key'] }}" @checked(($cfg?->admin_theme ?? 'executive-navy') === $p['key'])> {{ $p['label'] }}</label>
@endforeach</div>
</fieldset>

<fieldset class="rounded-xl border p-4"><legend class="px-2 text-sm font-bold">Warna Brand</legend>
<div class="grid gap-3 sm:grid-cols-3">
<label class="text-xs font-semibold">Primary<input type="color" name="primary_color" value="{{ $cfg?->primary_color ?? '#0369a1' }}" class="mt-1 h-10 w-full rounded-lg border"></label>
<label class="text-xs font-semibold">Secondary<input type="color" name="secondary_color" value="{{ $cfg?->secondary_color ?? '#0e7490' }}" class="mt-1 h-10 w-full rounded-lg border"></label>
<label class="text-xs font-semibold">Accent<input type="color" name="accent_color" value="{{ $cfg?->accent_color ?? '#38bdf8' }}" class="mt-1 h-10 w-full rounded-lg border"></label>
</div>
</fieldset>

<fieldset class="rounded-xl border p-4"><legend class="px-2 text-sm font-bold">Tipografi (whitelist)</legend>
<div class="grid gap-3 sm:grid-cols-2">
<label class="text-xs font-semibold">Font UI<select name="font_ui" class="mt-1 w-full rounded-xl border p-2.5">@foreach(\App\Support\Experience\ThemePresets::FONTS as $font)<option value="{{ $font }}" @selected($cfg?->font_ui === $font)>{{ $font }}</option>@endforeach</select></label>
<label class="text-xs font-semibold">Font Heading<select name="font_heading" class="mt-1 w-full rounded-xl border p-2.5">@foreach(\App\Support\Experience\ThemePresets::FONTS as $font)<option value="{{ $font }}" @selected($cfg?->font_heading === $font)>{{ $font }}</option>@endforeach</select></label>
</div>
</fieldset>

<fieldset class="rounded-xl border p-4"><legend class="px-2 text-sm font-bold">White Label</legend>
<div class="grid gap-3 sm:grid-cols-2">
<label class="text-xs font-semibold">System Name<input name="system_name" value="{{ $cfg?->system_name }}" placeholder="Graha Pondasi ERP" class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">Company Display Name<input name="company_display_name" value="{{ $cfg?->company_display_name }}" class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">Support Email<input type="email" name="support_email" value="{{ $cfg?->support_email }}" class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">Login Headline<input name="login_headline" value="{{ $cfg?->login_headline }}" placeholder="Satu ERP. Milik Anda." class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold sm:col-span-2">Footer Text<input name="footer_text" value="{{ $cfg?->footer_text }}" class="mt-1 w-full rounded-xl border p-2.5"></label>
</div>
</fieldset>

<fieldset class="rounded-xl border p-4"><legend class="px-2 text-sm font-bold">Layout & Komponen</legend>
<div class="grid gap-3 sm:grid-cols-4">
<label class="text-xs font-semibold">Sidebar<select name="sidebar_style" class="mt-1 w-full rounded-xl border p-2.5"><option value="">Default preset</option>@foreach(['dark','light','brand'] as $o)<option value="{{ $o }}" @selected($cfg?->sidebar_style === $o)>{{ ucfirst($o) }}</option>@endforeach</select></label>
<label class="text-xs font-semibold">Button Style<select name="button_style" class="mt-1 w-full rounded-xl border p-2.5"><option value="">Default</option>@foreach(['square','soft','rounded','pill'] as $o)<option value="{{ $o }}" @selected($cfg?->button_style === $o)>{{ ucfirst($o) }}</option>@endforeach</select></label>
<label class="text-xs font-semibold">Card Style<select name="card_style" class="mt-1 w-full rounded-xl border p-2.5"><option value="">Default</option>@foreach(['minimal','bordered','elevated','soft'] as $o)<option value="{{ $o }}" @selected($cfg?->card_style === $o)>{{ ucfirst($o) }}</option>@endforeach</select></label>
<label class="text-xs font-semibold">Density<select name="density" class="mt-1 w-full rounded-xl border p-2.5"><option value="">Default</option>@foreach(['compact','comfortable'] as $o)<option value="{{ $o }}" @selected($cfg?->density === $o)>{{ ucfirst($o) }}</option>@endforeach</select></label>
</div>
</fieldset>

<div class="flex items-center gap-3"><x-ui.button>Terbitkan tampilan</x-ui.button><span class="text-xs text-slate-400">Tersimpan sebagai konfigurasi aktif company — audit tercatat.</span></div>
</form>

<div class="mt-6 grid gap-5 lg:grid-cols-2">
<x-ui.card label="Brand Asset (privat)">
<form method="post" action="/admin/experience/assets" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">@csrf
<select name="kind" class="rounded-lg border p-1.5 text-xs"><option value="logo">Logo</option><option value="favicon">Favicon</option></select>
<input type="file" name="file" accept=".png,.jpg,.jpeg,.webp,.svg" required class="rounded-lg border p-1.5 text-xs">
<button class="font-bold text-violet-700 text-xs">Upload</button>
</form>
@if($row?->logo_path)<p class="mt-2 text-xs text-emerald-700">Logo aktif tersimpan.</p>@endif
<p class="mt-1 text-[11px] text-slate-400">PNG/JPG/WebP/SVG (SVG otomatis disanitasi) · maks 2 MB · storage privat ber-authorization.</p>
</x-ui.card>

<div class="flex flex-wrap gap-2 no-print">
<a href="/admin/experience/export" class="rounded-xl border px-3 py-1.5 text-xs font-bold">Export JSON</a>
<form method="post" action="/admin/experience/import" enctype="multipart/form-data" class="flex items-center gap-1">@csrf
<input type="file" name="file" accept=".json" required class="rounded-lg border p-1 text-xs">
<button class="rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-bold text-white">Import JSON</button>
</form>
</div>
<fieldset class="rounded-xl border p-4"><legend class="px-2 text-sm font-bold">Navigation Composer & Istilah</legend>
<div class="space-y-2">@foreach($navGroupsCfg as $idx => $group)
<div class="flex flex-wrap items-center gap-2 text-sm">
<input type="checkbox" name="nav_hidden[{{ $idx }}]" value="1" @checked(in_array($idx, $navConfig['hidden'] ?? []))> <span class="w-56 truncate">{{ $group['label'] }}</span>
<input name="nav_labels[{{ $idx }}]" value="{{ $navConfig['labels'][$idx] ?? '' }}" placeholder="Ganti label (opsional)" class="flex-1 rounded-lg border p-1.5 text-xs">
</div>
@endforeach</div>
<p class="mt-2 text-[11px] text-slate-400">Hide hanya menyembunyikan menu — permission backend tetap berlaku.</p>
<div class="mt-3 grid gap-2 sm:grid-cols-2">
@foreach(['Customer' => 'Klien', 'Vendor' => 'Supplier', 'Project' => 'Pekerjaan', 'Tender' => 'Lelang', 'Bored Pile' => 'Titik Pondasi'] as $from => $ph)
<label class="text-xs font-semibold">{{ $from }} → <input name="terminology[{{ $from }}]" value="{{ $terminologyMap[$from] ?? '' }}" placeholder="{{ $ph }}" class="mt-1 w-full rounded-lg border p-1.5 text-xs"></label>
@endforeach
</div>
</fieldset>
<fieldset class="rounded-xl border p-4"><legend class="px-2 text-sm font-bold">Industry Pack & Edition</legend>
<div class="grid gap-3 sm:grid-cols-2">
<label class="text-xs font-semibold">Industry Pack<select name="industry_pack" class="mt-1 w-full rounded-xl border p-2.5"><option value="">— default —</option>@foreach(config('industry-packs') as $k => $p)<option value="{{ $k }}" @selected(($cfg?->industry_pack) === $k)>{{ $p['label'] }}</option>@endforeach</select></label>
<label class="text-xs font-semibold">Edition<select name="edition" class="mt-1 w-full rounded-xl border p-2.5"><option value="">— semua modul —</option>@foreach(config('editions') as $k => $e)<option value="{{ $k }}" @selected(($cfg?->edition) === $k)>{{ $e['label'] }}</option>@endforeach</select></label>
</div>
</fieldset>

<fieldset class="rounded-xl border p-4"><legend class="px-2 text-sm font-bold">Dashboard Builder</legend>
<p class="text-[11px] text-slate-400">Kosongkan = layout legacy. Centang widget yang ingin tampil; lebar kolom grid 12.</p>
<div class="grid gap-1 sm:grid-cols-3">@foreach(config('dashboard-widgets') as $wid => $w)
<label class="flex items-center gap-2 rounded-lg border p-2 text-xs cursor-pointer has-checked:border-sky-500">
<input type="checkbox" name="dash_enabled[{{ $wid }}]" value="1" @checked(collect($cfg?->dashboard_config ?? [])->pluck('id')->contains($wid))>
<span class="flex-1">{{ $w['label'] }}</span>
<select name="dash_width[{{ $wid }}]" class="rounded border p-0.5 text-[10px]">@foreach([3,4,6,12] as $wd)<option value="{{ $wd }}" @selected(collect($cfg?->dashboard_config ?? [])->firstWhere('id',$wid)['w'] ?? $w['width'] === $wd)>w{{ $wd }}</option>@endforeach</select>
</label>
@endforeach
</div>
</fieldset>
@php($versions = \App\Models\ExperienceVersion::where('company_id', app(\App\Support\Tenancy\CurrentCompany::class)->id())->orderByDesc('version')->limit(10)->get())
<x-ui.card label="Versi Tampilan">
<form method="post" action="/admin/experience/draft" class="mb-2 no-print">@csrf<x-ui.button variant="secondary" type="submit" class="!py-1.5 !text-xs">Simpan sebagai draft baru</x-ui.button></form>
<table class="w-full text-xs"><thead><tr><th>Versi</th><th>Status</th><th>Diterbitkan</th><th>Aksi</th></tr></thead><tbody>
@forelse($versions as $v)
<tr class="border-t"><td class="font-mono font-bold">v{{ $v->version }}</td><td><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $v->status === 'published' ? 'bg-emerald-50 text-emerald-700' : ($v->status === 'draft' ? 'bg-sky-50 text-sky-700' : 'bg-slate-100 text-slate-500') }}">{{ strtoupper($v->status) }}</span></td><td>{{ $v->published_at?->format('d/m/Y H:i') ?? '-' }}</td>
<td class="flex gap-2">@if($v->status !== 'published')

@endif
@if($v->status === 'archived' || $v->status === 'published')
<form method="post" action="/admin/experience/versions/{{ $v->id }}/rollback">@csrf<button onclick="return confirm('Rollback ke konfigurasi v{{ $v->version }}?')" class="font-bold text-amber-700 text-[11px]">Rollback</button></form>
@endif
</td></tr>
@empty<tr><td colspan="4" class="p-3 text-slate-400">Belum ada versi — simpan draft pertama untuk mulai versioning.</td></tr>@endforelse
</tbody></table>
</x-ui.card>
</div>
</section></x-layouts.app>
