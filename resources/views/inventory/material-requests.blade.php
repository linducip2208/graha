<x-layouts.app title="Permintaan Material"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-2xl font-bold tracking-tight">Permintaan Material Proyek</h1>
<p class="mt-2 text-slate-500">Permintaan → approval pemisah → pengeluaran gudang dengan jurnal <strong>Biaya Material (D) / Gudang (K)</strong> berdimensi proyek, otomatis masuk project cost ledger.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<form method="post" action="/admin/inventory/material-requests" class="mt-6 grid gap-3 rounded-2xl border bg-white p-5 no-print">@csrf
<h2 class="font-bold">Permintaan Baru</h2>
<div class="grid gap-2 sm:grid-cols-4">
<input name="number" required placeholder="Nomor (MR-2026-001)" class="rounded-xl border p-3">
<select name="project_id" required class="rounded-xl border p-3"><option value="">Proyek</option>@foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>@endforeach</select>
<select name="warehouse_id" required class="rounded-xl border p-3"><option value="">Gudang sumber</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }}</option>@endforeach</select>
<select name="bored_pile_id" class="rounded-xl border p-3"><option value="">Titik pile (opsional)</option>@foreach($piles as $pile)<option value="{{ $pile->id }}">{{ $pile->project?->code }}/{{ $pile->pile_number }}</option>@endforeach</select>
</div>
<label class="block text-xs font-semibold">Item — satu per baris: <code>SKU|qty</code><textarea name="lines" rows="3" required placeholder="ITM-BESI|1.5&#10;ITM-BENTONITE|20" class="mt-1 w-full rounded-xl border p-2.5 font-mono text-xs"></textarea></label>
<button class="w-fit rounded-xl bg-sky-700 px-6 py-3 font-bold text-white">Ajukan permintaan</button>
</form>

<h2 class="mt-10 text-lg font-black">Daftar Permintaan</h2>
<div class="mt-3 space-y-3">@forelse($requests as $mr)
<article class="rounded-2xl border bg-white p-5">
<div class="flex flex-wrap items-center justify-between gap-2">
<strong>{{ $mr->number }} · {{ $mr->project?->code }}@if($mr->boredPile) / {{ $mr->boredPile->pile_number }}@endif · {{ $mr->warehouse?->code }}</strong>
<x-ui.badge :status="$mr->status === 'approved' ? 'approved' : ($mr->status === 'requested' ? 'pending_approval' : 'closed')" :label="$mr->status" />
</div>
<table class="mt-2 w-full text-xs"><thead><tr><th>SKU</th><th class="text-right">Diminta</th><th class="text-right">Diterbitkan</th><th>Kembali</th></tr></thead><tbody>@foreach($mr->lines as $line)<tr><td>{{ $line->item?->sku }}</td><td class="text-right font-mono">{{ $line->quantity }}</td><td class="text-right font-mono">{{ $line->issued_quantity }}</td><td>@if($canManage && $mr->status === 'approved' && bccomp((string) $line->issued_quantity, '0', 4) === 1)<form method="post" action="/admin/inventory/material-requests/{{ $mr->id }}/lines/{{ $line->id }}/return" class="inline-flex gap-1">@csrf<input type="number" step=".0001" min=".0001" max="{{ $line->issued_quantity }}" name="quantity" placeholder="qty" required class="w-20 rounded border p-1 text-xs"><button onclick="return confirm('Kembalikan material ini? Jurnal & biaya proyek dikoreksi otomatis.')" class="font-bold text-amber-600">Kembalikan</button></form>@else—@endif</td></tr>@endforeach</tbody></table>
@php($canManage = auth()->user()->hasPermission('inventory.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
@if($canManage)
@if($mr->status === 'requested' && $mr->requested_by !== auth()->id())
<form method="post" action="/admin/inventory/material-requests/{{ $mr->id }}/approve" class="mt-2 inline">@csrf<button onclick="return confirm('Setujui permintaan material ini?')" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white">Approve</button></form>
@elseif($mr->status === 'approved')
<form method="post" action="/admin/inventory/material-requests/{{ $mr->id }}/issue" class="mt-2 inline">@csrf<button onclick="return confirm('Terbitkan seluruh sisa material dari gudang dan posting jurnal biaya proyek?')" class="rounded-lg bg-sky-700 px-4 py-2 text-xs font-bold text-white">Issue ke proyek</button></form>
@endif
@endif
</article>
@empty<x-ui.empty icon="archive" title="Belum ada permintaan" description="Ajukan permintaan material pertama untuk proyek aktif." />@endforelse</div>
</section></x-layouts.app>
