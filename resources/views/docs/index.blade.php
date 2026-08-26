@extends('docs.layout')
@section('title', 'Dokumentasi')
@section('content')
@if($total === 0)
<div class="rounded-2xl border border-amber-300 bg-amber-50 p-6 text-sm text-amber-900">
<p class="font-bold">Dokumentasi tidak ditemukan.</p>
<p class="mt-1">Jalankan <code class="rounded bg-white/60 px-1.5 py-0.5 font-mono">php artisan docs:audit</code> untuk memeriksa registry artikel.</p>
</div>
@else
<h1 class="text-3xl font-black tracking-tight">Documentation Center</h1>
<p class="mt-2 max-w-2xl text-slate-500">Pelajari setiap fitur langkah demi langkah — lengkap dengan screenshot dari data demo, panduan per role, dan tombol langsung ke fitur. Total {{ $total }} artikel.</p>
<a href="{{ route('docs.quick-start') }}" class="mt-4 inline-flex min-h-[42px] items-center gap-2 rounded-xl px-5 text-sm font-bold text-white" style="background:var(--brand-primary)">⚡ Mulai dalam 10 menit — Quick Start</a>

<form action="{{ route('docs.search') }}" method="get" class="mt-6 flex gap-2 max-w-md"><input name="q" placeholder="Cari: NCR, journal, pile passport…" class="w-full rounded-xl border p-3 text-sm"><button class="rounded-xl bg-slate-900 px-4 text-sm font-bold text-white">Cari</button></form>

<div class="mt-8 space-y-8">
@foreach($grouped as $group)
<section>
<h2 class="text-lg font-extrabold">{{ $group['label'] }} <span class="ml-1 text-xs font-semibold text-slate-400">{{ $group['items']->count() }}</span></h2>
<div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
@foreach($group['items'] as $item)
<a href="{{ route('docs.article', ['category' => $item['category'], 'slug' => $item['slug']]) }}" class="card-lift group rounded-2xl border bg-white p-4 dark:bg-[#0f1a2e]">
<h3 class="font-bold group-hover:text-[var(--brand-primary)]">{{ $item['title'] }}</h3>
<p class="mt-1 line-clamp-2 text-xs leading-relaxed text-slate-500">{{ \Illuminate\Support\Str::limit($item['description'], 140) }}</p>
@if($item['role_tags'])<div class="mt-2 flex flex-wrap gap-1">@foreach(array_slice($item['role_tags'],0,3) as $rt)<span class="rounded-md bg-[var(--surface-muted)] px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-500">{{ $rt }}</span>@endforeach</div>@endif
</a>
@endforeach
</div>
</section>
@endforeach
</div>
@endif
@endsection
