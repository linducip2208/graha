@props(['label' => null, 'value' => null, 'hint' => '', 'icon' => null, 'tone' => 'brand', 'delta' => null, 'valueClass' => 'text-[26px] leading-tight'])
@php($tones = [
    'brand' => 'bg-[color-mix(in_srgb,var(--brand-primary)_10%,white)] text-[var(--brand-primary)] dark:bg-[color-mix(in_srgb,var(--brand-primary)_24%,#101a2c)]',
    'success' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-300',
    'warning' => 'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-300',
    'danger' => 'bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-300',
    'info' => 'bg-sky-50 text-sky-600 dark:bg-sky-950/60 dark:text-sky-300',
    'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-300',
])
@php($toneCard = [
    'brand' => 'stat-card-brand',
    'success' => 'stat-card-success',
    'warning' => 'stat-card-warning',
    'danger' => 'stat-card-danger',
    'info' => 'stat-card-info',
    'violet' => 'stat-card-violet',
][$tone] ?? 'stat-card-brand')
@php($toneClass = $tones[$tone] ?? $tones['brand'])
<article {{ $attributes->merge(['class' => "stat-card {$toneCard} rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-xs)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[var(--shadow-card-hover)] print:shadow-none"]) }}>
@if($icon)<span class="mb-3 grid h-10 w-10 place-items-center rounded-xl {{ $toneClass }}"><x-ui.icon :name="$icon" class="h-5 w-5" /></span>@endif
@if(filled($label))<p class="text-[13px] font-semibold text-[var(--text-secondary)]">{{ $label }}</p>@endif
<p class="mt-1 font-extrabold tabular-nums tracking-tight {{ $valueClass }}">{{ $value }}</p>
@if($delta)<p class="mt-1.5 inline-flex items-center gap-1 text-xs font-bold {{ str_starts_with($delta, '-') ? 'text-red-600' : 'text-emerald-600' }}">{{ $delta }}</p>@endif
@if(! empty($hint))<p class="mt-1 text-xs leading-relaxed text-[var(--text-muted)]">{{ $hint }}</p>@endif
</article>
