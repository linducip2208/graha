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
</section></x-layouts.app>
