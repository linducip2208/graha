<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-[var(--border-default)] bg-[var(--surface-card)] p-8 text-center']) }}>
<div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-400"><x-ui.icon :name="$icon ?? 'archive'" class="h-6 w-6" /></div>
<p class="mt-3 font-bold">{{ $title }}</p>
@if(! empty($description))<p class="mx-auto mt-1 max-w-md text-sm text-slate-500">{{ $description }}</p>@endif
@if(isset($slot) && trim($slot))<div class="mt-4">{{ $slot }}</div>@endif
</div>
