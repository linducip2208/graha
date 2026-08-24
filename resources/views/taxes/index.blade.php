<x-layouts.app title="Pajak & Bukti Potong"><section class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
<h1 class="text-2xl font-bold tracking-tight">Pajak & Bukti Potong</h1>
<p class="mt-2 text-slate-500">Rekapitulasi PPN keluaran/masukan dan PPh yang dipotong atau dipotongkan, plus master tarif pajak perusahaan.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 flex flex-wrap items-center gap-2">@foreach($years as $y)<a href="/admin/taxes?year={{ $y }}" class="rounded-xl border px-4 py-2 {{ $y == $year ? 'bg-slate-900 text-white' : 'bg-white' }}">{{ $y }}</a>@endforeach</div>

<div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
@php($cards = [['PPN Keluaran', $totals['tax_out'], 'Dari progress billing posted.', 'bg-sky-50'], ['PPN Masukan', $totals['tax_in'], 'Dari invoice vendor matched & posted.', 'bg-emerald-50'], ['PPh Dipotong Klien', $totals['pph_received'], 'PPh final/4(2) yang dipotong pemberi kerja.', 'bg-violet-50'], ['PPh Dipotong ke Vendor', $totals['pph_paid'], 'PPh 23 yang kita potong saat bayar vendor.', 'bg-amber-50'], ['Netto PPN ('.$year.')', bcsub($totals['tax_out'], $totals['tax_in'], 2), 'PPN keluaran dikurangi masukan (kreditable).', 'bg-slate-100']])
@foreach($cards as [$label, $value, $desc, $tone])
<article class="rounded-2xl border bg-white p-5 shadow-sm"><div class="rounded-xl p-3 {{ $tone }}"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-black">Rp {{ number_format($value, 2, ',', '.') }}</p></div><p class="mt-2 text-sm text-slate-500">{{ $desc }}</p></article>
@endforeach</div>

<div class="mt-8 overflow-x-auto rounded-2xl border bg-white">
<table class="w-full min-w-[900px] text-sm">
<thead><tr class="border-b bg-slate-50 text-left uppercase text-[11px] tracking-wide text-slate-500"><th>Bulan</th><th class="text-right">DPP Keluaran</th><th class="text-right">PPN Keluaran</th><th class="text-right">DPP Masukan</th><th class="text-right">PPN Masukan</th><th class="text-right">PPh Dipotong Klien</th><th class="text-right">PPh Dipotong Vendor</th></tr></thead>
<tbody>
@forelse($months as $num => $m)
<tr class="border-b last:border-0 hover:bg-slate-50"><td class="px-4 py-2 font-semibold">{{ \Carbon\Carbon::create(null, $num, 1)->translatedFormat('F') }}</td><td class="px-4 py-2 text-right font-mono">{{ number_format($m['dpp_out'], 2, ',', '.') }}</td><td class="px-4 py-2 text-right font-mono">{{ number_format($m['tax_out'], 2, ',', '.') }}</td><td class="px-4 py-2 text-right font-mono">{{ number_format($m['dpp_in'], 2, ',', '.') }}</td><td class="px-4 py-2 text-right font-mono">{{ number_format($m['tax_in'], 2, ',', '.') }}</td><td class="px-4 py-2 text-right font-mono">{{ number_format($m['pph_received'], 2, ',', '.') }}</td><td class="px-4 py-2 text-right font-mono">{{ number_format($m['pph_paid'], 2, ',', '.') }}</td></tr>
@empty<tr><td colspan="7" class="p-8 text-center text-slate-500">Belum ada transaksi berpajak pada tahun ini.</td></tr>@endforelse
</tbody>
<tfoot><tr class="border-t bg-slate-50 font-bold"><td class="px-4 py-3">Total {{ $year }}</td><td class="px-4 py-3 text-right font-mono">{{ number_format($totals['dpp_out'], 2, ',', '.') }}</td><td class="px-4 py-3 text-right font-mono">{{ number_format($totals['tax_out'], 2, ',', '.') }}</td><td class="px-4 py-3 text-right font-mono">{{ number_format($totals['dpp_in'], 2, ',', '.') }}</td><td class="px-4 py-3 text-right font-mono">{{ number_format($totals['tax_in'], 2, ',', '.') }}</td><td class="px-4 py-3 text-right font-mono">{{ number_format($totals['pph_received'], 2, ',', '.') }}</td><td class="px-4 py-3 text-right font-mono">{{ number_format($totals['pph_paid'], 2, ',', '.') }}</td></tr></tfoot>
</table></div>

