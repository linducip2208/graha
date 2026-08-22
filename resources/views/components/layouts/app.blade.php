@php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag)
<!doctype html>
<html lang="id"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="ERP konstruksi pondasi: tender sampai bored pile dalam satu jejak data ter-audit.">
<meta property="og:title" content="{{ $title ?? config('app.name') }}">
<meta property="og:description" content="ERP multi-company untuk kontraktor pondasi: approval berjenjang, jurnal otomatis, audit hash-chain.">
<meta property="og:type" content="website">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏗️</text></svg>">
<title>{{ $title ?? config('app.name') }}</title>
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark'||(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark')}})();</script>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900" data-flash="{{ session('status') }}" data-flash-error="{{ $errors->any() ? $errors->first() : '' }}">
@if(auth()->check())
@php($cid = session('company_id'))
@php($activeCompany = $cid ? \App\Models\Company::find($cid) : null)
@php($navGroups = \App\Support\Navigation::groups(auth()->user(), $cid))
<div class="min-h-screen lg:grid lg:grid-cols-[288px_1fr] print:block">
<aside id="admin-sidebar" class="admin-sidebar fixed inset-y-0 left-0 z-40 flex w-[288px] -translate-x-full flex-col bg-slate-950 text-slate-200 shadow-2xl transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 print:hidden">
 <div class="sidebar-brand flex items-center justify-between px-5 py-5">
  <a href="/dashboard" class="flex min-w-0 items-center gap-3 text-white"><span class="brand-mark"><x-ui.icon name="cube" class="h-5 w-5" /></span><span class="min-w-0"><strong class="block truncate text-sm font-black tracking-tight">Graha Pondasi</strong><span class="block text-[10px] font-bold uppercase tracking-[.2em] text-sky-300">Enterprise ERP</span></span></a>
  <button data-sidebar-close class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" aria-label="Tutup menu">✕</button>
 </div>
 @if($activeCompany)<div class="mx-4 mt-4 rounded-xl border border-white/10 bg-white/[.04] px-3 py-2.5"><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Perusahaan aktif</span><span class="mt-0.5 block truncate text-xs font-semibold text-slate-200">{{ $activeCompany->name }}</span></div>@endif
 <nav id="admin-navigation" class="sidebar-navigation flex-1 overflow-y-auto px-3 pb-5 pt-4 text-sm" aria-label="Navigasi utama">
  @foreach($navGroups as $group)
  <section class="nav-group" data-nav-group="{{ $group['key'] }}">
   <h2 class="nav-group-label">{{ $group['label'] }}</h2>
   @foreach($group['items'] as $item)
    @if(! empty($item['children']))
    <details class="nav-details" data-nav-key="{{ $group['key'] }}-{{ str($item['label'])->slug() }}" @if($item['expanded']) open @endif>
     <summary @class(['admin-link cursor-pointer list-none', 'active' => $item['expanded']]) aria-label="Buka submenu {{ $item['label'] }}"><x-ui.icon :name="$item['icon'] ?? 'dashboard'" class="h-[18px] w-[18px]" /><span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span><span class="nav-chevron" aria-hidden="true">⌄</span></summary>
     <div class="nav-children">
      @foreach($item['children'] as $child)
      <a href="{{ $child['href'] }}" @class(['nav-child-link', 'active' => $child['active']]) @if($child['active']) aria-current="page" @endif><span class="nav-child-dot" aria-hidden="true"></span><span>{{ $child['label'] }}</span></a>
      @endforeach
     </div>
    </details>
    @else
    <a href="{{ $item['href'] }}" @class(['admin-link', 'active' => $item['active']]) @if($item['active']) aria-current="page" @endif><x-ui.icon :name="$item['icon'] ?? 'dashboard'" class="h-[18px] w-[18px]" /><span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span></a>
    @endif
   @endforeach
  </section>
  @endforeach
 </nav>
 <div class="sidebar-user border-t border-white/10 p-4"><div class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-sky-500/15 text-xs font-black text-sky-300">{{ str(auth()->user()->name)->substr(0, 2)->upper() }}</span><span class="min-w-0"><strong class="block truncate text-xs text-white">{{ auth()->user()->name }}</strong><span class="block truncate text-[11px] text-slate-500">{{ auth()->user()->email }}</span></span></div></div>
</aside>
<div id="sidebar-overlay" data-sidebar-close class="fixed inset-0 z-30 hidden bg-slate-950/60 lg:hidden"></div>
<div class="min-w-0"><header class="sticky top-0 z-20 flex items-center justify-between border-b bg-white/90 px-4 py-3 backdrop-blur lg:px-8 print:hidden"><div class="flex min-w-0 items-center gap-3"><button data-sidebar-open class="rounded-xl border p-2 lg:hidden" aria-label="Buka menu">☰</button><div class="min-w-0"><p class="truncate text-xs text-slate-500">{{ $activeCompany?->name }}</p><strong class="block truncate">{{ $title ?? 'Dashboard' }}</strong></div></div><div class="flex items-center gap-2 sm:gap-4"><button id="theme-toggle" class="rounded-xl border px-3 py-2 text-sm no-print" title="Ganti tema terang/gelap" aria-label="Ganti tema">🌙</button><a href="/admin/notifications" class="relative rounded-xl border px-3 py-2 text-sm no-print" title="Notifikasi" aria-label="Notifikasi">🔔@php($unread = auth()->user()->unreadNotifications->count())@if($unread > 0)<span class="absolute -top-1.5 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>@endif</a><a href="/docs" class="hidden text-sm no-print xl:inline">Dokumentasi</a><form method="post" action="/logout">@csrf<button class="rounded-xl border px-3 py-2 text-sm font-semibold sm:px-4">Keluar</button></form></div></header><main>{{ $slot }}</main></div>
</div>
@else
<header class="sticky top-0 z-20 border-b bg-white/90 backdrop-blur"><nav class="mx-auto flex max-w-7xl justify-between px-5 py-4"><a href="/" class="flex items-center gap-2 font-black text-sky-800"><span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-sky-500 to-cyan-600 text-sm">🏗️</span>Graha Pondasi ERP</a><div class="flex gap-4"><a href="/docs">Dokumentasi</a><a href="/login">Masuk</a></div></nav></header><main>{{ $slot }}</main>
@endif
<x-ui.toast />
</body></html>
