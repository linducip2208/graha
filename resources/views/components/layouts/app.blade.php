@php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag)
@php($expCfg = $experience["config"] ?? [])
<!doctype html>
<html lang="id" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="description" content="ERP konstruksi pondasi: tender sampai bored pile dalam satu jejak data ter-audit.">
<meta property="og:title" content="{{ $title ?? config('app.name') }}">
<meta property="og:description" content="ERP multi-company untuk kontraktor pondasi: approval berjenjang, jurnal otomatis, audit hash-chain.">
<meta property="og:type" content="website">
<link rel="icon" href="{{ $expCfg['favicon_url'] ?? asset('favicon-default.svg') }}">
<title>{{ ($expCfg['system_name'] ?? null) ? $expCfg['system_name'].' — '.config('app.name') : ($title ?? config('app.name')) }}</title>
@vite(['resources/css/app.css','resources/css/adminlte.css','resources/js/adminlte.js'])
<style>:root{@foreach(($experience["tokens"] ?? []) as $tk => $tv){{ $tk }}:{{ $tv }};@endforeach}}</style>
</head>
<body class="min-h-screen bg-[var(--surface-page)] text-[var(--text-primary)]" data-flash="{{ session('status') }}" data-flash-error="{{ $errors->any() ? $errors->first() : '' }}" data-authed="{{ auth()->check() ? '1' : '0' }}">
@if(auth()->check() && !empty($expCfg['previewing_version']))
<div class="bg-violet-700 px-4 py-1.5 text-center text-xs font-bold text-white no-print">MODE PRATINJAU v{{ $expCfg['previewing_version'] }} — hanya terlihat oleh Anda
<form method="post" action="/admin/experience/preview/stop" class="inline ml-2">@csrf<button class="underline">Matikan</button></form></div>
@endif
@if(auth()->check())
@php($cid = session('company_id'))
@php($navGroups = \App\Support\Navigation::groups(auth()->user(), $cid))
@php($berandaGroup = $navGroups->firstWhere('key', 'beranda'))
@php($appGroups = $navGroups->filter(fn ($g) => $g['key'] !== 'beranda' && $g['key'] !== 'pengaturan' && $g['items']->isNotEmpty()))
@php($sistemGroup = $navGroups->firstWhere('key', 'pengaturan'))
@php($path = request()->getPathInfo())
@php($activeWorkspace = \App\Support\Navigation::activeGroupKey($appGroups, $path))
@php($wsIcons = ['komersial' => 'flag', 'proyek' => 'cube', 'supply-chain' => 'archive', 'operations' => 'cog', 'keuangan' => 'banknote', 'quality-hse' => 'shield', 'documents-approval' => 'document', 'laporan' => 'chart'])
@php($initials = collect(explode(' ', trim(auth()->user()->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode(''))
<div class="app-wrapper min-h-screen lg:grid lg:grid-cols-[232px_1fr] print:block">
<aside id="admin-sidebar" class="app-sidebar app-sidebar-dark fixed inset-y-0 left-0 z-40 flex w-[256px] -translate-x-full flex-col overflow-y-auto border-r transition-transform lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:translate-x-0 print:hidden">
<div class="flex h-14 shrink-0 items-center justify-between border-b px-4" style="border-color:var(--border-subtle)">
<a href="/dashboard" class="flex min-w-0 items-center gap-2.5">
@if(!empty($expCfg['logo_url']))<img src="{{ $expCfg['logo_url'] }}" alt="logo" class="h-7 max-w-[140px] object-contain">@else
<span class="grid h-8 w-8 shrink-0 place-items-center rounded-[10px] text-[13px] font-black text-white" style="background:linear-gradient(135deg,var(--brand-primary),var(--brand-accent))">{{ mb_substr($expCfg['system_name'] ?? config('app.name'), 0, 1) }}</span>
<span class="min-w-0 truncate text-[13px] font-extrabold tracking-tight" style="color:var(--text-sidebar)">{{ $expCfg['system_name'] ?? config('app.name') }}</span>
@endif
</a>
<button data-sidebar-close class="rounded-lg p-2 hover:bg-[var(--surface-muted)] lg:hidden" aria-label="Tutup menu">✕</button>
</div>
<nav class="flex-1 space-y-0.5 px-3 pb-4 text-sm">
<p class="shell-section">Beranda</p>
@foreach(($berandaGroup['items'] ?? collect())->filter(fn ($item) => ($item['href'] ?? '') !== '/apps') as $item)
<a href="{{ $item['href'] }}" class="shell-link {{ \App\Support\Navigation::isPathActive($item['href'], $path) ? 'active' : '' }}"><x-ui.icon :name="$item['icon'] ?? 'dashboard'" class="h-[18px] w-[18px]" /><span class="truncate">{{ $item['label'] }}</span></a>
@endforeach
@if($shellFavorites->isNotEmpty())
<p class="shell-section">Favorit</p>
@foreach($shellFavorites as $fav)
<a href="{{ $fav->href }}" class="shell-link"><x-ui.icon name="star" class="h-[18px] w-[18px]" /><span class="truncate">{{ $fav->label }}</span></a>
@endforeach
@endif
<p class="shell-section">Aplikasi</p>
<div id="workspace-nav" data-active-workspace="{{ $activeWorkspace }}">
@foreach($appGroups as $group)
@php($wsKey = (string) ($group['key'] ?? str($group['label'])->slug()))
@php($isActive = $wsKey === $activeWorkspace)
<details class="ws-group{{ $isActive ? ' ws-active' : '' }}" data-ws-key="{{ $wsKey }}"@if($isActive) open @endif>
<summary class="ws-row"><x-ui.icon :name="$wsIcons[$wsKey] ?? ($group['items']->first()['icon'] ?? 'grid')" class="h-[18px] w-[18px]" /><span class="min-w-0 flex-1 truncate">{{ preg_replace('/^[^\p{L}\d]+/u', '', $group['label']) }}</span><x-ui.icon name="chevron-down" class="ws-chevron h-4 w-4" /></summary>
<div class="ws-body">
@php($activeItems = \App\Support\Navigation::activeItems($group, $path))
@foreach($group['items'] as $item)
@php($selfActive = $activeItems->contains(fn ($m) => ($m['item']['href'] ?? null) === $item['href']))
@php($childActive = ! empty($item['children']) && $activeItems->contains(fn ($m) => collect($item['children'])->contains(fn ($c) => ($c['href'] ?? null) === ($m['item']['href'] ?? null))))
<a href="{{ $item['href'] }}" class="ws-link{{ $selfActive ? ' active' : '' }}"><x-ui.icon :name="$item['icon'] ?? 'grid'" class="h-4 w-4 shrink-0" />{{ $item['label'] }}</a>
@if(! empty($item['children']) && ($selfActive || $childActive))
@foreach($item['children'] as $child)
<a href="{{ $child['href'] }}" class="ws-sublink{{ $activeItems->contains(fn ($m) => ($m['item']['href'] ?? null) === $child['href']) ? ' active' : '' }}"><x-ui.icon :name="$child['icon'] ?? 'chevron-right'" class="h-3.5 w-3.5 shrink-0" />{{ $child['label'] }}</a>
@endforeach
@endif
@endforeach
</div>
</details>
@endforeach
<a href="/apps" class="ws-apps-footer"><x-ui.icon name="grid" class="h-3.5 w-3.5" />Semua Aplikasi…</a>
</div>
@if($sistemGroup && $sistemGroup['items']->isNotEmpty())
<p class="shell-section">Sistem</p>
@foreach($sistemGroup['items'] as $item)
<a href="{{ $item['href'] }}" class="shell-link {{ \App\Support\Navigation::isPathActive($item['href'], $path) ? 'active' : '' }}"><x-ui.icon :name="$item['icon'] ?? 'cog'" class="h-[18px] w-[18px]" /><span class="truncate">{{ $item['label'] }}</span></a>
@endforeach
@endif
</nav>
<div class="sticky bottom-0 mt-auto border-t p-3" style="border-color:var(--border-subtle);background:var(--surface-sidebar,#ffffff)">
@if($shellMemberships->count() > 1)
<details class="dropdown">
<summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-xl p-2 transition hover:bg-[var(--surface-muted)]">
<span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-[11px] font-black text-white" style="background:var(--brand-secondary)">{{ ($company = $shellMemberships->firstWhere('id', (int) $cid))?->code ?? 'PT' }}</span>
<span class="min-w-0 flex-1"><span class="block truncate text-xs font-extrabold" style="color:var(--text-sidebar)">{{ $company?->name ?? 'Perusahaan' }}</span><span class="block text-[10px]" style="color:var(--text-sidebar-muted)">Ganti perusahaan</span></span>
<x-ui.icon name="chevron-down" class="h-4 w-4 text-[var(--text-muted)]" />
</summary>
<div class="absolute bottom-full left-0 right-0 z-30 mb-2 overflow-hidden rounded-xl border bg-[var(--surface-card)] shadow-[var(--shadow-dropdown)]">
@foreach($shellMemberships as $m)
<form method="post" action="{{ route('company.switch') }}">@csrf
<button type="submit" name="company_id" value="{{ $m->id }}" class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-xs font-semibold transition hover:bg-[var(--surface-muted)] {{ $m->id === (int) $cid ? 'text-[var(--brand-primary)]' : '' }}">{{ $m->name }}</button>
</form>
@endforeach
</div>
</details>
@else
@php($activeCompany = $shellMemberships->firstWhere('id', (int) $cid))
<div class="flex items-center gap-2.5 rounded-xl p-2">
<span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-[11px] font-black text-white" style="background:var(--brand-secondary)">{{ $activeCompany?->code ?? 'PT' }}</span>
<span class="min-w-0"><span class="block truncate text-xs font-extrabold" style="color:var(--text-sidebar)">{{ $activeCompany?->name ?? 'Perusahaan' }}</span><span class="block text-[10px]" style="color:var(--text-sidebar-muted)">{{ $activeCompany?->code }}</span></span>
</div>
@endif
</div>
</aside>
<div id="sidebar-overlay" data-sidebar-close class="fixed inset-0 z-30 hidden bg-slate-950/50 lg:hidden"></div>
<div class="min-w-0">
<header class="app-header sticky top-0 z-20 flex h-14 items-center justify-between gap-3 border-b bg-[var(--surface-card)]/90 px-3 backdrop-blur lg:px-6 print:hidden" style="border-color:var(--border-subtle)">
<div class="flex min-w-0 flex-1 items-center gap-2">
<button data-sidebar-open class="rounded-xl border p-2 hover:bg-[var(--surface-muted)] lg:hidden" aria-label="Buka menu"><x-ui.icon name="menu" class="h-5 w-5" /></button>
<button id="global-search-trigger" class="flex h-10 w-10 items-center justify-center rounded-xl border text-[var(--text-muted)] transition hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)] md:h-9 md:w-72 md:justify-start md:gap-2 md:px-3 md:text-sm" aria-label="Cari (Ctrl+K)"><x-ui.icon name="search" class="h-4 w-4" /><span class="hidden md:inline">Cari apa saja…</span><kbd class="ml-auto hidden rounded border bg-[var(--surface-muted)] px-1.5 py-0.5 font-mono text-[10px] md:inline">Ctrl K</kbd></button>
</div>
<div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
<div class="relative">
<button id="quick-create-trigger" class="flex h-9 items-center gap-1.5 rounded-xl px-3 text-sm font-bold text-white shadow-sm transition hover:opacity-90" style="background:var(--brand-primary)" aria-label="Buat baru" aria-haspopup="true"><x-ui.icon name="plus" class="h-4 w-4" /><span class="hidden sm:inline">Buat</span></button>
<div id="quick-create-menu" hidden class="absolute right-0 top-full z-30 mt-2 w-64 overflow-hidden rounded-xl border bg-[var(--surface-card)] shadow-[var(--shadow-dropdown)]"><p class="border-b bg-[var(--surface-muted)] px-4 py-2 text-[10px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Buat Cepat</p><div class="py-1">@foreach(\App\Support\QuickCreate::items(auth()->user(), $cid) as $quick)<a href="{{ $quick['href'] }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-[var(--surface-muted)]"><x-ui.icon :name="$quick['icon'] ?? 'plus'" class="h-4 w-4 text-[var(--brand-primary)]" />{{ $quick['label'] }}</a>@endforeach</div></div>
</div>
<a href="/admin/notifications" class="relative grid h-9 w-9 place-items-center rounded-xl border transition hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]" title="Notifikasi" aria-label="Notifikasi"><x-ui.icon name="bell" class="h-[18px] w-[18px]" />@php($unread = auth()->user()->unreadNotifications->count())@if($unread > 0)<span class="absolute -top-1 -right-1 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-red-600 px-1 text-[9px] font-black text-white">{{ $unread > 99 ? '99+' : $unread }}</span>@endif</a>
<details class="dropdown relative">
<summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-xl border p-1 pr-2 transition hover:border-[var(--brand-primary)]">
<span class="grid h-7 w-7 place-items-center rounded-full text-[10px] font-black text-white" style="background:var(--brand-primary)">{{ $initials }}</span>
<span class="hidden text-left sm:block"><span class="block max-w-32 truncate text-xs font-extrabold leading-tight">{{ auth()->user()->name }}</span><span class="block text-[10px] leading-tight text-[var(--text-muted)]">{{ $shellRole ?: 'Anggota' }}</span></span>
<x-ui.icon name="chevron-down" class="h-4 w-4 text-[var(--text-muted)]" />
</summary>
<div class="dropdown-panel absolute right-0 top-full z-30 mt-2 w-56 overflow-hidden rounded-xl border bg-[var(--surface-card)] py-1 shadow-[var(--shadow-dropdown)]">
<a href="/admin/my-work" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-[var(--surface-muted)]"><x-ui.icon name="check" class="h-4 w-4 text-[var(--text-muted)]" />My Work</a>
<a href="{{ route('apps') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-[var(--surface-muted)]"><x-ui.icon name="grid" class="h-4 w-4 text-[var(--text-muted)]" />Semua Aplikasi</a>
<a href="/admin/my-signature" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-[var(--surface-muted)]"><x-ui.icon name="pen" class="h-4 w-4 text-[var(--text-muted)]" />Tanda Tangan Saya</a>
<button type="button" id="theme-toggle" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm hover:bg-[var(--surface-muted)]"><x-ui.icon name="moon" class="h-4 w-4 text-[var(--text-muted)]" />Ganti Tema</button>
<a href="/docs" class="flex items-center gap-2.5 px-4 py-2.5 text-sm hover:bg-[var(--surface-muted)]"><x-ui.icon name="document" class="h-4 w-4 text-[var(--text-muted)]" />Dokumentasi</a>
<div class="my-1 border-t" style="border-color:var(--border-subtle)"></div>
<form method="post" action="/logout">@csrf
<button class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40"><x-ui.icon name="logout" class="h-4 w-4" />Keluar</button>
</form>
</div>
</details>
</div>
</header>
<div id="breadcrumb-bar" class="flex flex-wrap items-center gap-1 border-b bg-[var(--surface-card)]/60 px-4 py-2 text-xs text-[var(--text-muted)] backdrop-blur lg:px-8 print:hidden" style="border-color:var(--border-subtle)"><a href="/dashboard" class="hover:text-[var(--brand-primary)]">Beranda</a>@isset($breadcrumbs)@foreach($breadcrumbs as $crumb)<span>›</span>@if(isset($crumb['href']) && !$loop->last)<a href="{{ $crumb['href'] }}" class="hover:text-[var(--brand-primary)]">{{ $crumb['label'] }}</a>@else<span class="font-semibold text-[var(--text-secondary)]">{{ $crumb['label'] }}</span>@endif @endforeach @endisset</div>
<div id="search-palette" hidden class="fixed inset-0 z-50 flex items-start justify-center bg-slate-950/60 p-4 pt-24 print:hidden"><div class="w-full max-w-2xl overflow-hidden rounded-2xl border bg-[var(--surface-card)] shadow-2xl"><input id="search-input" type="search" placeholder="Cari proyek, tender, PO, billing, NCR, dokumen…" autocomplete="off" class="w-full border-b p-4 text-base outline-none"><div id="search-results" class="max-h-96 overflow-y-auto p-2 text-sm"></div><p class="border-t bg-[var(--surface-muted)] px-4 py-2 text-[11px] text-[var(--text-muted)]">Hasil dibatasi sesuai kewenangan perusahaan Anda · Esc untuk menutup</p></div></div>
<main class="app-main">{{ $slot }}</main></div>
</div>
@else
<header class="sticky top-0 z-20 border-b bg-[var(--surface-card)]/90 backdrop-blur"><nav class="mx-auto flex max-w-7xl justify-between px-5 py-4"><a href="/" class="flex items-center gap-2 font-black text-[var(--brand-primary)]">@if(!empty($expCfg['logo_url']))<img src="{{ $expCfg['logo_url'] }}" alt="logo" class="h-7 max-w-[150px] object-contain">@else<span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-sky-500 to-cyan-600 text-sm">🏗️</span><span>{{ $expCfg['system_name'] ?? 'Graha Pondasi ERP' }}</span>@endif</a><div class="flex gap-4"><a href="/docs">Dokumentasi</a><a href="/login">Masuk</a></div></nav></header><main>{{ $slot }}</main>
@endif
<x-ui.toast />
<div id="confirm-modal" hidden class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4 print:hidden" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
<div class="w-full max-w-md rounded-2xl border bg-[var(--surface-card)] p-6 shadow-[var(--shadow-modal)]">
<div class="flex items-start gap-4">
<span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-red-50 text-red-600"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></span>
<div class="min-w-0"><h2 id="confirm-modal-title" class="text-base font-black tracking-tight">Konfirmasi</h2><p id="confirm-modal-message" class="mt-1 text-sm leading-relaxed text-slate-500"></p></div>
</div>
<div class="mt-5 flex justify-end gap-2">
<button type="button" id="confirm-modal-cancel" class="rounded-xl border px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-slate-50">Batal</button>
<button type="button" id="confirm-modal-ok" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-700">Ya, Lanjutkan</button>
</div>
</div>
</div>
<script>
(function () {
  var modal = document.getElementById('confirm-modal');
  if (! modal) return;
  var msgEl = document.getElementById('confirm-modal-message');
  var cancelBtn = document.getElementById('confirm-modal-cancel');
  var okBtn = document.getElementById('confirm-modal-ok');
  var pending = null;
  var lastFocus = null;
  function open(el, message) {
    pending = el;
    lastFocus = document.activeElement;
    msgEl.textContent = message;
    modal.hidden = false;
    cancelBtn.focus();
  }
  function close() {
    modal.hidden = true;
    var target = pending;
    pending = null;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
    return target;
  }
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (! el) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    open(el, el.dataset.confirm);
  }, true);
  okBtn.addEventListener('click', function () {
    var el = close();
    if (! el) return;
    el.removeAttribute('data-confirm');
    if (el.tagName === 'FORM') {
      if (el.requestSubmit) el.requestSubmit(); else el.submit();
      return;
    }
    el.click();
  });
  cancelBtn.addEventListener('click', function () { close(); });
  modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
  document.addEventListener('keydown', function (e) {
    if (modal.hidden) return;
    if (e.key === 'Escape') { e.preventDefault(); close(); }
  });
  // Dropdown (details.dropdown): tutup saat klik di luar.
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.dropdown[open]').forEach(function (d) {
      if (! d.contains(e.target)) d.removeAttribute('open');
    });
  });
})();
</script>
</body></html>
