@props(['colors' => [], 'flip' => false])
@php($c = array_pad(array_slice($colors, 0, 3), 3, null))
<div {{ $attributes->merge(['class' => 'strata-divider'.($flip ? ' strata-flip' : '')]) }} aria-hidden="true">
    <svg viewBox="0 0 1440 72" preserveAspectRatio="none">
        <path d="M0 10C180 4 340 18 560 10S960 2 1160 12 1390 16 1440 8L1440 72 0 72Z" fill="{{ $c[0] }}"/>
        <path d="M0 38C220 30 420 46 660 38S1060 30 1260 40 1400 34 1440 38L1440 72 0 72Z" fill="{{ $c[1] }}"/>
        <path d="M0 58C240 52 480 64 740 58S1160 52 1440 60L1440 72 0 72Z" fill="{{ $c[2] }}"/>
    </svg>
</div>
