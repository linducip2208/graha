<x-layouts.app title="Semua Aplikasi">
<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<p class="text-xs font-bold uppercase tracking-widest text-sky-700">{{ $company?->code }}</p>
<h1 class="mt-1 text-2xl font-bold tracking-tight">Semua Aplikasi</h1>
<p class="mt-2 text-slate-500">Seluruh workspace {{ $company?->name ?? 'ERP' }} sesuai kewenangan Anda. Tandai bintang untuk menyematkan ke favorit.</p>

@if($favorites->isNotEmpty())
<div class="mt-8" id="launcher-favorites">
<h2 class="text-xs font-bold uppercase tracking-widest text-slate-500">★ Favorit Saya</h2>
<div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
@foreach($favorites as $fav)
<a href="{{ $fav->href }}" class="card-lift group flex items-center justify-between gap-2 rounded-2xl border bg-white p-4 shadow-sm"><span class="min-w-0 truncate text-sm font-semibold">{{ $fav->label }}</span><span class="text-amber-500">★</span></a>
@endforeach
</div>
</div>
@endif

@if($recents->isNotEmpty())
<div class="mt-8">
<h2 class="text-xs font-bold uppercase tracking-widest text-slate-500">🕐 Terakhir Dilihat</h2>
<div class="mt-3 flex flex-wrap gap-2">
@foreach($recents as $recent)
<a href="{{ $recent->href }}" class="rounded-full border bg-white px-4 py-1.5 text-sm hover:border-sky-600 hover:text-sky-700">{{ $recent->label }}</a>
@endforeach
</div>
</div>
@endif

<div class="mt-10 space-y-10">
@foreach($workspaces as $workspace)
<section>
<h2 class="border-b pb-2 text-lg font-black">{{ $workspace['label'] }}</h2>
<div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
@foreach($workspace['items'] as $item)
<a href="{{ $item['href'] }}" class="card-lift rounded-2xl border bg-white p-5 shadow-sm">
<span class="flex items-start gap-3">
<span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-50 text-sky-700 dark:!bg-sky-950"><x-ui.icon :name="$item['icon'] ?? 'dashboard'" /></span>
<span class="min-w-0"><strong class="block truncate">{{ $item['label'] }}</strong>
@if(! empty($item['children']))<span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ collect($item['children'])->pluck('label')->implode(' · ') }}</span>@endif
</span></span>
</a>
@endforeach
</div>
</section>
@endforeach
</div>
</section>
</x-layouts.app>
