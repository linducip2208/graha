@props(['variant' => 'primary', 'type' => 'submit', 'href' => null])
@php($base = 'inline-flex items-center justify-center gap-1.5 rounded-xl px-4 py-2 text-sm font-bold transition disabled:opacity-40')
@php($tone = match ($variant) {
    'danger' => 'btn-danger',
    'secondary' => 'border bg-white text-slate-700 hover:border-sky-500 dark:bg-[#16233c] dark:text-slate-200',
    'ghost' => 'text-sky-700 hover:bg-sky-50',
    default => 'btn-brand',
})
@if($href)<a href="{{ $href }}" {{ $attributes->merge(['class' => "$base $tone"]) }}>{{ $slot }}</a>
@else<button {{ $attributes->merge(['type' => $type, 'class' => "$base $tone"]) }}>{{ $slot }}</button>@endif
