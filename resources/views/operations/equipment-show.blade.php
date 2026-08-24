<x-layouts.app title="Equipment {{ $equipment->code }}"><div class="page-container">
<x-ui.page-header title="{{ $equipment->code }} — {{ $equipment->name }}" subtitle="{{ ucfirst($equipment->ownership) }} · {{ $equipment->category }} · Hour meter {{ $equipment->current_hour_meter }} jam" status="{{ strtoupper($equipment->status) }}">
<a href="/admin/operations" class="x-ui.button secondary">← Kembali ke Operations</a>
</x-ui.page-header>

<div class="mt-6 grid gap-5 lg:grid-cols-2">
<x-ui.card label="Riwayat Hour Meter">
<ul class="space-y-1 text-sm">@forelse($meters as $log)<li class="flex justify-between rounded-lg border p-2 text-xs"><span>{{ $log->recorded_at->format('d/m/Y H:i') }}</span><span class="font-mono font-bold">{{ $log->reading }} jam</span></li>@empty<li class="text-slate-400">Belum ada catatan meter.</li>@endforelse</ul>
</x-ui.card>
<x-ui.card label="Konsumsi BBM Terakhir">
<ul class="space-y-1 text-xs">@forelse($fuels as $fuel)<li class="flex items-center justify-between rounded-lg border p-2"><span>{{ $fuel->used_at->format('d/m/Y') }} · {{ $fuel->liters }} L @ {{ $fuel->liters_per_hour }} LPH</span>@if($fuel->is_anomaly)<span class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-700">ANOMALI</span>@else<span class="text-emerald-600">normal</span>@endif</li>@empty<li class="text-slate-400">Belum ada pemakaian.</li>@endforelse</ul>
<p class="mt-2 text-[11px] text-slate-400">Anomali = LPH &gt; 120% dari target {{ $equipment->fuel_target_lph ?? '-' }} LPH.</p>
</x-ui.card>
<x-ui.card label="Biaya per Jam ({{ $costFrom->format('d/m/Y') }} — {{ $costTo->format('d/m/Y') }})" class="lg:col-span-2">
<form method="get" class="mb-3 flex flex-wrap items-end gap-2 no-print">
<div><label class="block text-[11px] font-bold text-slate-500">Dari</label><input type="date" name="cost_from" value="{{ $costFrom->toDateString() }}" class="rounded-lg border p-2 text-xs"></div>
<div><label class="block text-[11px] font-bold text-slate-500">Sampai</label><input type="date" name="cost_to" value="{{ $costTo->toDateString() }}" class="rounded-lg border p-2 text-xs"></div>
<button class="rounded-xl bg-slate-800 px-4 py-2 text-xs font-bold text-white">Terapkan</button>
</form>
@php($fmt = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.'))
<div class="grid gap-3 sm:grid-cols-4">
<div class="rounded-xl bg-slate-50 p-3 text-center"><p class="text-[11px] font-bold uppercase text-slate-500">Jam Operasi</p><p class="text-xl font-black tabular-nums">{{ $costSummary['hours'] ?? '-' }}</p></div>
<div class="rounded-xl bg-slate-50 p-3 text-center"><p class="text-[11px] font-bold uppercase text-slate-500">Total Biaya</p><p class="text-xl font-black tabular-nums">{{ $fmt($costSummary['total_cost']) }}</p></div>
<div class="rounded-xl bg-indigo-50 p-3 text-center"><p class="text-[11px] font-bold uppercase text-indigo-500">Rp / Jam</p><p class="text-xl font-black tabular-nums text-indigo-700">{{ $costSummary['cost_per_hour'] !== null ? $fmt($costSummary['cost_per_hour']) : '-' }}</p></div>
<div class="rounded-xl bg-slate-50 p-3 text-center"><p class="text-[11px] font-bold uppercase text-slate-500">Aset Tertaut</p><p class="mt-1 text-sm font-bold">{{ $equipment->fixed_asset_id ? 'YA' : 'Belum' }}</p></div>
</div>
<dl class="mt-4 grid gap-2 text-xs sm:grid-cols-3">
<div class="flex justify-between rounded-lg border p-2"><dt>BBM berharga</dt><dd class="font-mono font-bold">{{ $fmt($costSummary['fuel_cost']) }}</dd></div>
<div class="flex justify-between rounded-lg border p-2"><dt>Maintenance ditutup</dt><dd class="font-mono font-bold">{{ $fmt($costSummary['maintenance_cost']) }}</dd></div>
<div class="flex justify-between rounded-lg border p-2"><dt>Depresiasi aset</dt><dd class="font-mono font-bold">{{ $fmt($costSummary['depreciation_cost']) }}</dd></div>
</dl>
@if((float) $costSummary['unpriced_fuel_liters'] > 0)<p class="mt-2 rounded-lg bg-amber-50 p-2 text-[11px] text-amber-800">{{ number_format((float) $costSummary['unpriced_fuel_liters'], 2, ',', '.') }} L BBM belum berharga (isi harga/liter saat mencatat fuel) — tidak dikarang ke total.</p>@endif
@if($costSummary['hours'] === null)<p class="mt-2 rounded-lg bg-slate-50 p-2 text-[11px] text-slate-600">Jam operasi butuh minimal dua catatan hour meter dalam periode agar selisihnya terhitung.</p>@elseif((float) $costSummary['hours'] === 0.0)<p class="mt-2 rounded-lg bg-slate-50 p-2 text-[11px] text-slate-600">Tidak ada jam operasi tercatat pada periode ini.</p>@endif
<p class="mt-2 text-[11px] text-slate-400">Sumber: hour meter, fuel usage berharga, WO maintenance ditutup, dan depresiasi aset tetap tertaut. Semua dihitung saat halaman dibuka.</p>
</x-ui.card>
<x-ui.card label="Maintenance Work Order ({{ $workOrders->count() }})" class="lg:col-span-2">
<div class="space-y-2">@forelse($workOrders as $wo)
<div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3 text-sm">
<div><strong>{{ $wo->number }}</strong> <span class="text-xs uppercase">{{ $wo->type }}</span><p class="text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($wo->problem, 90) }}</p></div>
<span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $wo->status === 'open' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">{{ strtoupper($wo->status) }}</span>
</div>
@empty<p class="text-sm text-slate-400">Belum ada work order.</p>@endforelse
</div>
</x-ui.card>
</div>
</div></x-layouts.app>
