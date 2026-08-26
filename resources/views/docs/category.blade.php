@extends('docs.layout')
@section('title', $categoryLabel)
@section('content')
<p class="text-xs font-bold uppercase tracking-widest text-[var(--brand-primary)]"><a href="/docs">Dokumentasi</a> / {{ $categoryLabel }}</p>
<h1 class="mt-1 text-3xl font-black">{{ $categoryLabel }}</h1>
<div class="mt-6 grid gap-4 sm:grid-cols-2">
@foreach($articles as $item)
<a href="{{ route('docs.article', ['category' => $category, 'slug' => $item['slug']]) }}" class="card-lift rounded-2xl border bg-white p-5 dark:bg-[#0f1a2e]">
<h2 class="font-extrabold group-hover:text-[var(--brand-primary)]">{{ $item['title'] }}</h2>
<p class="mt-1 text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($item['description'], 140) }}</p>
</a>
@endforeach
</div>
@endsection
