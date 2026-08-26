@extends('docs.layout')
@section('title', 'Hasil pencarian: '.$q)
@section('content')
<h1 class="text-2xl font-black">Hasil pencarian <span class="text-[var(--brand-primary)]">“{{ $q }}”</span></h1>
<form action="{{ route('docs.search') }}" method="get" class="mt-4 flex max-w-md gap-2"><input name="q" value="{{ $q }}" class="w-full rounded-xl border p-3 text-sm"><button class="rounded-xl bg-slate-900 px-4 text-sm font-bold text-white">Cari</button></form>
@forelse($results as $categoryKey => $items)
<h2 class="mt-8 text-sm font-bold uppercase tracking-wide text-slate-400">{{ \App\Support\Docs\DocsRegistry::CATEGORIES[$categoryKey] ?? $categoryKey }}</h2>
<div class="mt-2 space-y-2">
@foreach($items as $item)
<a href="{{ route('docs.article', ['category' => $item['category'], 'slug' => $item['slug']]) }}" class="block rounded-xl border bg-white p-3 text-sm hover:border-[var(--brand-primary)] dark:bg-[#0f1a2e]">
<strong>{{ $item['title'] }}</strong> — <span class="text-slate-500">{{ \Illuminate\Support\Str::limit($item['description'], 110) }}</span>
</a>
@endforeach
</div>
@empty
<p class="mt-6 rounded-xl border bg-white p-5 text-slate-500">Tidak ditemukan. Coba kata kunci lain, mis. “billing”, “NCR”, “pile”.</p>
@endforelse
@endsection
