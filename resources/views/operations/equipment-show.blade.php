<x-layouts.app title="Equipment {{ $equipment->code }}"><section class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
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
</section></x-layouts.app>
