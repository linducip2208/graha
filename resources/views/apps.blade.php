@php($cfg = $launcherConfig)
<x-layouts.app title="Semua Aplikasi">
<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8" id="app-launcher" data-view-default="{{ $cfg['style'] }}">
<header class="flex flex-wrap items-end justify-between gap-4">
<div>
<p class="text-xs font-bold uppercase tracking-widest text-[var(--brand-primary)]">{{ $company?->company_display_name ?? $company?->code }}</p>
<h1 class="mt-1 text-2xl font-bold tracking-tight">Semua Aplikasi</h1>
<p class="mt-2 max-w-2xl text-sm text-slate-500">Seluruh workspace {{ $company?->name ?? 'ERP' }} sesuai kewenangan Anda. Tandai bintang untuk menyematkan ke favorit.</p>
</div>
<form role="search" class="w-full sm:w-80" onSubmit="return false" aria-label="Cari aplikasi">
<label for="app-search" class="sr-only">Cari aplikasi</label>
<input id="app-search" type="search" placeholder="Cari aplikasi…" autocomplete="off" class="w-full rounded-xl border bg-white px-4 py-2.5 text-sm shadow-sm outline-none transition focus:border-[var(--brand-primary)]">
</form>
</header>

<div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-white px-4 py-3 shadow-sm" role="group" aria-label="Mode tampilan launcher">
<span class="text-xs font-bold uppercase tracking-widest text-slate-500">Tampilan</span>
<div class="flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-100/10">
<button type="button" data-view-btn="visual" class="rounded-lg px-3 py-1.5 text-xs font-bold text-slate-600 transition dark:text-slate-300" aria-pressed="false">▦ Visual</button>
<button type="button" data-view-btn="compact" class="rounded-lg px-3 py-1.5 text-xs font-bold text-slate-600 transition dark:text-slate-300" aria-pressed="false">☷ Compact</button>
<button type="button" data-view-btn="list" class="rounded-lg px-3 py-1.5 text-xs font-bold text-slate-600 transition dark:text-slate-300" aria-pressed="false">≡ List</button>
</div>
</div>

@if($favorites->isNotEmpty())
<div id="launcher-favorites" class="mt-8" data-launcher-section>
<h2 class="text-xs font-bold uppercase tracking-widest text-slate-500">★ Favorit Saya</h2>
<div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
@foreach($favorites as $fav)
<div class="launcher-fav group relative flex items-center justify-between gap-2 rounded-2xl border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" data-search="{{ strtolower($fav->label) }}">
<a href="{{ $fav->href }}" class="min-w-0 flex-1 truncate text-sm font-semibold focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]">{{ $fav->label }}</a>
<button type="button" class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-lg text-amber-500 transition hover:bg-amber-50 dark:hover:bg-amber-950" data-unfavorite="{{ $fav->href }}" aria-label="Hapus {{ $fav->label }} dari favorit">★</button>
</div>
@endforeach
</div>
</div>
@endif

@if($recents->isNotEmpty())
<div id="launcher-recents" class="mt-8" data-launcher-section>
<h2 class="text-xs font-bold uppercase tracking-widest text-slate-500">🕐 Terakhir Dibuka</h2>
<div class="mt-3 flex flex-wrap gap-2">
@foreach($recents as $recent)
<a href="{{ $recent->href }}" class="rounded-full border bg-white px-4 py-2 text-sm shadow-sm transition hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]">{{ $recent->label }}</a>
@endforeach
</div>
</div>
@endif

