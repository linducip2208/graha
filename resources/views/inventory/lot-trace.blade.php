<x-layouts.app title="Lot Traceability"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-2xl font-bold tracking-tight">Material Traceability per Lot</h1>
<p class="mt-1 text-sm text-slate-500">Telusuri satu lot: dari penerimaan (GR/vendor) hingga konsumsi (proyek/pile/produksi/cage). Ledger immutable — jejak tidak dapat diubah.</p>
<form method="get" action="/admin/inventory/lots" class="mt-5 flex flex-wrap gap-2 no-print">
<input name="lot" value="{{ $lot }}" required placeholder="Nomor lot / heat (mis. HEAT-2026-01)" class="min-w-64 flex-1 rounded-xl border p-3 font-mono text-sm">
<button class="rounded-xl bg-[var(--brand-primary)] px-6 py-3 font-bold text-white">Telusuri</button>
</form>

@if($lot !== '')
<div class="mt-6 overflow-x-auto rounded-2xl border bg-white">
<table class="w-full text-sm table-sticky"><thead><tr><th>#</th><th>Waktu</th><th>Item</th><th>Jenis</th><th class="text-right">Qty</th><th class="text-right">Saldo</th><th>Gudang/Bin</th><th>Sumber/Tujuan</th><th>Pile</th></tr></thead><tbody>
@forelse($movements as $m)
<tr class="border-t"><td class="font-mono text-xs">{{ $m->id }}</td><td>{{ $m->posted_at?->format('d/m/Y H:i') ?? '-' }}</td><td>{{ $m->item?->sku }}</td><td><span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ str_starts_with($m->movement_type, 'adjustment') ? 'bg-amber-50 text-amber-700' : ($m->quantity > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-sky-50 text-[var(--brand-primary)]') }}">{{ strtoupper($m->movement_type) }}</span></td><td class="text-right font-mono {{ $m->quantity < 0 ? 'text-red-600' : '' }}">{{ $m->quantity }}</td><td class="text-right font-mono">{{ $m->balance_after }}</td><td>{{ $m->warehouse?->code }}/{{ $m->bin?->code }}</td><td>{{ $m->source_label }}</td><td>{{ $m->pile_number ?? '-' }}</td></tr>
@empty
<tr><td colspan="9" class="p-8 text-center text-slate-400">Tidak ada pergerakan untuk lot "{{ $lot }}" di perusahaan ini.</td></tr>
@endforelse
</tbody></table>
</div>
@if($movements->isNotEmpty())
<p class="mt-2 text-xs text-slate-400">Total {{ $movements->count() }} pergerakan · qty masuk {{ $movements->where('quantity','>',0)->sum('quantity') }} · qty keluar {{ abs($movements->where('quantity','<',0)->sum('quantity')) }}.</p>
@endif
@endif
</section></x-layouts.app>
