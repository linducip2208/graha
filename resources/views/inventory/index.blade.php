<x-layouts.app title="Inventory & Gudang">
<div class="page-container">
<x-ui.page-header title="Inventory" subtitle="Persediaan material dan operasi gudang — setiap movement masuk ledger immutable dengan FIFO cost.">
<x-slot:actions>
<button type="button" class="inline-flex min-h-[42px] items-center gap-2 rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-open="inventory-setup-drawer"><x-ui.icon name="archive" class="h-4 w-4" />Item & Gudang</button>
<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="inventory-movement-drawer"><x-ui.icon name="swap" class="h-4 w-4" />Posting Movement</button>
</x-slot:actions>
</x-ui.page-header>

@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
<x-ui.stat-card label="Total SKU Terdaftar" value="{{ number_format($items->count()) }}" icon="archive" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Baris Saldo Stok" value="{{ number_format($balanceCount) }}" icon="grid" tone="info" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Stok Kritis" value="{{ number_format($lowStock->count()) }}" icon="triangle-alert" tone="{{ $lowStock->isNotEmpty() ? 'danger' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Movement Terakhir" value="{{ optional($movements->first())->posted_at?->format('d M H:i') ?? '—' }}" icon="clock" tone="violet" :value-class="'text-[18px] leading-tight'" />
</div>

<x-ui.card label="Stok Kritis" bodyClass="p-0" class="mt-6" id="stok-kritis">
@if($lowStock->isNotEmpty())
<p class="border-b border-[var(--border-subtle)] bg-amber-50 px-5 py-2.5 text-xs font-bold text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">{{ $lowStock->count() }} baris di bawah minimum stock</p>
@endif
<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th>SKU</th><th>Gudang/Bin</th><th>Qty</th><th>Min. Stock</th><th>Status</th></tr></thead><tbody>@forelse($lowStock as $row)<tr><td>{{ $row->item->sku }} <span class="block text-xs text-slate-400">{{ $row->item->name }}</span></td><td>{{ $row->warehouse->code }}/{{ $row->bin?->code }}</td><td class="font-mono font-bold text-amber-700 dark:text-amber-300">{{ $row->quantity }}</td><td class="font-mono">{{ $row->item->minimum_stock }}</td><td><x-ui.badge status="exception" label="di bawah minimum" /></td></tr>@empty<tr><td colspan="5" class="p-6 text-center text-slate-500">Semua stok di atas minimum — aman.</td></tr>@endforelse</tbody></table></div>
</x-ui.card>

<x-ui.filter-bar action="/admin/inventory" class="mt-6">
<input name="q" value="{{ request('q') }}" placeholder="Cari SKU/nama item…" class="w-64 rounded-xl border border-[var(--border-default)] px-3.5 text-sm">
<x-ui.button variant="secondary" type="submit">Filter</x-ui.button>
<span class="text-xs text-slate-400">{{ $balanceCount }} baris saldo</span>
</x-ui.filter-bar>

<article class="mt-4 overflow-hidden rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]">
<div class="overflow-x-auto"><table class="w-full text-sm table-sticky"><thead><tr><th>SKU</th><th>Gudang/Bin</th><th>Lot</th><th class="text-right">Qty</th><th class="text-right">Reserved</th></tr></thead><tbody>@forelse($balances as $b)<tr><td>{{ $b->item->sku }} <span class="block text-xs text-slate-400">{{ $b->item->name }}</span></td><td>{{ $b->warehouse->code }}/{{ $b->bin?->code }}</td><td class="font-mono text-xs">{{ $b->lot_number?:'-' }}</td><td class="text-right font-mono font-bold">{{ $b->quantity }}</td><td class="text-right font-mono">{{ $b->reserved_quantity }}</td></tr>@empty<tr><td colspan="5" class="p-8 text-center text-slate-500">Belum ada stok.</td></tr>@endforelse</tbody></table></div>
@if($balances->hasPages())<div class="border-t px-5 py-3" style="border-color:var(--border-subtle)">{{ $balances->links() }}</div>@endif
</article>

