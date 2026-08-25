<x-layouts.app title="Transmittal Dokumen">
<div class="page-container">
<x-ui.page-header title="Transmittal Dokumen" subtitle="Register distribusi dokumen ke pihak eksternal/internal dengan daftar versi dokumen yang dikirim dan status penerimaan." />
@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-3">
<x-ui.stat-card label="Total Transmittal" value="{{ number_format($stats['total']) }}" icon="document" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Terikirim" value="{{ number_format($stats['sent']) }}" icon="swap" tone="info" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Diterima" value="{{ number_format($stats['acknowledged']) }}" icon="check" tone="success" :value-class="'text-[24px] leading-tight'" />
</div>

@if(auth()->user()->hasPermission('document.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<button type="button" class="btn-brand mt-4 inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="transmittal-drawer" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />Buat Transmittal</button>
@endif

<div class="mt-8 space-y-3">@forelse($transmittals as $t)
<x-ui.card><div class="flex flex-wrap items-start justify-between gap-3"><div><strong class="font-mono">{{ $t->number }}</strong> → {{ $t->recipient }}<p class="text-sm text-slate-500">{{ $t->transmit_date->format('d/m/Y') }} · {{ ucfirst($t->method) }}@if($t->purpose) · {{ $t->purpose }}@endif</p><ul class="mt-2 list-disc pl-5 text-xs text-slate-600">@foreach($t->items as $item)<li>{{ $item->version?->document?->number }} v{{ $item->version?->version }} — {{ \Illuminate\Support\Str::limit($item->version?->document?->title ?? '', 60) }}</li>@endforeach</ul></div>
<div class="flex flex-col items-end gap-2"><span class="chip chip-default {{ $t->status === 'acknowledged' ? 'bg-emerald-50 text-emerald-700' : '' }}">{{ strtoupper($t->status) }}@if($t->acknowledged_at) {{ $t->acknowledged_at->format(' d/m/Y') }}@endif</span>
@if($t->status === 'sent' && auth()->user()->hasPermission('document.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))<form method="post" action="/admin/documents/transmittals/{{ $t->id }}/acknowledge">@csrf<button data-confirm="Tandai transmittal ini sudah diterima penerima?" class="rounded-lg bg-emerald-700 px-2.5 py-1.5 text-xs font-bold text-white">Tandai Diterima</button></form>@endif
</div></div></x-ui.card>
@empty<div class="mt-8 rounded-2xl border border-dashed bg-white p-8 text-center"><h3 class="font-bold">Belum ada transmittal</h3><p class="mt-1 text-sm text-slate-500">Catat pengiriman dokumen resmi agar jejak distribusi terkendali.</p></div>@endforelse</div>
</div>

@if(auth()->user()->hasPermission('document.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<x-ui.drawer id="transmittal-drawer" title="Buat Transmittal" description="Pilih satu atau lebih versi dokumen dari registry, lalu catat penerima dan metode kirim.">
<form method="post" action="/admin/documents/transmittals" class="grid gap-4">@csrf
<x-ui.field label="Penerima" name="recipient" required><input name="recipient" required placeholder="Nama pihak / instansi" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div class="grid grid-cols-2 gap-3">
<x-ui.field label="Tanggal kirim" name="transmit_date"><input type="date" name="transmit_date" value="{{ today()->toDateString() }}" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Metode" name="method"><select name="method" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="email">Email</option><option value="courier">Kurir</option><option value="hand">Langsung</option><option value="portal">Portal</option></select></x-ui.field>
</div>
<x-ui.field label="Keperluan (opsional)" name="purpose"><input name="purpose" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Dokumen (versi) — pilih minimal satu" name="version_ids"><select name="version_ids[]" multiple size="8" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5">@foreach($versions as $v)<option value="{{ $v->id }}">{{ $v->document?->number }} v{{ $v->version }} · {{ \Illuminate\Support\Str::limit($v->document?->title ?? '', 50) }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Catatan (opsional)" name="notes"><textarea name="notes" rows="2" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Kirim & Catat</button></div>
</form>
</x-ui.drawer>
@endif
</x-layouts.app>
