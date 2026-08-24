@props(['title' => null, 'description' => null])
<div {{ $attributes->merge(['class' => 'rounded-[var(--radius-card)] border bg-white p-5 shadow-[var(--shadow-card)] dark:bg-[#101a2c]']) }}>
@if(filled($title))
<div class="mb-4 border-b border-slate-100 pb-3 dark:border-[#22304d]">
<h3 class="form-section-title">{{ $title }}</h3>
@if(filled($description))<p class="mt-1 text-xs text-slate-500">{{ $description }}</p>@endif
</div>
@endif
{{ $slot }}
</div>
