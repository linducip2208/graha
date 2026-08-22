<x-layouts.app title="RFQ & Perbandingan Harga"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-3xl font-black">RFQ & Perbandingan Harga</h1>
<p class="mt-2 text-slate-500">Alur pra-PO: buka RFQ → undang vendor → terima quotation → bandingkan total/skor/lead time → pilih pemenang (RFQ otomatis tertutup).</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 no-print"><form method="get" class="flex gap-2"><select name="rfq" onchange="this.form.submit()" class="rounded-xl border p-3 text-sm"><option value="">Pilih RFQ</option>@foreach($rfqs as $r)<option value="{{ $r->id }}" @selected($rfq?->id === $r->id)>{{ $r->number }} — {{ $r->title }} ({{ $r->status }})</option>@endforeach</select></form></div>

<form method="post" action="/admin/procurement/rfq" class="mt-6 grid gap-3 rounded-2xl border bg-white p-5 no-print">@csrf
<h2 class="font-bold">Buat RFQ Baru</h2>
<div class="grid gap-2 sm:grid-cols-2"><input name="number" required placeholder="Nomor RFQ" class="rounded-xl border p-3"><input name="title" required placeholder="Judul pengadaan" class="rounded-xl border p-3"><label class="text-xs font-semibold">Batas penawaran<input type="date" name="due_date" class="mt-1 w-full rounded-xl border p-3"></label><input name="notes" placeholder="Catatan (opsional)" class="rounded-xl border p-3"></div>
<label class="block text-xs font-semibold">Item — satu per baris: <code>SKU|kuantitas</code><textarea name="items" rows="3" placeholder="ITM-BESI|5&#10;ITM-BENTONITE|200" required class="mt-1 w-full rounded-xl border p-2.5 font-mono text-xs"></textarea></label>
<button class="w-fit rounded-xl bg-sky-700 px-6 py-3 font-bold text-white">Buat RFQ</button>
</form>

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
<tr class="{{ $index === 0 ? 'bg-emerald-50' : '' }}"><td>{{ $index + 1 }}{{ $index === 0 ? ' 🏆' : '' }}</td><td class="font-semibold">{{ $row['vendor'] }}</td><td class="text-right font-mono">{{ number_format((float) $row['total'], 2, ',', '.') }}</td><td class="text-right">{{ $row['lead'] ?? '-' }}</td><td class="text-right">{{ $row['tech'] ?? '-' }}</td><td class="text-right">{{ $row['comm'] ?? '-' }}</td><td>{{ $row['payment_term'] ?? '-' }}</td><td><x-ui.badge :status="match($row['status']) { 'selected' => 'approved', 'rejected' => 'rejected', default => 'pending_approval' }" :label="$row['status']" /></td><td>@if($canManage && $rfq->status === 'open' && $row['status'] === 'submitted')<form method="post" action="/admin/procurement/rfq/quotations/{{ $row['id'] }}/select" class="inline">@csrf<button onclick="return confirm('Pilih vendor ini sebagai pemenang? RFQ akan ditutup dan quotation lain ditolak.')" class="font-bold text-emerald-700">Pilih</button></form>@else-</td>@endif</tr>
@empty<tr><td colspan="9" class="p-8 text-center">Belum ada quotation. Undang vendor lalu input penawarannya.</td></tr>@endforelse</tbody></table></div>
@else
<x-ui.empty icon="swap" title="Belum ada RFQ" description="Buat RFQ pertama untuk memulai proses pengadaan kompetitif sebelum PO." />
@endif
</section></x-layouts.app>
