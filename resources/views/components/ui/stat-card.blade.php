<article {{ $attributes->merge(['class' => 'card-lift rounded-2xl border bg-white p-5 shadow-sm print:shadow-none']) }}>
<p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $label }}</p>
<p class="mt-2 text-2xl font-black tabular-nums xl:text-3xl">{{ $value }}</p>
@if(! empty($hint))<p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>@endif
</article>
