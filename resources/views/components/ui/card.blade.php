@props(['label' => null, 'bodyClass' => 'p-5'])
<article {{ $attributes->merge(['class' => 'rounded-2xl border bg-white shadow-sm print:shadow-none']) }}>
@if(filled($label))<h2 class="border-b border-slate-100 px-5 py-3 font-bold dark:border-[#22304d]">{{ $label }}</h2>@endif
<div class="{{ $bodyClass }}">{{ $slot }}</div>
</article>
