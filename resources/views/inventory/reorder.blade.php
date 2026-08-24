<x-layouts.app title="Rekomendasi Reorder">
<section class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
<x-ui.page-header title="Rekomendasi Reorder" subtitle="Usulan pengadaan deterministik dari saldo stok, outstanding PO, dan parameter reorder per item.">
<a href="/admin/inventory" class="x-ui.button secondary">← Inventory</a>
</x-ui.page-header>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid gap-5 lg:grid-cols-2">
<x-ui.card label="Item Perlu Reorder ({{ count($recommendations) }})" class="lg:col-span-2">
@forelse($recommendations as $rec)
@php($it = $rec['item'])
<div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3 text-sm">
<div>
<strong class="font-mono text-xs">{{ $it->sku }}</strong> · {{ $it->name }}
<p class="mt-0.5 text-[11px] text-slate-500">On-hand {{ $rec['on_hand'] }} · On-order {{ $rec['on_order'] }} · Target {{ $rec['target'] }} @if($rec['lead_time_days'] > 0)· Lead time {{ $rec['lead_time_days'] }} hari @endif</p>
</div>
<span class="rounded-full bg-indigo-50 px-3 py-1 font-mono text-xs font-black tabular-nums text-indigo-700">usulan {{ $rec['suggested_qty'] }} {{ $it->unit?->code ?? '' }}</span>
</div>
@empty
<x-ui.empty icon="archive" title="Tidak ada usulan reorder" description="Semua item dengan reorder point terisi masih di atas ambang, atau parameter reorder belum diset pada master item." />
@endforelse
<p class="mt-3 text-[11px] text-slate-400">Aturan: item aktif dengan saldo ≤ reorder point; usulan = target − on-hand − outstanding PO (status draft/pending/approved/active). Tanpa estimasi karangan.</p>
</x-ui.card>

<x-ui.card label="Parameter Reorder per Item" class="lg:col-span-2">
<form method="get" class="mb-4 flex flex-wrap items-end gap-2 no-print">
<div class="min-w-56"><label class="block text-[11px] font-bold text-slate-500">Cari item</label><input type="search" name="q" value="{{ request('q') }}" placeholder="SKU / nama" class="w-full rounded-lg border p-2 text-xs"></div>
<button class="rounded-lg bg-slate-800 px-4 py-2 text-xs font-bold text-white">Filter</button>
</form>
<div class="space-y-2">
@if($items->isEmpty())
<x-ui.empty icon="archive" title="Belum ada item" description="Tambahkan master item terlebih dahulu dari halaman Inventory." />
@endif
@foreach($items->when(request('q'), fn ($c) => $c->filter(fn ($i) => str_contains(mb_strtolower($i->sku.' '.$i->name), mb_strtolower(request('q'))))) as $it)
<form method="post" action="/admin/inventory/items/{{ $it->id }}/reorder-settings" class="flex flex-wrap items-end justify-between gap-3 rounded-xl border p-3">
@csrf
<input type="hidden" name="minimum_stock" value="{{ $it->minimum_stock }}">
<div class="min-w-40"><strong class="font-mono text-xs">{{ $it->sku }}</strong><p class="text-[11px] text-slate-500">{{ $it->name }}</p></div>
<label class="text-[11px] font-bold text-slate-500">Reorder Point<input type="number" step=".0001" min="0" name="reorder_point" value="{{ $it->reorder_point }}" required class="mt-0.5 block w-28 rounded border p-1.5 text-xs"></label>
<label class="text-[11px] font-bold text-slate-500">Target Max<input type="number" step=".0001" min="0" name="reorder_max" value="{{ $it->reorder_max }}" class="mt-0.5 block w-28 rounded border p-1.5 text-xs"></label>
<label class="text-[11px] font-bold text-slate-500">Lead Time (hari)<input type="number" min="0" max="365" name="lead_time_days" value="{{ $it->lead_time_days }}" class="mt-0.5 block w-20 rounded border p-1.5 text-xs"></label>
<button class="rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-xs font-bold text-white">Simpan</button>
</form>
@endforeach
</div>
<p class="mt-2 text-[11px] text-slate-400">Reorder point 0 = item tidak dipantau untuk reorder otomatis. Minimum stok dipertahankan dari nilai master.</p>
</x-ui.card>
</div>
</section>
</x-layouts.app>
