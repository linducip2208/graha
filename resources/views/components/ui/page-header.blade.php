@props(['title', 'subtitle' => null, 'status' => null, 'docs' => null])
<header {{ $attributes->merge(['class' => 'flex flex-wrap items-start justify-between gap-3']) }}>
<div class="min-w-0">
<h1 class="text-2xl font-bold tracking-tight">{{ $title }}</h1>
@if(filled($subtitle))<p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>@endif
@if(filled($status))<span class="mt-2 inline-block rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase text-slate-700 dark:bg-[#16233c] dark:text-slate-200">{{ $status }}</span>@endif
</div>
<div class="flex flex-wrap gap-2 no-print">
@if(filled($docs))<a href="{{ $docs }}" target="_blank" rel="noopener" title="Buka dokumentasi fitur ini" class="inline-flex min-h-[38px] items-center gap-1.5 rounded-xl border px-3 text-xs font-bold text-slate-500 hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]">? Bantuan</a>@endif
@if(isset($actions)){{ $actions }}@endif
</div>
</header>
