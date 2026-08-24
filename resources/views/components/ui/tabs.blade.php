@props(['tabs', 'active'])
<nav {{ $attributes->merge(['class' => 'workspace-toolbar no-print']) }}>
@foreach($tabs as $key => $label)
<a href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}" @class(['tab-link', 'active' => $active === $key])>{{ $label }}</a>
@endforeach
</nav>
