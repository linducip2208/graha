@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false])
<label {{ $attributes->merge(['class' => 'block']) }}>
@if(filled($label))<span class="mb-1 block text-xs font-bold text-slate-600 dark:text-slate-300">{{ $label }}@if($required)<span class="text-red-500"> *</span>@endif</span>@endif
{{ $slot }}
@if(filled($hint))<span class="help-text block">{{ $hint }}</span>@endif
@if($name && $errors->has($name))<span class="error-text block">{{ $errors->first($name) }}</span>@endif
</label>
