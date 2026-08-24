@props(['title', 'subtitle' => null, 'status' => null])
<header {{ $attributes->merge(['class' => 'flex flex-wrap items-start justify-between gap-3']) }}>
<div class="min-w-0">
<h1 class="text-2xl font-bold tracking-tight">{{ $title }}</h1>
@if(filled($subtitle))<p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>@endif
@if(filled($status))<span class="mt-2 inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase text-slate-700 dark:bg-[#16233c] dark:text-slate-200">{{ $status }}</span>@endif
</div>
@if(isset($actions))<div class="flex flex-wrap gap-2 no-print">{{ $actions }}</div>@endif
</header>
