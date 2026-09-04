@props(['label' => null, 'bodyClass' => 'p-5', 'variant' => 'default'])
@php($hover = $variant === 'interactive' ? 'transition duration-200 hover:-translate-y-0.5 hover:shadow-[var(--shadow-card-hover)]' : '')
<article {{ $attributes->merge(['class' => "ui-card rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)] print:shadow-none {$hover}"]) }}>
@if(filled($label))<h2 class="border-b border-[var(--border-subtle)] px-5 py-3.5 text-sm font-extrabold tracking-tight">{{ $label }}</h2>@endif
<div class="{{ $bodyClass }}">{{ $slot }}</div>
</article>
