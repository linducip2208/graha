@props(['title' => null, 'description' => null])
@php($site = \App\Support\PublicSite::resolve())
@php($authed = auth()->check())
<!doctype html>
<html lang="id"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="{{ $description ?? 'ERP multi-company untuk kontraktor pondasi: tender, proyek, bored pile, keuangan, dan mutu dalam satu jejak data ter-audit.' }}">
<meta property="og:title" content="{{ $title ?? $site['system_name'] }}">
<meta property="og:description" content="{{ $description ?? $site['footer_text'] }}">
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ asset('marketing/screens/dashboard-redesign-v2-1440.png') }}">
<meta property="og:image:width" content="1440">
<meta property="og:image:height" content="900">
<meta property="og:image:alt" content="Tampilan dashboard {{ $site['system_name'] }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="{{ asset('marketing/screens/dashboard-redesign-v2-1440.png') }}">
<link rel="canonical" href="{{ url()->current() }}">
<meta name="theme-color" content="#020617">
<link rel="icon" href="{{ $site['logo_url'] ?? asset('favicon-default.svg') }}">
<title>{{ $title ? $title.' — '.$site['system_name'] : $site['system_name'].' · ERP Konstruksi Pondasi & Bored Pile' }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-slate-900 antialiased">
<a href="#main-content" class="skip-link">Lewati ke konten utama</a>
<header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/90 backdrop-blur">
<nav aria-label="Navigasi publik" class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-5">
<a href="/" class="flex min-w-0 items-center gap-2.5">
@if($site['logo_url'])<img src="{{ $site['logo_url'] }}" alt="{{ $site['system_name'] }}" class="h-8 max-w-[160px] object-contain">@else
<span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-sky-600 to-cyan-700 text-sm font-black text-white">{{ mb_substr($site['system_name'], 0, 1) }}</span>
<span class="truncate text-[15px] font-extrabold tracking-tight">{{ $site['system_name'] }}</span>
@endif
</a>
<div class="hidden items-center gap-7 text-sm font-semibold text-slate-600 md:flex">
<a href="/#flow" class="transition hover:text-sky-700">Alur Kerja</a>
<a href="/#modules" class="transition hover:text-sky-700">Modul</a>
<a href="/#foundation" class="transition hover:text-sky-700">Proyek & Pondasi</a>
<a href="/#security" class="transition hover:text-sky-700">Keamanan</a>
<a href="/docs" class="transition hover:text-sky-700">Dokumentasi</a>
</div>
<div class="flex items-center gap-2">
@if($authed)
<a href="/dashboard" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-sky-800">Dashboard</a>
@else
<a href="/login" class="rounded-xl px-3 py-2 text-sm font-bold text-slate-600 transition hover:text-sky-700">Masuk</a>
<a href="{{ $site['cta1_url'] }}" class="rounded-xl bg-sky-700 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-sky-800">{{ $site['cta1_label'] }}</a>
@endif
<details class="dropdown relative md:hidden">
<summary class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-xl border border-slate-200" aria-label="Buka menu"><x-ui.icon name="menu" class="h-5 w-5" /></summary>
<div class="dropdown-panel absolute right-0 top-full z-30 mt-2 w-52 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
<a href="/#flow" class="block px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Alur Kerja</a>
<a href="/#modules" class="block px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Modul</a>
<a href="/#foundation" class="block px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Proyek & Pondasi</a>
<a href="/#security" class="block px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Keamanan</a>
<a href="/docs" class="block px-4 py-2.5 text-sm font-semibold hover:bg-slate-50">Dokumentasi</a>
@if(!$authed)<a href="/login" class="block px-4 py-2.5 text-sm font-bold text-sky-700 hover:bg-slate-50">Masuk</a>@endif
</div>
</details>
</div>
</nav>
</header>
<main id="main-content">{{ $slot }}</main>
<footer class="border-t border-slate-200 bg-slate-50">
<div class="mx-auto grid max-w-6xl gap-10 px-5 py-14 sm:grid-cols-2 lg:grid-cols-4">
<div class="sm:col-span-2">
<div class="flex items-center gap-2.5">
<span class="grid h-9 w-9 place-items-center rounded-xl bg-gradient-to-br from-sky-600 to-cyan-700 text-sm font-black text-white">{{ mb_substr($site['system_name'], 0, 1) }}</span>
<strong class="text-[15px]">{{ $site['system_name'] }}</strong>
</div>
<p class="mt-4 max-w-sm text-sm leading-relaxed text-slate-500">{{ $site['footer_text'] }}</p>
@if($site['support_email'])<p class="mt-3 text-sm text-slate-500">Kontak: <a href="mailto:{{ $site['support_email'] }}" class="font-semibold text-sky-700 hover:underline">{{ $site['support_email'] }}</a></p>@endif
</div>
<div>
<p class="text-xs font-extrabold uppercase tracking-widest text-slate-500">Produk</p>
<ul class="mt-4 space-y-2.5 text-sm text-slate-600">
<li><a href="/#modules" class="hover:text-sky-700">Komersial</a></li>
<li><a href="/#foundation" class="hover:text-sky-700">Proyek & Pondasi</a></li>
<li><a href="/#finance" class="hover:text-sky-700">Keuangan</a></li>
<li><a href="/#qhse" class="hover:text-sky-700">QMS & HSE</a></li>
</ul>
</div>
<div>
<p class="text-xs font-extrabold uppercase tracking-widest text-slate-500">Sistem</p>
<ul class="mt-4 space-y-2.5 text-sm text-slate-600">
<li><a href="/docs" class="hover:text-sky-700">Dokumentasi</a></li>
<li><a href="/login" class="hover:text-sky-700">Masuk</a></li>
<li><a href="/docs#verify" class="hover:text-sky-700">Verifikasi Tanda Tangan</a></li>
</ul>
</div>
</div>
<div class="border-t border-slate-200 py-5 text-center text-xs text-slate-500">© {{ date('Y') }} {{ $site['system_name'] }} · Powered by Laravel</div>
</footer>
</body></html>