<nav class="mt-4 no-print"><a href="/admin/inventory/lots" class="inline-flex min-h-[40px] items-center gap-2 rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]">Telusuri Lot (traceability)</a></nav>
</div>

<x-ui.drawer id="inventory-setup-drawer" title="Master Item & Gudang" description="Item baru + lokasi penyimpanan defaultnya.">
<form method="post" action="/admin/inventory/setup" class="grid gap-4">@csrf
<x-ui.form-section title="Data Master">
<div class="grid gap-4 sm:grid-cols-2"><x-ui.field label="SKU" name="sku" hint="Kode unik item" required><input name="sku" placeholder="mis. BESI-12" required class="w-full p-3"></x-ui.field><x-ui.field label="Nama barang" name="name" required><input name="name" placeholder="mis. Besi Beton 12mm" required class="w-full p-3"></x-ui.field><x-ui.field label="Kategori" name="category" required><input name="category" placeholder="mis. material" required class="w-full p-3"></x-ui.field><x-ui.field label="UOM" name="uom" hint="Satuan dasar (KG, UNIT, M3)" required><input name="uom" placeholder="KG" required class="w-full p-3"></x-ui.field><x-ui.field label="Kode gudang" name="warehouse_code" required><input name="warehouse_code" placeholder="W1" required class="w-full p-3"></x-ui.field><x-ui.field label="Nama gudang" name="warehouse_name" required><input name="warehouse_name" placeholder="Gudang Utama" required class="w-full p-3"></x-ui.field><x-ui.field label="Bin" name="bin_code" hint="Rak/lokasi fisik" required><input name="bin_code" placeholder="A1" required class="w-full p-3"></x-ui.field></div>
</x-ui.form-section>
<div class="flex justify-end gap-2 pt-2">
<button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button>
<button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan Master</button>
</div>
</form>
</x-ui.drawer>

<x-ui.drawer id="inventory-movement-drawer" title="Posting Movement" description="Semua pergerakan masuk stock ledger immutable dengan FIFO cost.">
<form method="post" action="/admin/inventory/movements" class="grid gap-4">@csrf
<x-ui.form-section title="Detail Movement">
<div class="grid gap-4 sm:grid-cols-2"><x-ui.field label="Item" name="item_id" required><select name="item_id" required class="w-full p-3">@foreach($items as $i)<option value="{{ $i->id }}">{{ $i->sku }} — {{ $i->name }}</option>@endforeach</select></x-ui.field><x-ui.field label="Gudang / Bin" name="warehouse_bin_id" required><select name="warehouse_bin_id" required class="w-full p-3">@foreach($warehouses as $w)@foreach($w->bins as $b)<option value="{{ $b->id }}">{{ $w->code }}/{{ $b->code }}</option>@endforeach @endforeach</select></x-ui.field><x-ui.field label="Jenis movement" name="movement_type"><select name="movement_type" class="w-full p-3"><option value="receipt">Penerimaan</option><option value="issue">Pengeluaran</option><option value="return_in">Pengembalian</option><option value="adjustment_in">Adjustment +</option><option value="adjustment_out">Adjustment -</option></select></x-ui.field><div class="grid grid-cols-2 gap-3"><x-ui.field label="Qty" name="quantity" required><input name="quantity" type="number" step=".0001" placeholder="0.0000" required class="w-full p-3"></x-ui.field><x-ui.field label="Unit cost" name="unit_cost" hint="Wajib untuk receipt"><input name="unit_cost" type="number" step=".0001" placeholder="0" class="w-full p-3"></x-ui.field></div><x-ui.field label="Referensi unik" name="reference_id" hint="Kunci idempotensi — duplikat ditolak" required><input name="reference_id" placeholder="mis. GR-2026-001" required class="w-full p-3"></x-ui.field><x-ui.field label="Alasan" name="reason" hint="Opsional, masuk audit"><input name="reason" placeholder="mis. terima dari PO-12" class="w-full p-3"></x-ui.field></div>
</x-ui.form-section>
<div class="flex justify-end gap-2 pt-2">
<button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button>
<button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Post Movement</button>
</div>
</form>
</x-ui.drawer>
</x-layouts.app>
