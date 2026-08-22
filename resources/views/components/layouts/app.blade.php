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
@php($navGroups = \App\Support\Navigation::groups(auth()->user(), $cid))
<div class="min-h-screen lg:grid lg:grid-cols-[272px_1fr] print:block">
<aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 w-[280px] -translate-x-full overflow-y-auto bg-slate-950 text-slate-200 shadow-2xl transition-transform lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:translate-x-0 print:hidden">
 <div class="flex items-center justify-between border-b border-white/10 px-5 py-5"><a href="/dashboard" class="flex items-center gap-2 font-black text-white"><span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-sky-500 to-cyan-600 text-sm">🏗️</span>Graha Pondasi ERP</a><button data-sidebar-close class="rounded-lg p-2 lg:hidden" aria-label="Tutup menu">✕</button></div>
 <nav id="admin-navigation" class="space-y-6 p-4 text-sm">
  @foreach($navGroups as $group)
  <div>
   <p class="px-3 pb-1 text-[11px] font-bold uppercase tracking-widest text-slate-500">{{ $group['label'] }}</p>
   @foreach($group['items'] as $item)
    @if(! empty($item['children']))
    <details class="nav-details">
     <summary class="admin-link cursor-pointer list-none"><x-ui.icon :name="$item['icon'] ?? 'dashboard'" class="h-[18px] w-[18px]" /><span>{{ $item['label'] }}</span><span class="nav-chevron ml-auto text-[10px] opacity-60">▼</span></summary>
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
  @endforeach
 </nav>
</aside>
<div id="sidebar-overlay" data-sidebar-close class="fixed inset-0 z-30 hidden bg-slate-950/60 lg:hidden"></div>
<div class="min-w-0"><header class="sticky top-0 z-20 flex items-center justify-between border-b bg-white/90 px-4 py-3 backdrop-blur lg:px-8 print:hidden"><div class="flex items-center gap-3"><button data-sidebar-open class="rounded-xl border p-2 lg:hidden" aria-label="Buka menu">☰</button><div><p class="text-xs text-slate-500">{{ $cid ? \App\Models\Company::find($cid)?->name : '' }}</p><strong>{{ $title ?? 'Dashboard' }}</strong></div></div><div class="flex items-center gap-3 sm:gap-4"><button id="theme-toggle" class="rounded-xl border px-3 py-2 text-sm no-print" title="Ganti tema terang/gelap" aria-label="Ganti tema">🌙</button><a href="/admin/notifications" class="relative rounded-xl border px-3 py-2 text-sm no-print" title="Notifikasi" aria-label="Notifikasi">🔔@php($unread = auth()->user()->unreadNotifications->count())@if($unread > 0)<span class="absolute -top-1.5 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>@endif</a><a href="/docs" class="hidden text-sm no-print sm:inline">Dokumentasi</a><form method="post" action="/logout">@csrf<button class="rounded-xl border px-4 py-2 text-sm font-semibold">Keluar</button></form></div></header><main>{{ $slot }}</main></div>
</div>
@else
<header class="sticky top-0 z-20 border-b bg-white/90 backdrop-blur"><nav class="mx-auto flex max-w-7xl justify-between px-5 py-4"><a href="/" class="flex items-center gap-2 font-black text-sky-800"><span class="grid h-8 w-8 place-items-center rounded-lg bg-gradient-to-br from-sky-500 to-cyan-600 text-sm">🏗️</span>Graha Pondasi ERP</a><div class="flex gap-4"><a href="/docs">Dokumentasi</a><a href="/login">Masuk</a></div></nav></header><main>{{ $slot }}</main>
@endif
<x-ui.toast />
</body></html>
