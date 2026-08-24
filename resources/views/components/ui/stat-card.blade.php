@props(['label' => null, 'value' => null, 'hint' => '', 'icon' => null, 'tone' => 'brand', 'delta' => null, 'valueClass' => 'text-2xl xl:text-[28px]'])
@php($tones = [
    'brand' => 'bg-[color-mix(in_srgb,var(--brand-primary)_10%,white)] text-[var(--brand-primary)] dark:bg-[color-mix(in_srgb,var(--brand-primary)_22%,#101a2c)]',
    'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    'danger' => 'bg-red-50 text-red-700 dark:bg-red-950/60 dark:text-red-300',
    'info' => 'bg-sky-50 text-[var(--brand-primary)] dark:bg-sky-950/60 dark:text-sky-300',
    'violet' => 'bg-violet-50 text-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
])
@php($toneClass = $tones[$tone] ?? $tones['brand'])
<article {{ $attributes->merge(['class' => 'card-lift rounded-[var(--radius-card)] border bg-white p-5 shadow-[var(--shadow-card)] print:shadow-none']) }}>
<div class="flex items-start justify-between gap-3">
<p class="text-[11px] font-bold uppercase tracking-widest text-slate-500">{{ $label }}</p>
@if($icon)<span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $toneClass }}"><x-ui.icon :name="$icon" class="h-[18px] w-[18px]" /></span>@endif
</div>
<p class="mt-2 font-black tabular-nums tracking-tight {{ $valueClass }}">{{ $value }}</p>
@if($delta)<p class="mt-1 text-xs font-bold {{ str_starts_with($delta, '-') ? 'text-red-600' : 'text-emerald-700' }}">{{ $delta }}</p>@endif
@if(! empty($hint))<p class="mt-1 text-xs leading-relaxed text-slate-400">{{ $hint }}</p>@endif
</article>