{{-- ===== WORKSPACE: VISUAL (kartu per workspace, cover 16:9) ===== --}}
<div id="launcher-visual" data-view-pane class="mt-10 space-y-10">
@foreach($workspaces as $ws)
<section data-workspace-section>
<h2 class="border-b pb-2 text-base font-black tracking-tight">{{ $ws['label'] }}</h2>
<div class="mt-4 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
<article class="launcher-card card-lift group overflow-hidden rounded-[var(--radius-card)] border bg-white shadow-[var(--shadow-card)]" data-search="{{ strtolower($ws['label'].' '.$ws['description'].' '.implode(' ', $ws['capabilities'])) }}">
<a href="{{ $ws['href'] }}" class="block focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]" aria-label="Buka workspace {{ $ws['label'] }}">
<div class="relative aspect-[16/9] overflow-hidden bg-gradient-to-br from-slate-800 via-[#101a2c] to-[var(--brand-secondary,#0369A1)]">
@if(!empty($ws['cover']) && ($cfg['covers_enabled'] ?? true))
<img src="{{ asset($ws['cover']) }}" alt="" width="1200" height="675" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover transition duration-200 group-hover:scale-[1.03]" onerror="this.remove()">
@endif
<span class="absolute right-3 top-3 grid h-11 w-11 place-items-center rounded-xl bg-white/15 text-white backdrop-blur-sm"><x-ui.icon :name="$ws['icon']" class="h-5 w-5" /></span>
</div>
</a>
<div class="flex flex-col gap-3 p-5 pt-4">
<div class="flex items-start justify-between gap-3">
<a href="{{ $ws['href'] }}" class="min-w-0 focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]"><strong class="block truncate text-base">{{ $ws['label'] }}</strong></a>
<button type="button" class="launcher-star grid h-11 w-11 shrink-0 place-items-center rounded-full text-xl leading-none text-slate-300 transition hover:bg-amber-50 hover:text-amber-400 dark:hover:bg-amber-950" data-favorite-label="{{ $ws['label'] }}" data-favorite-href="{{ $ws['href'] }}" aria-label="Sematkan {{ $ws['label'] }} ke favorit" aria-pressed="false">☆</button>
</div>
<p class="line-clamp-2 text-xs leading-relaxed text-slate-500">{{ $ws['description'] }}</p>
<div class="mt-auto flex items-end justify-between gap-3 pt-1">
<p class="min-w-0 text-[11px] font-semibold text-slate-400">
@forelse($ws['capabilities'] as $cap)<span class="mr-1 inline-block whitespace-nowrap">{{ $cap }}@if(!$loop->last) ·@endif</span>@empty<span>{{ $ws['items'] }} aplikasi</span>@endforelse
@if($ws['more'] > 0)<span class="whitespace-nowrap">+{{ $ws['more'] }} lainnya</span>@endif
</p>
<a href="{{ $ws['href'] }}" class="shrink-0 rounded-xl border px-4 py-2 text-xs font-bold text-[var(--brand-primary)] transition group-hover:border-[var(--brand-primary)] group-hover:bg-[var(--brand-primary)] group-hover:text-white focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]" aria-label="Buka workspace {{ $ws['label'] }}">Buka →</a>
</div>
</div>
</article>
</div>
</section>
@endforeach
</div>

{{-- ===== WORKSPACE: COMPACT ===== --}}
<div id="launcher-compact" data-view-pane class="mt-10 hidden space-y-8">
@foreach($workspaces as $ws)
<section data-workspace-section>
<h2 class="border-b pb-2 text-base font-black tracking-tight">{{ $ws['label'] }}</h2>
<div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
<a href="{{ $ws['href'] }}" class="launcher-card flex min-h-[72px] items-center gap-3 rounded-2xl border bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:border-[var(--brand-primary)] hover:shadow-md focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]" data-search="{{ strtolower($ws['label']) }}" aria-label="Buka {{ $ws['label'] }}">
<span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-[var(--brand-primary)]"><x-ui.icon :name="$ws['icon']" class="h-5 w-5" /></span>
<span class="min-w-0 text-sm font-semibold leading-snug">{{ $ws['label'] }}</span>
</a>
</div>
</section>
@endforeach
</div>

{{-- ===== SEMUA ITEM + CHILD LINKS: LIST (tidak ada fitur yang hilang) ===== --}}
<div id="launcher-list" data-view-pane class="mt-10 hidden space-y-8">
@foreach($navGroups as $group)
<section data-workspace-section class="overflow-hidden rounded-[var(--radius-card)] border bg-white shadow-[var(--shadow-card)]">
<h2 class="border-b bg-slate-50 px-5 py-3 text-sm font-black uppercase tracking-wide">{{ $group['label'] }}</h2>
<ul class="divide-y">
@foreach($group['items'] as $item)
<li class="launcher-list-item px-5 py-3" data-search="{{ strtolower($item['label']) }}">
<a href="{{ $item['href'] }}" class="flex items-center gap-3 py-1 text-sm font-semibold transition hover:text-[var(--brand-primary)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]"><x-ui.icon :name="$item['icon'] ?? 'dashboard'" class="h-4 w-4 text-[var(--brand-primary)]" />{{ $item['label'] }}</a>
@if(!empty($item['children']))
<ul class="ml-7 mt-1 flex flex-wrap gap-x-4 gap-y-1">
@foreach($item['children'] as $child)
<li><a href="{{ $child['href'] }}" class="text-xs text-slate-500 transition hover:text-[var(--brand-primary)] focus-visible:outline-2 focus-visible:outline-[var(--brand-primary)]">{{ $child['label'] }}</a></li>
@endforeach
</ul>
@endif
</li>
@endforeach
</ul>
</section>
@endforeach
</div>

