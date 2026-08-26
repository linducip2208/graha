@props(['keyName', 'alt' => '', 'caption' => null])
@php($manifest = \App\Support\Docs\DocsScreenshotManifest::resolve($keyName))
@if($manifest)
<figure class="docs-shot my-6">
<button type="button" class="block w-full cursor-zoom-in rounded-2xl border bg-white p-2 shadow-sm" title="Klik untuk perbesar">
<img src="{{ route('docs.assets', $manifest['path']) }}" alt="{{ $alt ?: ($manifest['alt'] ?? $keyName) }}"
     loading="lazy" decoding="async"
     @if(!empty($manifest['width'])) width="{{ $manifest['width'] }}" @endif
     @if(!empty($manifest['height'])) height="{{ $manifest['height'] }}" @endif
     class="mx-auto max-w-full rounded-xl">
</button>
<figcaption class="mt-2 text-center text-xs text-slate-500">{{ $caption ?? $manifest['caption'] ?? '' }}@if($caption === null && !isset($manifest['caption'])) Screenshot: {{ str($keyName)->replace('-', ' ')->title() }}@endif</figcaption>
</figure>
@else
<div class="my-6 rounded-2xl border border-dashed p-6 text-center text-xs text-slate-400" role="status">
📷 Screenshot <strong>{{ $keyName }}</strong> belum tersedia — jalankan <code>php artisan docs:capture --only={{ $keyName }}</code>.
</div>
@endif
