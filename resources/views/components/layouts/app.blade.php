@php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag)
<!doctype html>
<html lang="id"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="description" content="ERP konstruksi pondasi: tender sampai bored pile dalam satu jejak data ter-audit.">
<meta property="og:title" content="{{ $title ?? config('app.name') }}">
<meta property="og:description" content="ERP multi-company untuk kontraktor pondasi: approval berjenjang, jurnal otomatis, audit hash-chain.">
<meta property="og:type" content="website">
@php($expCfg = $experience["config"] ?? [])
<link rel="icon" href="{{ $expCfg['favicon_url'] ?? asset('favicon-default.svg') }}">
<title>{{ ($expCfg['system_name'] ?? null) ? $expCfg['system_name'].' — '.config('app.name') : ($title ?? config('app.name')) }}</title>
<style>:root{@foreach(($experience["tokens"] ?? []) as $tk => $tv){{ $tk }}:{{ $tv }};@endforeach}}</style>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900"
@if(!empty($experience["config"]["previewing_version"]))
<div class="bg-violet-700 px-4 py-1.5 text-center text-xs font-bold text-white no-print">MODE PRATINJAU v{{ $experience["config"]["previewing_version"] }} — hanya terlihat oleh Anda
<form method="post" action="/admin/experience/preview/stop" class="inline ml-2">@csrf<button class="underline">Matikan</button></form></div>
@endif data-flash="{{ session('status') }}" data-flash-error="{{ $errors->any() ? $errors->first() : '' }}" data-authed="{{ auth()->check() ? '1' : '0' }}">
@if(auth()->check())
@php($cid = session('company_id'))
@php($navGroups = \App\Support\Navigation::groups(auth()->user(), $cid))
<div class="min-h-screen lg:grid lg:grid-cols-[236px_1fr] print:block">
<aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 w-[248px] -translate-x-full overflow-y-auto bg-slate-950 text-slate-200 shadow-2xl transition-transform lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:translate-x-0 print:hidden">
<div class="flex items-center justify-between border-b border-white/10 px-4 py-3"><a href="/dashboard" class="flex items-center gap-2 font-black text-white">@if(!empty($expCfg['logo_url']))<img src="{{ $expCfg['logo_url'] }}" alt="logo" class="h-7 max-w-[150px] object-contain">@else<span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-sky-500 to-cyan-600 text-sm">🏗️</span><span>{{ $expCfg['system_name'] ?? 'Graha Pondasi ERP' }}</span>@endif</a><button data-sidebar-close class="rounded-lg p-2 lg:hidden" aria-label="Tutup menu">✕</button></div>
 <nav id="admin-navigation" class="space-y-4 p-4 text-sm">
  @foreach($navGroups as $group)
  <details class="nav-group" data-group="{{ $group['label'] }}" open>
   <summary class="flex cursor-pointer select-none items-center justify-between px-3 py-1.5 text-[11px] font-bold uppercase tracking-widest text-slate-500 hover:text-slate-300">
    <span>{{ $group['label'] }}</span><span class="nav-chevron text-[9px] opacity-60">&#9660;</span>
   </summary>
   <div class="space-y-0.5 pt-0.5">
   @foreach($group['items'] as $item)
    @if(! empty($item['children']))
    <details class="nav-details" {{ url()->current() === url($item['href']) ? 'open' : '' }}>
     <summary class="admin-link cursor-pointer list-none"><x-ui.icon :name="$item['icon'] ?? 'dashboard'" class="h-[18px] w-[18px]" /><span>{{ $item['label'] }}</span><span class="nav-chevron ml-auto text-[10px] opacity-60">&#9660;</span></summary>
     <div class="ml-6 mt-1 space-y-1 border-l border-white/10 pl-3">
      @foreach($item['children'] as $child)
      <a href="{{ $child['href'] }}" class="admin-link !py-2 text-[13px]">{{ $child['label'] }}</a>
      @endforeach
     </div>
    </details>
    @endif
    @if(empty($item['children']))
    <a href="{{ $item['href'] }}" class="admin-link"><x-ui.icon :name="$item['icon'] ?? 'dashboard'" class="h-[18px] w-[18px]" /><span>{{ $item['label'] }}</span></a>
    @endif
   @endforeach
   </div>
  </details>
  @endforeach
 </nav>
