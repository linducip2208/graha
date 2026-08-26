@extends('docs.layout')
@section('title', $article['title'])
@section('meta')
<meta name="description" content="{{ \Illuminate\Support\Str::limit($article['description'], 150) }}">
@if($article['visibility'] !== 'public')<meta name="robots" content="noindex">@endif
@endsection
@section('content')
<p class="text-xs font-bold uppercase tracking-widest text-[var(--brand-primary)]"><a href="/docs">Dokumentasi</a> @if($article['category'] !== '_special') / <a href="{{ route('docs.category', $article['category']) }}">{{ $article['category_label'] }}</a>@endif</p>
<h1 class="mt-1 text-3xl font-black tracking-tight">{{ $article['title'] }}</h1>
<p class="mt-2 text-slate-500">{{ $article['description'] }}</p>

<div class="mt-4 flex flex-wrap items-center gap-3 text-xs">
@if($article['feature_route'])
@php($featureUrl = \App\Support\Docs\DocsRegistry::resolveFeatureUrl($article))
@if($featureUrl)
<a href="{{ $featureUrl }}" class="btn-brand inline-flex min-h-[40px] items-center rounded-xl px-4 font-bold text-white shadow-sm">Buka Fitur →</a>
@endif
@endif
@if($article['role_tags'])<span class="text-slate-400">Untuk: <strong class="text-slate-600">{{ implode(', ', $article['role_tags']) }}</strong></span>@endif
<span class="text-slate-400">Diperbarui {{ \Carbon\Carbon::parse($article['updated_at'])->translatedFormat('d M Y') }}</span>
</div>

<div class="docs-body mt-8">{!! $html !!}</div>

@if($related->isNotEmpty())
<h2 class="mt-10 text-lg font-extrabold">Fitur terkait</h2>
<div class="mt-3 grid gap-3 sm:grid-cols-3">
@foreach($related as $rel)
<a href="{{ route('docs.article', ['category' => $rel['category'], 'slug' => $rel['slug']]) }}" class="rounded-xl border p-3 text-sm font-semibold hover:border-[var(--brand-primary)]">{{ $rel['title'] }}</a>
@endforeach
</div>
@endif
@endsection
@section('toc')
@foreach($toc as $item)
<a href="#{{ $item['anchor'] }}" class="block border-l-2 py-1 pl-3 {{ $item['level'] === 2 ? 'font-semibold' : 'pl-5 text-slate-400' }} hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]">{{ $item['text'] }}</a>
@endforeach
@endsection