<div class="mt-10 grid gap-5 lg:grid-cols-2">
@if(auth()->user()->hasPermission('finance.manage',app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<form method="post" action="/admin/taxes/rates" class="grid gap-3 rounded-2xl border bg-white p-5">@csrf
<h2 class="font-bold">Tambah Tarif Pajak</h2>
<input name="code" required placeholder="Kode (mis. PPN11)" class="rounded-xl border p-3">
<input name="name" required placeholder="Nama tarif" class="rounded-xl border p-3">
<select name="kind" required class="rounded-xl border p-3"><option value="ppn_output">PPN Keluaran (penjualan)</option><option value="ppn_input">PPN Masukan (pembelian)</option><option value="withholding">Pemotongan PPh (bukti potong)</option></select>
<input type="number" step=".0001" min="0" max="100" name="rate_percent" required placeholder="Tarif % (mis. 11)" class="rounded-xl border p-3">
<input name="description" placeholder="Keterangan (opsional)" class="rounded-xl border p-3">
<button class="rounded-xl bg-[var(--brand-primary)] p-3 text-white">Simpan tarif</button>
<p class="text-xs text-slate-500">Contoh umum: PPN keluaran 11%, PPN masukan 11%, PPh 23 2%, PPh final konstruksi 0,5–2%.</p>
</form>
@endif
<div class="overflow-x-auto rounded-2xl border bg-white p-5"><h2 class="font-bold mb-3">Master Tarif Pajak</h2>
<table class="w-full text-sm"><thead><tr class="border-b text-left uppercase text-[11px] tracking-wide text-slate-500"><th>Kode</th><th>Jenis</th><th class="text-right">Tarif</th><th>Status</th>@if(auth()->user()->hasPermission('finance.manage',app(\App\Support\Tenancy\CurrentCompany::class)->id()))<th>Aksi</th>@endif</tr></thead>
<tbody>@forelse($rates as $rate)<tr class="border-b last:border-0"><td class="py-2 font-semibold">{{ $rate->code }}<span class="block text-xs text-slate-400">{{ $rate->name }}</span></td><td>{{ str($rate->kind)->replace('_', ' ') }}</td><td class="text-right font-mono">{{ number_format($rate->rate_percent, 4, ',', '.') }}%</td><td>@if($rate->is_active)<span class="rounded-md bg-emerald-100 px-2 py-1 text-xs font-semibold">Aktif</span>@else<span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold">Nonaktif</span>@endif</td>
@if(auth()->user()->hasPermission('finance.manage',app(\App\Support\Tenancy\CurrentCompany::class)->id()))<td><form method="post" action="/admin/taxes/rates/{{ $rate->id }}/toggle">@csrf<button class="font-bold text-slate-600 hover:text-slate-900">{{ $rate->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form></td>@endif</tr>@empty<tr><td colspan="5" class="p-6 text-center text-slate-500">Belum ada tarif pajak.</td></tr>@endforelse</tbody></table></div></div>

<div class="mt-8 rounded-2xl border bg-white p-5"><h2 class="font-bold">Mapping Akun Pajak</h2>
<p class="mb-4 mt-1 text-sm text-slate-500">Empat sisi jurnal pajak wajib dipetakan sebelum posting dokumen berpajak.</p>
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
@php($sides = [['progress_billing', 'tax_credit', 'PPN Keluaran (billing)'], ['vendor_invoice', 'tax_debit', 'PPN Masukan (invoice)'], ['customer_receipt', 'withholding_debit', 'PPh dipotong klien'], ['vendor_payment', 'withholding_credit', 'PPh dipotong vendor']])
@foreach($sides as [$event, $side, $label])
@php($existing = $mappings->get($event.'.'.$side))
<form method="post" action="/admin/finance/mappings" class="grid gap-2 rounded-xl border p-4 @if($existing) border-emerald-300 bg-emerald-50 @else border-dashed @endif">@csrf
<h3 class="text-sm font-bold">{{ $label }}</h3>
<input type="hidden" name="event_type" value="{{ $event }}">
<input type="hidden" name="entry_side" value="{{ $side }}">
<select name="account_id" required class="rounded-lg border p-2 text-sm"><option value="">Akun GL</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected($existing?->account_id === $a->id)>{{ $a->code }} — {{ $a->name }}</option>@endforeach</select>
<button class="rounded-lg bg-slate-900 p-2 text-sm text-white">{{ $existing ? 'Ubah mapping' : 'Simpan mapping' }}</button>
</form>
@endforeach</div></div>

<div class="mt-8 grid gap-5 xl:grid-cols-2">
<div class="overflow-x-auto rounded-2xl border bg-white p-5"><h2 class="font-bold">Bukti Potong Diterima (dari klien)</h2>
<table class="mt-3 w-full text-sm"><thead><tr class="border-b text-left uppercase text-[11px] tracking-wide text-slate-500"><th>No. Bukti</th><th>Penerimaan</th><th>Proyek</th><th class="text-right">Dipotong</th></tr></thead>
<tbody>@forelse($certificatesOut as $cert)<tr class="border-b last:border-0"><td class="py-2 font-mono text-xs">{{ $cert->bukti_potong_number }}<span class="block text-slate-400">{{ optional($cert->bukti_potong_date)->format('d/m/Y') }}</span></td><td>{{ $cert->number }}</td><td>{{ $cert->billing?->project?->code }}</td><td class="text-right font-mono">{{ number_format($cert->withholding_amount, 2, ',', '.') }}</td></tr>@empty<tr><td colspan="4" class="p-6 text-center text-slate-500">Belum ada bukti potong dari klien.</td></tr>@endforelse</tbody></table></div>
<div class="overflow-x-auto rounded-2xl border bg-white p-5"><h2 class="font-bold">Bukti Potong Diterbitkan (ke vendor)</h2>
<table class="mt-3 w-full text-sm"><thead><tr class="border-b text-left uppercase text-[11px] tracking-wide text-slate-500"><th>No. Bukti</th><th>Pembayaran</th><th>Vendor</th><th class="text-right">Dipotong</th></tr></thead>
<tbody>@forelse($certificatesIn as $cert)<tr class="border-b last:border-0"><td class="py-2 font-mono text-xs">{{ $cert->bukti_potong_number }}<span class="block text-slate-400">{{ optional($cert->bukti_potong_date)->format('d/m/Y') }}</span></td><td>{{ $cert->number }}</td><td>{{ $cert->invoice?->vendor?->name }}</td><td class="text-right font-mono">{{ number_format($cert->withholding_amount, 2, ',', '.') }}</td></tr>@empty<tr><td colspan="4" class="p-6 text-center text-slate-500">Belum ada bukti potong ke vendor.</td></tr>@endforelse</tbody></table></div></div>
</section></x-layouts.app>
