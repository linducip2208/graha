@php($tab = request('tab', 'overview'))
@php($tabs = ['overview' => 'Overview', 'versions' => 'Versions'])
@if($approvals->isNotEmpty())@php($tabs['approval'] = 'Approval')@endif
@if($activity->isNotEmpty())@php($tabs['activity'] = 'Activity')@endif
<x-layouts.app title="{{ $document->number }} — {{ $document->title }}">
<div class="page-container">
<x-ui.page-header :subtitle="'Jenis: '.$document->document_type.' · Pemilik: '.($document->owner?->name ?? '—')">
<x-slot:title><span class="font-mono text-lg text-[var(--text-muted)]">{{ $document->number }}</span> {{ $document->title }}</x-slot:title>
<x-slot:actions>
<span class="chip {{ $document->workflow_status === 'approved' ? 'chip-approved' : 'chip-draft' }}">{{ ucfirst($document->workflow_status) }}</span>
<span class="chip {{ $document->signature_status === 'fully_signed' ? 'chip-signed' : '' }}">{{ $document->signature_status === 'fully_signed' ? '✓ Ditandatangani' : 'Belum TTD' }}</span>
@if(($latest = $document->versions->first()))<a class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold" href="/admin/document-versions/{{ $latest->id }}/download"><x-ui.icon name="document" class="h-4 w-4" />Unduh Rev. {{ $latest->revision ?? $latest->version - 1 }}</a>@endif
</x-slot:actions>
</x-ui.page-header>

<x-ui.tabs :tabs="$tabs" :active="$tab" class="mt-5" />

@php($activeTab = array_key_exists($tab, $tabs) ? $tab : 'overview')
@if($activeTab === 'overview')
<article class="mt-4 rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-6 shadow-[var(--shadow-xs)]">
<dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
<div><dt class="text-[11px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Nomor Dokumen</dt><dd class="mt-1 font-mono text-sm font-bold">{{ $document->number }}</dd></div>
<div><dt class="text-[11px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Jumlah Versi</dt><dd class="mt-1 text-sm font-bold tabular-nums">{{ $document->versions->count() }} versi</dd></div>
<div><dt class="text-[11px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Revisi Berlaku</dt><dd class="mt-1 text-sm font-bold">{{ optional($document->versions->first())->revision ?? '—' }}</dd></div>
<div><dt class="text-[11px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Pemilik</dt><dd class="mt-1 text-sm font-semibold">{{ $document->owner?->name ?? '—' }}</dd></div>
<div><dt class="text-[11px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Dibuat</dt><dd class="mt-1 text-sm">{{ optional($document->created_at)->format('d M Y H:i') }}</dd></div>
<div><dt class="text-[11px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Terakhir Diperbarui</dt><dd class="mt-1 text-sm">{{ optional($document->updated_at)->format('d M Y H:i') }}</dd></div>
</dl>
</article>
@elseif($activeTab === 'versions')
<article class="mt-4 overflow-hidden rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]">
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead><tr><th>Versi</th><th>Status</th><th>Alasan Perubahan</th><th>Oleh</th><th>Tanggal</th><th>SHA-256</th><th class="text-right">Aksi</th></tr></thead>
<tbody>
@foreach($document->versions as $version)
<tr>
<td class="whitespace-nowrap font-bold">v{{ $version->version }} <span class="font-normal text-[var(--text-muted)]">(Rev. {{ $version->revision }})</span></td>
<td>@if($version->is_signed)<span class="chip chip-signed">✓ Terkunci TTD</span>@else<span class="chip chip-draft">Terbuka</span>@endif</td>
<td class="max-w-[320px]">{{ \Illuminate\Support\Str::limit((string) $version->change_reason, 90) }}</td>
<td class="whitespace-nowrap">{{ $version->creator?->name ?? '—' }}</td>
<td class="whitespace-nowrap text-[var(--text-muted)]">{{ optional($version->created_at)->format('d M Y H:i') }}</td>
<td class="max-w-[140px] truncate font-mono text-[10px] text-[var(--text-muted)]" title="{{ $version->sha256 }}">{{ $version->sha256 }}</td>
<td class="whitespace-nowrap text-right"><a class="font-bold text-[var(--brand-primary)] hover:underline" href="/admin/document-versions/{{ $version->id }}/download">Unduh</a></td>
</tr>
@endforeach
</tbody>
</table>
</div>
</article>
@elseif($activeTab === 'approval')
<article class="mt-4 space-y-3">
@foreach($approvals as $request)
<article class="rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-xs)]">
<header class="flex flex-wrap items-center justify-between gap-2">
<p class="text-sm font-black">{{ ucfirst($request->workflow?->name ?? 'Alur Persetujuan') }}</p>
<span class="chip {{ match ($request->status) { 'approved' => 'chip-approved', 'rejected' => 'bg-red-50 text-red-600', default => 'chip-draft' } }}">{{ ucfirst($request->status) }}</span>
</header>
<dl class="mt-3 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-3">
<div><dt class="text-[10px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Diajukan</dt><dd>{{ optional($request->submitted_at)->format('d M Y H:i') }}</dd></div>
<div><dt class="text-[10px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Tahap Aktif</dt><dd class="tabular-nums">#{{ $request->current_sequence }}</dd></div>
<div><dt class="text-[10px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Selesai</dt><dd>{{ optional($request->completed_at)?->format('d M Y H:i') ?? '—' }}</dd></div>
</dl>
@if($request->decisions->isNotEmpty())
<ul class="mt-3 space-y-1.5 border-t pt-3 text-sm" style="border-color:var(--border-subtle)">
@foreach($request->decisions as $decision)
<li class="flex items-center justify-between gap-2"><span>{{ $decision->actor?->name ?? 'Pengguna' }} · {{ $decision->comment ? \Illuminate\Support\Str::limit($decision->comment, 80) : ucfirst($decision->decision) }}</span><span class="text-xs text-[var(--text-muted)]">{{ optional($decision->decided_at)->format('d M Y H:i') }}</span></li>
@endforeach
</ul>
@endif
</article>
@endforeach
</article>
@elseif($activeTab === 'activity')
<article class="mt-4 overflow-hidden rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]">
<table class="w-full text-sm">
<thead><tr><th>Waktu</th><th>Aktivitas</th><th>Aktor</th></tr></thead>
<tbody>
@foreach($activity as $log)
<tr>
<td class="whitespace-nowrap text-[var(--text-muted)]">{{ optional($log->created_at)->format('d M Y H:i') }}</td>
<td class="font-mono text-xs font-bold">{{ $log->event }}</td>
<td class="whitespace-nowrap">{{ $log->actor?->name ?? 'Sistem' }}</td>
</tr>
@endforeach
</tbody>
</table>
</article>
@endif

<nav class="mt-6 no-print"><a href="/admin/documents" class="inline-flex min-h-[40px] items-center gap-2 rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]">← Kembali ke Document Control</a></nav>
</div>
</x-layouts.app>
