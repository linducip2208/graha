<div class="space-y-1">
@php($allArticles = app(\App\Support\Docs\DocsRegistry::class)->all())
@foreach(\App\Support\Docs\DocsRegistry::CATEGORIES as $key => $label)
@php($items = $allArticles->where('category', $key)->values())
@if($items->isNotEmpty())
<a href="{{ route('docs.category', $key) }}" class="block rounded-lg px-3 py-1.5 font-bold {{ request()->routeIs('docs.category') && request()->route('category') === $key ? 'bg-[var(--brand-primary)] text-white' : 'hover:bg-[var(--surface-muted)]' }}">{{ $label }}</a>
@if(request()->routeIs('docs.article') && request()->route('category') === $key)
<div class="ml-3 border-l pl-2">
@foreach($items as $item)
<a href="{{ route('docs.article', ['category' => $key, 'slug' => $item['slug']]) }}" class="block rounded-lg px-2 py-1 text-slate-500 hover:text-[var(--brand-primary)] {{ request()->route('slug') === $item['slug'] ? 'font-bold text-[var(--brand-primary)]' : '' }}">{{ $item['title'] }}</a>
@endforeach
</div>
@endif
@endif
@endforeach
<a href="{{ route('docs.quick-start') }}" class="mt-2 block rounded-lg px-3 py-1.5 font-bold text-amber-600 hover:bg-amber-50">⚡ Quick Start</a>
</div>
