@props(['columns'])
<div {{ $attributes->merge(['class' => 'grid gap-4 overflow-x-auto pb-4']) }} style="grid-template-columns: repeat({{ count($columns) }}, minmax(240px, 1fr))">
@foreach($columns as $column)
<section class="min-w-[240px] rounded-2xl bg-[var(--surface-muted)] p-3">
<header class="flex items-center justify-between px-1 pb-2"><h3 class="text-xs font-bold uppercase tracking-widest text-slate-500">{{ $column['label'] }}</h3><span class="rounded-full bg-[var(--surface-card)] px-2 py-0.5 text-[11px] font-bold text-slate-500">{{ $column['items']->count() }}</span></header>
<div class="space-y-2">
@forelse($column['items'] as $item)
<a href="{{ $item['href'] }}" class="card-lift block rounded-xl border border-[var(--border-subtle)] bg-[var(--surface-card)] p-3 shadow-[var(--shadow-xs)]"><strong class="block text-sm">{{ $item['title'] }}</strong>@if(isset($item['subtitle']))<span class="mt-0.5 block text-xs text-slate-500">{{ $item['subtitle'] }}</span>@endif @if(isset($item['meta']))<span class="mt-1 inline-block rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase text-slate-500">{{ $item['meta'] }}</span>@endif</a>
@empty
<p class="rounded-xl border border-dashed p-4 text-center text-xs text-slate-400">Kosong</p>
@endforelse
</div>
</section>
@endforeach
</div>
