<x-layouts.public title="Verifikasi Tanda Tangan Digital" description="Verifikasi keaslian tanda tangan digital dokumen: integritas hash, versi, penandatangan, dan waktu.">
@php($valid = (bool) ($result['valid'] ?? false))
@php($checks = $result['checks'] ?? [])
@php($version = $result['version'] ?? null)
@php($document = $version?->document)
<div class="mx-auto max-w-2xl px-5 py-16">
<article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="flex items-center gap-4 border-b border-slate-200 p-6 {{ $valid ? 'bg-emerald-50' : 'bg-red-50' }}">
<span class="grid h-12 w-12 shrink-0 place-items-center rounded-full {{ $valid ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
<x-ui.icon :name="$valid ? 'check' : 'triangle-alert'" class="h-6 w-6" />
</span>
<div class="min-w-0">
<p class="text-lg font-black">{{ $valid ? 'Tanda Tangan TERVERIFIKASI' : 'GAGAL VERIFIKASI' }}</p>
<p class="mt-0.5 text-sm {{ $valid ? 'text-emerald-700' : 'text-red-700' }}">{{ $valid ? 'Integritas dokumen terjaga — hash cocok dengan catatan audit.' : 'Satu atau lebih pemeriksaan integritas tidak terpenuhi.' }}</p>
</div>
</div>
<dl class="grid gap-x-6 gap-y-4 p-6 text-sm sm:grid-cols-[180px_1fr]">
@if($document)<dt class="font-bold text-slate-500">Dokumen</dt><dd class="break-words"><span class="font-mono text-xs">{{ $document->number }}</span> · {{ $document->title }}</dd>@endif
@if($version)<dt class="font-bold text-slate-500">Versi</dt><dd class="font-mono">v{{ $version->version }} (Rev. {{ $version->revision }})</dd>@endif
<dt class="font-bold text-slate-500">Penandatangan</dt><dd>{{ $signature->signer_name }}@if($signature->signer_position)<span class="text-slate-400"> · {{ $signature->signer_position }}</span>@endif</dd>
<dt class="font-bold text-slate-500">Jenis TTD</dt><dd class="uppercase">{{ str_replace('_', ' ', $signature->signature_type) }}</dd>
@if($signature->signed_at ?? null)<dt class="font-bold text-slate-500">Ditandatangani</dt><dd>{{ $signature->signed_at->format('d M Y H:i').' WIB' }}</dd>@endif
<dt class="font-bold text-slate-500">Signed Hash (SHA-256)</dt><dd class="break-all font-mono text-xs">{{ $signature->signed_hash }}</dd>
<dt class="font-bold text-slate-500">Pemeriksaan Integritas</dt>
<dd>
<ul class="space-y-1.5">
@foreach(['version_found' => 'Versi dokumen ditemukan', 'status_completed' => 'Proses tanda tangan selesai', 'hash_bound' => 'Hash cocok dengan versi bertanda tangan', 'file_intact' => 'Berkas utuh (SHA-256 terverifikasi)'] as $key => $label)
<li class="flex items-center gap-2"><span class="grid h-4.5 w-4.5 place-items-center rounded-full {{ ($checks[$key] ?? false) ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600' }}"><x-ui.icon :name="($checks[$key] ?? false) ? 'check' : 'triangle-alert'" class="h-3 w-3" /></span>{{ $label }}</li>
@endforeach
</ul>
</dd>
</dl>
<div class="border-t border-slate-200 bg-slate-50 px-6 py-4 text-xs text-slate-400">
Halaman verifikasi publik — hanya menampilkan status integritas dan metadata minimum dokumen.
</div>
</article>
<p class="mt-6 text-center text-sm"><a href="/" class="font-bold text-sky-700 hover:underline">← Kembali ke beranda</a></p>
</div>
</x-layouts.public>
