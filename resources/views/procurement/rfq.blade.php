<x-layouts.app title="RFQ & Perbandingan Harga"><div class="page-container">
<x-ui.page-header title="RFQ & Perbandingan Harga" />
<p class="mt-2 text-slate-500">Alur pra-PO: buka RFQ → undang vendor → terima quotation → bandingkan total/skor/lead time → pilih pemenang (RFQ otomatis tertutup).</p>
<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="rfq-create-drawer" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />RFQ Baru</button>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 no-print"><form method="get" class="flex gap-2"><select name="rfq" onchange="this.form.submit()" class="rounded-xl border p-3 text-sm"><option value="">Pilih RFQ</option>@foreach($rfqs as $r)<option value="{{ $r->id }}" @selected($rfq?->id === $r->id)>{{ $r->number }} — {{ $r->title }} ({{ $r->status }})</option>@endforeach</select></form></div>



@if($rfq)
@php($canManage = auth()->user()->hasPermission('procurement.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<article class="mt-8 rounded-2xl border bg-white p-5">
<div class="flex flex-wrap items-center justify-between gap-2"><strong>{{ $rfq->number }} — {{ $rfq->title }}</strong><x-ui.badge :status="$rfq->status === 'open' ? 'open' : 'closed'" :label="$rfq->status" /></div>
<p class="mt-1 text-xs text-slate-500">{{ $rfq->items->count() }} item · {{ $rfq->vendors->count() }} vendor diundang · {{ $rfq->quotations->count() }} quotation @if($rfq->due_date)· batas {{ $rfq->due_date->format('d/m/Y') }}@endif</p>
<ul class="mt-3 grid gap-1 text-xs sm:grid-cols-2 xl:grid-cols-3">@foreach($rfq->items as $item)<li class="rounded-lg bg-slate-50 px-3 py-1.5">{{ $item->item?->sku }} — {{ $item->item?->name }} <span class="font-mono">{{ $item->quantity }}</span></li>@endforeach</ul>
</article>

@if($canManage && $rfq->status === 'open')
<form method="post" action="/admin/procurement/rfq/{{ $rfq->id }}/invite" class="mt-4 grid gap-3 rounded-2xl border bg-white p-5 no-print">@csrf
<h3 class="text-sm font-bold">Undang Vendor</h3>
<div class="flex flex-wrap gap-3">@foreach($vendors as $vendor)<label class="flex items-center gap-2 rounded-xl border px-3 py-2 text-sm"><input type="checkbox" name="vendor_ids[]" value="{{ $vendor->id }}"> {{ $vendor->name }}</label>@endforeach</div>
<button class="w-fit rounded-xl bg-slate-900 px-5 py-2.5 font-bold text-white">Kirim undangan</button>
</form>

<form method="post" action="/admin/procurement/rfq/{{ $rfq->id }}/quotations" class="mt-4 grid gap-3 rounded-2xl border bg-white p-5 no-print">@csrf
<h3 class="text-sm font-bold">Input Quotation Vendor</h3>
<select name="vendor_id" required class="w-fit rounded-xl border p-3"><option value="">Vendor yang diundang</option>@foreach($rfq->vendors as $invited)<option value="{{ $invited->vendor_id }}">{{ $invited->vendor?->name }}</option>@endforeach</select>
<input name="number" required placeholder="No. quotation vendor" class="w-fit rounded-xl border p-3">
<div class="grid gap-2 sm:grid-cols-4"><input name="delivery_lead_days" type="number" min="0" placeholder="Lead time (hari)" class="rounded-xl border p-3"><input name="payment_term" placeholder="Termin bayar" class="rounded-xl border p-3"><input name="technical_score" type="number" step=".01" min="0" max="100" placeholder="Skor teknis" class="rounded-xl border p-3"><input name="commercial_score" type="number" step=".01" min="0" max="100" placeholder="Skor komersial" class="rounded-xl border p-3"></div>
<table class="w-full text-sm"><thead><tr><th>Item</th><th class="text-right">Qty RFQ</th><th class="text-right">Harga satuan (Rp)</th></tr></thead><tbody>@foreach($rfq->items as $item)<tr><td>{{ $item->item?->sku }} — {{ $item->item?->name }}</td><td class="text-right font-mono">{{ $item->quantity }}</td><td class="text-right"><input type="number" step=".01" min="0" name="prices[{{ $item->item_id }}]" required class="w-40 rounded-xl border p-2 text-right font-mono"></td></tr>@endforeach</tbody></table>
<button class="w-fit rounded-xl bg-violet-700 px-6 py-3 font-bold text-white">Simpan quotation</button>
</form>
@endif

<h2 id="perbandingan" class="mt-10 scroll-mt-24 text-lg font-black">Perbandingan Penawaran</h2>
<div class="mt-3 overflow-x-auto rounded-2xl border bg-white"><table class="w-full min-w-[760px] text-sm table-sticky"><thead><tr><th>#</th><th>Vendor</th><th class="text-right">Total (Rp)</th><th class="text-right">Lead (hari)</th><th class="text-right">Skor Teknis</th><th class="text-right">Skor Komersial</th><th>Termin</th><th>Status</th><th>Aksi</th></tr></thead><tbody>@forelse($comparison as $index => $row)
<tr class="{{ $index === 0 ? 'bg-emerald-50' : '' }}"><td>{{ $index + 1 }}{{ $index === 0 ? ' 🏆' : '' }}</td><td class="font-semibold">{{ $row['vendor'] }}</td><td class="text-right font-mono">{{ number_format((float) $row['total'], 2, ',', '.') }}</td><td class="text-right">{{ $row['lead'] ?? '-' }}</td><td class="text-right">{{ $row['tech'] ?? '-' }}</td><td class="text-right">{{ $row['comm'] ?? '-' }}</td><td>{{ $row['payment_term'] ?? '-' }}</td><td><x-ui.badge :status="match($row['status']) { 'selected' => 'approved', 'rejected' => 'rejected', default => 'pending_approval' }" :label="$row['status']" /></td><td>@if($canManage && $rfq->status === 'open' && $row['status'] === 'submitted')<form method="post" action="/admin/procurement/rfq/quotations/{{ $row['id'] }}/select" class="inline">@csrf<button data-confirm="Pilih vendor ini sebagai pemenang? RFQ akan ditutup dan quotation lain ditolak." class="font-bold text-emerald-700">Pilih</button></form>@else-</td>@endif</tr>
@empty<tr><td colspan="9" class="p-8 text-center">Belum ada quotation. Undang vendor lalu input penawarannya.</td></tr>@endforelse</tbody></table></div>
@else
<x-ui.empty icon="swap" title="Belum ada RFQ" description="Buat RFQ pertama untuk memulai proses pengadaan kompetitif sebelum PO." />
@endif
</div>
<x-ui.drawer id="rfq-create-drawer" title="Buat RFQ">
<form method="post" action="/admin/procurement/rfq" class="grid gap-4">@csrf
<x-ui.form-section title="Buat RFQ Baru" description="Undang vendor, terima penawaran, lalu pilih pemenang — RFQ otomatis tertutup.">
<div class="grid gap-4 sm:grid-cols-2"><x-ui.field label="Nomor RFQ" name="number" hint="Unik per perusahaan" required><input name="number" placeholder="mis. RFQ-2026-001" required class="w-full p-3"></x-ui.field><x-ui.field label="Judul pengadaan" name="title" required><input name="title" placeholder="mis. Pengadaan Besi Beton" required class="w-full p-3"></x-ui.field><x-ui.field label="Batas penawaran" name="due_date"><input type="date" name="due_date" class="w-full p-3"></x-ui.field><x-ui.field label="Catatan" name="notes" hint="Opsional"><input name="notes" placeholder="Instruksi untuk vendor" class="w-full p-3"></x-ui.field></div>
<div class="mt-4">
<label class="block"><span class="mb-1 block text-xs font-bold text-slate-600 dark:text-slate-300">Item — satu per baris <span class="font-mono text-slate-400">SKU|kuantitas</span></span><textarea name="items" rows="3" placeholder="ITM-BESI|5&#10;ITM-BENTONITE|200" required class="w-full rounded-xl border p-2.5 font-mono text-xs"></textarea></label>
</div>
<div class="mt-4"><button class="rounded-xl bg-[var(--brand-primary)] px-6 py-3 font-bold text-white">Buat RFQ</button></div>
</x-ui.form-section>
</form>
</x-ui.drawer>
</x-layouts.app>
