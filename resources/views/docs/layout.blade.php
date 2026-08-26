{{-- Layout Documentation Center (P9/P37): sidebar kiri, artikel tengah, TOC kanan. --}}
@php($brand = auth()->user() && session('company_id') ? app(\App\Services\ThemeService::class)->resolve((int) session('company_id')) : ['primary' => '#4338ca'])
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'Dokumentasi') — {{ config('app.name') }}</title>
@yield('meta')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource-variable/inter@5.0.16/index.min.css">
<style>
:root { --brand-primary: {{ $brand['primary'] ?? '#4338ca' }}; }
body { font-family: 'Inter Variable', Inter, system-ui, sans-serif; }
.docs-body h2 { border-bottom: 1px solid rgb(0 0 0 / .06); padding-bottom: .35rem; }
.dark .docs-body h2 { border-color: rgb(255 255 255 / .08); }
.docs-list { margin: .75rem 0; padding-left: 1.25rem; list-style: disc; }
ol.docs-list { list-style: decimal; }
.docs-list li { margin-bottom: .35rem; line-height: 1.6; }
.docs-body p { margin: .65rem 0; line-height: 1.7; }
</style>
<script src="https://cdn.tailwindcss.com?plugins=typography"></script>
<script>tailwind.config = { darkMode: 'class' }</script>
</head>
<body class="bg-slate-50 text-slate-900 antialiased dark:bg-[#0b1424] dark:text-slate-100">
<header class="sticky top-0 z-40 border-b bg-white/90 backdrop-blur dark:bg-[#0b1424]/90">
<div class="mx-auto flex max-w-[1400px] items-center gap-4 px-4 py-3">
<a href="/docs" class="flex items-center gap-2 font-black tracking-tight"><span class="grid h-8 w-8 place-items-center rounded-lg text-white" style="background:var(--brand-primary)">?</span> Dokumentasi <span class="hidden text-xs font-semibold text-slate-400 sm:inline">{{ config('app.name') }}</span></a>
<form action="{{ route('docs.search') }}" method="get" class="ml-auto w-full max-w-sm"><input type="search" name="q" value="{{ request('q') }}" placeholder="Cari dokumentasi… (tekan /)" aria-label="Cari dokumentasi" class="w-full rounded-xl border px-4 py-2 text-sm focus:border-[var(--brand-primary)] focus:outline-none"></form>
<details class="relative md:hidden"><summary class="cursor-pointer rounded-lg border p-2 text-sm font-bold">☰</summary><div class="absolute right-0 mt-2 w-56 rounded-xl border bg-white p-2 shadow-xl dark:bg-[#0b1424]">@include('docs.partials.sidebar-tree')</div></details>
</div>
</header>
<main class="mx-auto grid max-w-[1400px] gap-8 px-4 py-8 lg:grid-cols-[240px_minmax(0,1fr)] xl:grid-cols-[240px_minmax(0,1fr)_220px]">
<nav class="hidden lg:block" aria-label="Navigasi dokumen">
<div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto pr-2 text-sm">
@include('docs.partials.sidebar-tree')
</div>
</nav>
<article class="min-w-0">@yield('content')</article>
<aside class="hidden xl:block" aria-label="Daftar isi">
<div class="sticky top-20 max-h-[calc(100vh-6rem)] overflow-y-auto text-xs">
<h2 class="mb-2 font-bold uppercase tracking-wide text-slate-400">Di halaman ini</h2>
@yield('toc', '<p class="text-slate-400">—</p>')
</div>
</aside>
</main>
<footer class="border-t py-6 text-center text-xs text-slate-400">© {{ date('Y') }} {{ config('app.name') }} · Dokumentasi diperbarui berkala · <a href="/" class="hover:underline">Beranda</a></footer>
<script>
// Smooth scroll TOC + highlight sederhana.
document.querySelectorAll('a[href^="#"]').forEach(a => a.addEventListener('click', e => {
    const t = document.querySelector(a.getAttribute('href'));
    if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); }
}));
// Keyboard shortcut "/" fokus search (P8).
document.addEventListener('keydown', e => {
    if (e.key === '/' && ! ['INPUT','TEXTAREA'].includes(document.activeElement.tagName)) {
        const i = document.querySelector('input[type=search]'); if (i) { e.preventDefault(); i.focus(); }
    }
});
// Lightbox screenshot (P38).
document.addEventListener('click', e => {
    const img = e.target.closest('.docs-shot img'); if (! img) return;
    const ov = document.createElement('div');
    ov.className = 'fixed inset-0 z-50 grid place-items-center bg-black/80 p-8 cursor-zoom-out';
    ov.innerHTML = '<img src="'+img.src+'" class="max-h-full max-w-full rounded-xl shadow-2xl">';
    ov.addEventListener('click', () => ov.remove());
    document.body.appendChild(ov);
});
</script>
@stack('scripts')
</body>
</html>