</aside>
<div id="sidebar-overlay" data-sidebar-close class="fixed inset-0 z-30 hidden bg-slate-950/60 lg:hidden"></div>
<div class="min-w-0"><header class="sticky top-0 z-20 flex items-center justify-between border-b bg-white/90 px-3 py-1.5 backdrop-blur lg:px-6 print:hidden"><div class="flex items-center gap-2.5"><button data-sidebar-open class="rounded-xl border p-2 lg:hidden" aria-label="Buka menu">&#9776;</button><div><p class="text-xs text-slate-500">{{ $cid ? \App\Models\Company::find($cid)?->name : '' }}</p><strong>{{ $title ?? 'Dashboard' }}</strong></div></div><div class="flex items-center gap-3 sm:gap-4">
<button id="global-search-trigger" class="hidden items-center gap-2 rounded-xl border px-3 py-2 text-sm text-slate-500 hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)] md:flex" title="Cari dokumen (Ctrl+K)" aria-label="Cari (Ctrl+K)">🔍<span class="hidden lg:inline">Cari apa saja…</span><kbd class="ml-2 hidden rounded border bg-slate-50 px-1.5 py-0.5 font-mono text-[10px] lg:inline">Ctrl K</kbd></button>
<button id="quick-create-trigger" class="rounded-xl bg-[var(--brand-primary)] px-3 py-2 text-sm font-semibold text-white shadow hover:bg-[var(--brand-primary-hover)] no-print" title="Buat baru" aria-label="Buat baru">＋ <span class="hidden sm:inline">Buat</span></button>
<div id="quick-create-menu" hidden class="absolute right-4 top-full z-30 mt-2 w-64 overflow-hidden rounded-2xl border bg-white shadow-xl"><p class="border-b bg-slate-50 px-4 py-2 text-[11px] font-bold uppercase tracking-widest text-slate-500">Buat Cepat</p><div class="py-1">@foreach(\App\Support\QuickCreate::items(auth()->user(), $cid) as $quick)<a href="{{ $quick['href'] }}" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-sky-50"><x-ui.icon :name="$quick['icon'] ?? 'plus'" class="h-4 w-4 text-[var(--brand-primary)]" />{{ $quick['label'] }}</a>@endforeach</div></div>
<a href="/admin/apps" class="rounded-xl border px-3 py-2 text-sm no-print" title="Semua Aplikasi" aria-label="Semua aplikasi">▦</a><a href="/admin/my-work" class="rounded-xl border px-3 py-2 text-sm no-print" title="Pekerjaan Saya" aria-label="Pekerjaan saya">📋</a><button id="theme-toggle" class="rounded-xl border px-3 py-2 text-sm no-print" title="Ganti tema terang/gelap" aria-label="Ganti tema">🌙</button><a href="/admin/notifications" class="relative rounded-xl border px-3 py-2 text-sm no-print" title="Notifikasi" aria-label="Notifikasi">🔔@php($unread = auth()->user()->unreadNotifications->count())@if($unread > 0)<span class="absolute -top-1.5 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>@endif</a><a href="/admin/my-signature" class="rounded-xl border px-3 py-2 text-sm no-print" title="Tanda Tangan Saya" aria-label="Tanda tangan saya">✍️</a><a href="/docs" class="hidden text-sm no-print xl:inline">Dokumentasi</a><form method="post" action="/logout">@csrf<button class="rounded-xl border px-4 py-2 text-sm font-semibold">Keluar</button></form></div></header>
<div id="breadcrumb-bar" class="flex flex-wrap items-center gap-1 border-b bg-slate-50/80 px-4 py-2 text-xs text-slate-500 backdrop-blur lg:px-8 print:hidden"><a href="/dashboard" class="hover:text-[var(--brand-primary)]">Beranda</a>@isset($breadcrumbs)@foreach($breadcrumbs as $crumb)<span>›</span>@if(isset($crumb['href']) && !$loop->last)<a href="{{ $crumb['href'] }}" class="hover:text-[var(--brand-primary)]">{{ $crumb['label'] }}</a>@else<span class="font-semibold text-slate-700">{{ $crumb['label'] }}</span>@endif @endforeach @endisset </div>
<div id="search-palette" hidden class="fixed inset-0 z-50 flex items-start justify-center bg-slate-950/60 p-4 pt-24 print:hidden"><div class="w-full max-w-2xl overflow-hidden rounded-2xl border bg-white shadow-2xl"><input id="search-input" type="search" placeholder="Cari proyek, tender, PO, billing, NCR, dokumen…" autocomplete="off" class="w-full border-b p-4 text-base outline-none"><div id="search-results" class="max-h-96 overflow-y-auto p-2 text-sm"></div><p class="border-t bg-slate-50 px-4 py-2 text-[11px] text-slate-400">Hasil dibatasi sesuai kewenangan perusahaan Anda · Esc untuk menutup</p></div></div>
<main>{{ $slot }}</main></div>
</div>
@else
<header class="sticky top-0 z-20 border-b bg-white/90 backdrop-blur"><nav class="mx-auto flex max-w-7xl justify-between px-5 py-4"><a href="/" class="flex items-center gap-2 font-black text-sky-800"><span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-sky-500 to-cyan-600 text-sm">ðŸ—ï¸</span>Graha Pondasi ERP</a><div class="flex gap-4"><a href="/docs">Dokumentasi</a><a href="/login">Masuk</a></div></nav></header><main>{{ $slot }}</main>
@endif
<x-ui.toast />
</body></html>
