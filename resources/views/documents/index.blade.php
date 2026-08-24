<x-layouts.app title="Document Control">
<div class="page-container">
<x-ui.page-header title="Document Control" subtitle="Kelola dokumen, revisi, approval, dan distribusi — setiap versi terikat hash SHA-256.">
<x-slot:actions>
<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="document-create-drawer"><x-ui.icon name="plus" class="h-4 w-4" />Dokumen Baru</button>
</x-slot:actions>
</x-ui.page-header>

<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
<x-ui.stat-card label="Total Dokumen" value="{{ number_format($stats['total']) }}" icon="document" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Disetujui" value="{{ number_format($stats['approved']) }}" icon="check" tone="success" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Draft (Menunggu Review)" value="{{ number_format($stats['draft']) }}" icon="clock" tone="warning" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Ditandatangani" value="{{ number_format($stats['signed']) }}" icon="pen" tone="violet" :value-class="'text-[24px] leading-tight'" />
</div>

<x-ui.filter-bar class="mt-6">
<input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nomor atau judul dokumen…" class="min-w-[220px] flex-1 rounded-xl border border-[var(--border-default)] bg-[var(--surface-input)] px-3.5 text-sm sm:max-w-xs">
<select name="type" class="rounded-xl border border-[var(--border-default)] bg-[var(--surface-input)] px-3 text-sm">
<option value="">Semua Jenis</option>
@foreach($types as $type)
<option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>
@endforeach
</select>
<select name="status" class="rounded-xl border border-[var(--border-default)] bg-[var(--surface-input)] px-3 text-sm">
<option value="">Semua Status</option>
<option value="draft" @selected(request('status') === 'draft')>Draft</option>
<option value="approved" @selected(request('status') === 'approved')>Approved</option>
</select>
<button class="inline-flex min-h-[42px] items-center gap-2 rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]">Terapkan</button>
</x-ui.filter-bar>

<article class="mt-4 overflow-hidden rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]">
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr>
<th>Nomor</th><th>Judul</th><th>Jenis</th><th>Revisi</th><th>Status</th><th>Diperbarui</th><th class="text-right">Aksi</th>
</tr>
</thead>
<tbody>
@forelse($documents as $document)
@php($latest = $document->versions->first())
<tr>
<td class="whitespace-nowrap font-mono text-xs font-bold">{{ $document->number }}</td>
<td class="max-w-[320px]"><a href="/admin/documents/{{ $document->id }}" class="font-semibold text-[var(--brand-primary)] hover:underline">{{ $document->title }}</a></td>
<td class="whitespace-nowrap">{{ $document->document_type }}</td>
<td class="tabular-nums">{{ $latest ? 'Rev. '.($latest->revision ?? $latest->version - 1) : '—' }}</td>
<td><span class="chip {{ $document->workflow_status === 'approved' ? 'chip-approved' : 'chip-draft' }}">{{ ucfirst($document->workflow_status) }}</span>@if($document->signature_status === 'fully_signed')<span class="ml-1 chip chip-signed">✓ TTD</span>@endif</td>
<td class="whitespace-nowrap text-[var(--text-muted)]">{{ optional($document->updated_at)->format('d M Y H:i') }}</td>
<td class="whitespace-nowrap text-right">
@if($latest)<a class="font-bold text-[var(--brand-primary)] hover:underline" href="/admin/document-versions/{{ $latest->id }}/download">Unduh</a>@else<span class="text-[var(--text-muted)]">—</span>@endif
</td>
</tr>
@empty
<tr><td colspan="7" class="p-2">
<x-ui.empty icon="document" title="Belum ada dokumen" description="Daftarkan dokumen pertama Anda untuk mulai mengendalikan revisi dan distribusi.">
<button type="button" class="btn-brand rounded-xl px-4 py-2.5 text-sm font-bold" data-drawer-open="document-create-drawer">+ Dokumen Baru</button>
</x-ui.empty>
</td></tr>
@endforelse
</tbody>
</table>
</div>
@if($documents->hasPages())
<div class="border-t px-5 py-3 text-sm" style="border-color:var(--border-subtle)">{{ $documents->links() }}</div>
@endif
</article>
<p class="mt-3 text-xs text-[var(--text-muted)]">Menampilkan {{ $documents->firstItem() ?? 0 }}–{{ $documents->lastItem() ?? 0 }} dari {{ number_format($documents->total()) }} dokumen.</p>
</div>

<x-ui.drawer id="document-create-drawer" title="Dokumen Baru" description="Daftarkan dokumen baru — versi pertama otomatis tersimpan dengan hash SHA-256.">
<form method="post" action="/admin/documents" enctype="multipart/form-data" class="grid gap-4">
@csrf
<x-ui.field label="Jenis dokumen" name="document_type" required>
<input name="document_type" required maxlength="80" list="document-type-options" value="{{ old('document_type') }}" class="w-full rounded-xl border border-[var(--border-default)] px-3.5" placeholder="contract, policy, drawing…">
<datalist id="document-type-options">@foreach($types as $type)<option value="{{ $type }}">@endforeach</datalist>
</x-ui.field>
<x-ui.field label="Judul" name="title" required>
<input name="title" required maxlength="200" value="{{ old('title') }}" class="w-full rounded-xl border border-[var(--border-default)] px-3.5">
</x-ui.field>
<x-ui.field label="File (PDF/JPG/PNG, maks 20 MB)" name="file" required hint="File privat per perusahaan — akses lewat tautan ter-audit.">
<input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png" class="w-full rounded-xl border border-[var(--border-default)] p-2.5 text-sm">
</x-ui.field>
<x-ui.field label="Alasan / tujuan versi" name="change_reason" required>
<textarea name="change_reason" required maxlength="500" rows="3" class="w-full rounded-xl border border-[var(--border-default)] px-3.5">{{ old('change_reason') }}</textarea>
</x-ui.field>
<div class="flex justify-end gap-2 pt-2">
<button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button>
<button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Daftarkan Dokumen</button>
</div>
</form>
</x-ui.drawer>
</x-layouts.app>