<div id="launcher-empty" hidden class="mt-16 rounded-3xl border border-dashed bg-white p-12 text-center">
<p class="text-lg font-bold">Tidak ada aplikasi yang cocok.</p>
<p class="mt-1 text-sm text-slate-500">Coba kata kunci lain atau hapus pencarian.</p>
</div>
</section>

<script>
(function () {
  var root = document.getElementById('app-launcher');
  if (! root) return;
  var panes = { visual: document.getElementById('launcher-visual'), compact: document.getElementById('launcher-compact'), list: document.getElementById('launcher-list') };
  var stored = null;
  try { stored = localStorage.getItem('apps.view'); } catch (e) {}
  var view = panes[stored] ? stored : (root.dataset.viewDefault || 'visual');
  var csrf = document.querySelector('meta[name="csrf-token"]');

  function applyView(next) {
    view = next;
    Object.keys(panes).forEach(function (k) {
      if (! panes[k]) return;
      panes[k].classList.toggle('hidden', k !== next);
    });
    document.querySelectorAll('[data-view-btn]').forEach(function (btn) {
      var active = btn.dataset.viewBtn === next;
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
      btn.classList.toggle('bg-white', active);
      btn.classList.toggle('shadow', active);
    });
    try { localStorage.setItem('apps.view', next); } catch (e) {}
    filter();
  }
  document.querySelectorAll('[data-view-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () { applyView(btn.dataset.viewBtn); });
  });

  var query = '';
  document.getElementById('app-search').addEventListener('input', function (e) {
    query = e.target.value.trim().toLowerCase();
    filter();
  });
  function filter() {
    var pane = panes[view];
    if (! pane) return;
    var visibleTotal = 0;
    pane.querySelectorAll('[data-workspace-section]').forEach(function (section) {
      var visible = 0;
      section.querySelectorAll('.launcher-card, .launcher-fav, .launcher-list-item').forEach(function (card) {
        var hay = card.dataset.search || '';
        var show = ! query || hay.indexOf(query) !== -1;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
      });
      section.style.display = visible ? '' : 'none';
      visibleTotal += visible;
    });
    var empty = document.getElementById('launcher-empty');
    if (empty) empty.hidden = visibleTotal > 0;
  }

  // Favorit memakai backend existing /admin/preferences/favorites (toggle).
  function setStar(btn, on) {
    btn.textContent = on ? '★' : '☆';
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    btn.classList.toggle('text-amber-500', on);
    btn.classList.toggle('text-slate-300', ! on);
  }
  document.querySelectorAll('.launcher-star').forEach(function (btn) {
    setStar(btn, Boolean(document.querySelector('#launcher-favorites a[href="' + CSS.escape(btn.dataset.favoriteHref) + '"]')));
    btn.addEventListener('click', function () {
      fetch('/admin/preferences/favorites', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.content : '', 'Accept': 'application/json' },
        body: JSON.stringify({ label: btn.dataset.favoriteLabel, href: btn.dataset.favoriteHref })
      }).then(function (r) { if (! r.ok) throw new Error(); window.location.reload(); }).catch(function () {});
    });
  });
  document.querySelectorAll('[data-unfavorite]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var card = btn.closest('.launcher-fav');
      var label = card ? (card.querySelector('a') || {}).textContent : '';
      fetch('/admin/preferences/favorites', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.content : '', 'Accept': 'application/json' },
        body: JSON.stringify({ label: (label || '').trim(), href: btn.dataset.unfavorite })
      }).then(function (r) { if (! r.ok) throw new Error(); if (card) card.remove(); }).catch(function () {});
    });
  });

  applyView(view);

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var style = document.createElement('style');
    style.textContent = '#app-launcher *{transition:none!important;animation:none!important}';
    document.head.appendChild(style);
  }
})();
</script>
</x-layouts.app>
