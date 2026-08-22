<x-layouts.app title="Stock Opname"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-3xl font-black">Stock Opname</h1>
<p class="mt-2 text-slate-500">Penghitungan fisik per gudang. Approval oleh user lain (bukan penghitung) akan memposting adjustment in/out ke ledger secara idempotent — hanya baris dengan selisih.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<form method="post" action="/admin/inventory/opname" class="mt-6 grid gap-3 rounded-2xl border bg-white p-5">@csrf
<h2 class="font-bold">Buat Opname Baru</h2>
<div class="grid gap-2 sm:grid-cols-3">
<input name="number" required placeholder="Nomor opname (mis. OPN-2026-001)" class="rounded-xl border p-3">
<select name="warehouse_id" required class="rounded-xl border p-3"><option value="">Gudang</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>@endforeach</select>
<input name="notes" placeholder="Catatan (opsional)" class="rounded-xl border p-3">
</div>
<label class="block text-xs font-semibold">Hasil hitung fisik — satu baris per item: <code>SKU|qty_fisik</code><textarea name="lines" rows="4" required placeholder="ITM-BESI|49.5&#10;ITM-BENTONITE|52" class="mt-1 w-full rounded-xl border p-2.5 font-mono text-xs"></textarea></label>
<p class="text-xs text-slate-500">Saldo sistem diambil otomatis dari ledger saat dibuat; approval hanya memproses baris bervariansi dan menolak stok negatif tanpa override item.</p>
<button class="w-fit rounded-xl bg-sky-700 px-6 py-3 font-bold text-white">Simpan draft opname</button>
</form>

<h2 class="mt-10 text-lg font-black">Riwayat Opname</h2>
<div class="mt-3 space-y-3">@forelse($counts as $count)
<article class="rounded-2xl border bg-white p-5">
<div class="flex flex-wrap items-center justify-between gap-2">
<strong>{{ $count->number }} · {{ $count->warehouse?->code }}</strong>
<x-ui.badge :status="$count->status === 'approved' ? 'approved' : 'draft'" />
</div>
<p class="mt-1 text-xs text-slate-500">{{ $count->lines->count() }} baris · dihitung {{ $count->counted_by ? \App\Models\User::find($count->counted_by)?->name : '-' }} @if($count->approved_at)· disetujui {{ $count->approved_at->format('d/m/Y H:i') }}@endif</p>
<details class="mt-2"><summary class="cursor-pointer text-xs font-bold text-sky-700">Detail varian</summary>
<table class="mt-2 w-full text-xs"><thead><tr><th>SKU</th><th class="text-right">Sistem</th><th class="text-right">Fisik</th><th class="text-right">Selisih</th></tr></thead><tbody>@foreach($count->lines as $line)<tr><td>{{ $line->item?->sku }}</td><td class="text-right font-mono">{{ $line->system_quantity }}</td><td class="text-right font-mono">{{ $line->counted_quantity }}</td><td class="text-right font-mono {{ $line->variance() !== '0.0000' ? 'font-bold text-amber-600' : 'text-slate-400' }}">{{ $line->variance() }}</td></tr>@endforeach</tbody></table></details>
@if($count->status === 'draft' && auth()->user()->hasPermission('inventory.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()) && $count->counted_by !== auth()->id())
<form method="post" action="/admin/inventory/opname/{{ $count->id }}/approve" class="mt-2 inline">@csrf<button onclick="return confirm('Approve akan memposting adjustment ke ledger secara permanen untuk semua baris bervarian. Lanjutkan?')" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white">Approve & posting adjustment</button></form>
@endif
</article>
@empty<x-ui.empty icon="archive" title="Belum ada opname" description="Buat penghitungan fisik pertama untuk gudang Anda." />@endforelse</div>
</section></x-layouts.app>
