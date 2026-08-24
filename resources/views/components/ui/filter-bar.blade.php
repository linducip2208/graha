@props(['action' => null, 'chips' => []])
<div class="no-print">
<form method="get" action="{{ $action ?? url()->current() }}" class="flex flex-wrap items-center gap-2" {{ $attributes->except('class')->merge(['class' => $attributes->get('class') ?? '']) }}>
{{ $slot }}
</form>
@php($activeChips = collect(request()->query())->only(array_merge(['q', 'status', 'project', 'lot'], is_array($chips) ? $chips : []))->filter(fn ($v) => filled($v)))
@if($activeChips->isNotEmpty())
<div class="mt-2 flex flex-wrap gap-1.5">
@foreach($activeChips as $key => $value)
<a href="{{ request()->fullUrlWithQuery([$key => null]) }}" class="chip chip-active" title="Hapus filter">&times; {{ ucfirst($key) }}: {{ \Illuminate\Support\Str::limit((string) $value, 24) }}</a>
@endforeach
</div>
@endif
</div>
