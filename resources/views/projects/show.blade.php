<x-layouts.app title="{{ $project->code }} — {{ $project->name }}">
@php($tabs = [
    'overview' => 'Ringkasan',
    'planning' => 'Perencanaan',
    'piles' => 'Bored Pile',
    'fieldops' => 'Field Ops',
    'materials' => 'Material',
    'procurement' => 'Procurement',
    'contracts' => 'Kontrak',
    'cost' => 'Biaya & EAC',
    'billing' => 'Billing',
    'quality' => 'Quality',
    'hse' => 'HSE',
])
<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<p class="text-xs font-bold uppercase tracking-widest text-sky-700">{{ $project->code }} Â· {{ $project->location }}</p>
<h1 class="mt-1 text-3xl font-black">{{ $project->name }}</h1>
<div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-slate-500">
<span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold uppercase">{{ str_replace('_', ' ', $project->status) }}</span>
@if($project->planned_start)<span>Rencana: {{ $project->planned_start->format('d/m/Y') }} ? {{ $project->planned_end?->format('d/m/Y') }}</span>@endif
@if($project->contract_value)<span>Kontrak: <strong class="font-mono">Rp {{ number_format((float) $project->contract_value, 0, ',', '.') }}</strong></span>@endif
</div>

<nav class="mt-6 flex gap-1 overflow-x-auto border-b no-print" aria-label="Tab proyek">
@foreach($tabs as $key => $label)
<a href="/admin/projects/{{ $project->id }}?tab={{ $key }}" @class(['whitespace-nowrap rounded-t-xl px-4 py-2.5 text-sm font-semibold', 'bg-sky-700 text-white shadow-sm' => $activeTab === $key, 'text-slate-500 hover:bg-slate-100 hover:text-slate-800' => $activeTab !== $key])>{{ $label }}</a>
@endforeach
</nav>

@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 print:hidden">{{ session('status') }}</div>@endif

@if($activeTab === 'overview')
<section class="mt-6 grid gap-5">
<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
<article class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Progress Fisik</p><p class="mt-1 text-3xl font-black">{{ number_format($physicalPercent, 1) }}%</p>@if($plannedPercent !== null)<div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-gradient-to-r from-sky-500 to-cyan-500" style="width: {{ min(100, $physicalPercent) }}%"></div></div><p class="mt-1 text-xs text-slate-500">Rencana s.d. hari ini: {{ number_format($plannedPercent, 1) }}%</p>@endif</article>
<article class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Titik Pile</p><p class="mt-1 text-3xl font-black">{{ $piles->count() }}</p><p class="text-xs text-slate-500">Selesai: {{ $piles->where('status', 'completed')->count() }}</p></article>
@if($costing)
<article class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">EAC (Estimasi Akhir)</p><p class="mt-1 text-xl font-black">Rp {{ number_format((float) $costing['eac'], 0, ',', '.') }}</p><p class="text-xs {{ (float) $costing['variance'] < 0 ? 'text-red-600 font-bold' : 'text-emerald-700' }}">Varians vs RAB: Rp {{ number_format((float) $costing['variance'], 0, ',', '.') }}</p></article>
<article class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Margin Estimasi</p>@php($margin = (float) $project->contract_value > 0 ? round((float) bcdiv(bcmul(bcsub((string) $project->contract_value, $costing['eac'], 2), '100', 4), (string) $project->contract_value, 4), 1) : null)<p class="mt-1 text-3xl font-black {{ ($margin ?? 0) < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $margin !== null ? $margin.'%' : '-' }}</p></article>
@endif
</div>
<div class="grid gap-5 lg:grid-cols-3">
<article class="rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">Funnel Bored Pile</h2><div class="mt-3 space-y-2">@foreach(['planned', 'drilling', 'cage_installation', 'concreting', 'testing', 'completed'] as $stage)
@php($count = $piles->where('status', $stage)->count())
<div class="flex items-center justify-between text-sm"><span class="capitalize">{{ str_replace('_', ' ', $stage) }}</span><span class="font-mono font-bold">{{ $count }}</span></div>
@endforeach</div></article>
<article class="lg:col-span-2 rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto"><h2 class="font-bold">Zona & Ringkasan Pile</h2>
<table class="mt-3 w-full text-sm table-sticky"><thead><tr><th>Zona</th><th>Jml Pile</th><th>Selesai</th><th>Berjalan</th></tr></thead><tbody>@foreach($piles->groupBy('zone.code') as $zoneCode => $zonePiles)
<tr><td>{{ $zoneCode }}</td><td>{{ $zonePiles->count() }}</td><td>{{ $zonePiles->where('status', 'completed')->count() }}</td><td>{{ $zonePiles->whereNotIn('status', ['completed'])->count() }}</td></tr>
@endforeach</tbody></table></article>
</div>
</section>
@endif

@if($activeTab === 'planning')
<section class="mt-6">@if(isset($schedule))
@include('projects.partials.schedule', ['schedule' => $schedule])
@else<p class="text-sm text-slate-500">Jadwal belum tersedia.</p>@endif</section>
@endif

@if($activeTab === 'piles')
<section class="mt-6 overflow-x-auto rounded-2xl border bg-white">
<table class="w-full text-sm table-sticky"><thead><tr><th>Pile</th><th>Zona</th><th>Diameter</th><th>Depth</th><th>Beton</th><th>Status</th><th>Genealogi</th></tr></thead><tbody>@forelse($piles as $pile)
<tr><td class="font-mono font-bold">{{ $pile->pile_number }}</td><td>{{ $pile->zone?->code }}</td><td>{{ $pile->diameter_mm }} mm</td><td>{{ $pile->actual_depth_m ?? $pile->planned_depth_m }} m</td><td>{{ $pile->actual_concrete_m3 ?? '-' }} mÂ³</td><td>{{ str_replace('_', ' ', strtoupper($pile->status)) }}</td><td><a href="{{ route('piles.genealogy', $pile) }}" class="font-bold text-sky-700 hover:underline">Genealogi</a> · <a href="{{ route('piles.as-built', $pile) }}" class="text-slate-500 hover:underline">PDF</a></td></tr>
@empty<tr><td colspan="7" class="p-8 text-center text-slate-500">Belum ada titik bored pile pada proyek ini.</td></tr>@endforelse
</tbody></table>
</section>
@endif

@if($activeTab === 'fieldops')
<section class="mt-6 space-y-6">
<article class="overflow-x-auto rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">Drilling Terakhir</h2><table class="mt-3 w-full text-sm table-sticky"><thead><tr><th>Pile</th><th>Mulai</th><th>Selesai</th><th>Alat</th></tr></thead><tbody>@forelse($drillings ?? [] as $drilling)
<tr><td>Pile #{{ $drilling->bored_pile_id }}</td><td>{{ $drilling->drilling_started_at?->format('d/m H:i') }}</td><td>{{ $drilling->drilling_finished_at?->format('d/m H:i') ?? '-' }}</td><td>{{ $drilling->drilling_tool ?? '-' }}</td></tr>
@empty<tr><td colspan="4" class="p-6 text-center text-slate-500">Belum ada drilling record.</td></tr>@endforelse</tbody></table></article>
<article class="overflow-x-auto rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">Delivery Beton</h2><table class="mt-3 w-full text-sm table-sticky"><thead><tr><th>DO</th><th>Truk</th><th>Tiba</th></tr></thead><tbody>@forelse($deliveries ?? [] as $delivery)
<tr><td class="font-mono text-xs">{{ $delivery->delivery_order_number }}</td><td>{{ $delivery->truck_number }}</td><td>{{ $delivery->arrived_at?->format('d/m H:i') ?? '-' }}</td></tr>
@empty<tr><td colspan="3" class="p-6 text-center text-slate-500">Belum ada delivery beton.</td></tr>@endforelse</tbody></table></article>
<article class="overflow-x-auto rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">Pile Testing</h2><table class="mt-3 w-full text-sm table-sticky"><thead><tr><th>Nomor</th><th>Jenis</th><th>Jadwal</th><th>Hasil</th></tr></thead><tbody>@forelse($tests ?? [] as $test)
<tr><td class="font-mono text-xs">{{ $test->number }}</td><td>{{ strtoupper($test->test_type) }}</td><td>{{ $test->scheduled_date?->format('d/m/Y') }}</td><td>{{ strtoupper($test->result_status) }}</td></tr>
@empty<tr><td colspan="4" class="p-6 text-center text-slate-500">Belum ada jadwal testing.</td></tr>@endforelse</tbody></table></article>
</section>
@endif

@if($activeTab === 'materials' && isset($materialRequests))
<section class="mt-6 overflow-x-auto rounded-2xl border bg-white">
<table class="w-full text-sm table-sticky"><thead><tr><th>Nomor</th><th>Gudang</th><th>Status</th><th>Tanggal</th></tr></thead><tbody>@forelse($materialRequests ?? [] as $mr)
<tr><td class="font-mono text-xs">{{ $mr->number }}</td><td>{{ $mr->warehouse?->name }}</td><td>{{ strtoupper($mr->status) }}</td><td>{{ $mr->created_at->format('d/m/Y') }}</td></tr>
@empty<tr><td colspan="4" class="p-8 text-center text-slate-500">Belum ada permintaan material untuk proyek ini.</td></tr>@endforelse
</tbody></table>
</section>
@endif

@if($activeTab === 'procurement' && isset($purchaseOrders))
<section class="mt-6 space-y-6">
<article class="overflow-x-auto rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">Purchase Order Proyek</h2><table class="mt-3 w-full text-sm table-sticky"><thead><tr><th>PO</th><th>Vendor</th><th class="text-right">Nilai</th><th>Status</th><th>Genealogi</th></tr></thead><tbody>@forelse($purchaseOrders ?? [] as $po)
<tr><td class="font-mono text-xs">{{ $po->number }} v{{ $po->version }}</td><td>{{ $po->vendor?->name }}</td><td class="text-right font-mono">{{ number_format((float) $po->total, 0, ',', '.') }}</td><td>{{ strtoupper(str_replace('_', ' ', $po->status)) }}</td></tr>
@empty<tr><td colspan="4" class="p-6 text-center text-slate-500">Belum ada PO untuk proyek ini.</td></tr>@endforelse</tbody></table></article>
<article class="overflow-x-auto rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">RFQ Proyek</h2><table class="mt-3 w-full text-sm table-sticky"><thead><tr><th>Nomor</th><th>Judul</th><th>Vendor Diundang</th><th>Status</th><th>Genealogi</th></tr></thead><tbody>@forelse($rfqs ?? [] as $rfq)
<tr><td class="font-mono text-xs">{{ $rfq->number }}</td><td>{{ $rfq->title }}</td><td>{{ $rfq->vendors_count }}</td><td>{{ strtoupper($rfq->status) }}</td></tr>
@empty<tr><td colspan="4" class="p-6 text-center text-slate-500">Belum ada RFQ.</td></tr>@endforelse</tbody></table></article>
</section>
@endif

@if($activeTab === 'contracts' && isset($contractChanges))
<section class="mt-6 overflow-x-auto rounded-2xl border bg-white">
<table class="w-full text-sm table-sticky"><thead><tr><th>Nomor</th><th>Jenis</th><th>Judul</th><th class="text-right">Nilai</th><th>Status</th><th>Genealogi</th></tr></thead><tbody>@forelse($contractChanges ?? [] as $change)
<tr onclick="location.href='/admin/contracts/{{ $change->id }}'" class="cursor-pointer hover:bg-slate-50 dark:hover:!bg-slate-800"><td class="font-mono text-xs">{{ $change->number }}</td><td>{{ \App\Models\ContractChange::TYPES[$change->type] ?? $change->type }}</td><td class="max-w-[220px] truncate">{{ $change->title }}</td><td class="text-right font-mono">{{ number_format((float) $change->amount, 0, ',', '.') }}</td><td>{{ str_replace('_', ' ', $change->status) }}</td></tr>
@empty<tr><td colspan="5" class="p-8 text-center text-slate-500">Belum ada perubahan kontrak. Kelola di menu Komersial ? Administrasi Kontrak.</td></tr>@endforelse
</tbody></table>
</section>
@endif

@if($activeTab === 'cost' && isset($costByCode))
<section class="mt-6 grid gap-5 lg:grid-cols-3">
@if(isset($costing))
<article class="rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">Cockpit Biaya</h2><dl class="mt-3 space-y-2 text-sm"><div class="flex justify-between"><dt>RAB</dt><dd class="font-mono">{{ number_format((float) $costing['budget'], 0, ',', '.') }}</dd></div><div class="flex justify-between"><dt>Aktual</dt><dd class="font-mono">{{ number_format((float) $costing['actual'], 0, ',', '.') }}</dd></div><div class="flex justify-between"><dt>Komitmen PO</dt><dd class="font-mono">{{ number_format((float) $costing['committed'], 0, ',', '.') }}</dd></div><div class="flex justify-between"><dt>CTC</dt><dd class="font-mono">{{ number_format((float) $costing['cost_to_complete'], 0, ',', '.') }}</dd></div><div class="flex justify-between font-bold"><dt>EAC</dt><dd class="font-mono">{{ number_format((float) $costing['eac'], 0, ',', '.') }}</dd></div></dl></article>
@endif
<article class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto lg:col-span-2"><h2 class="font-bold">Realisasi per Cost Code</h2><table class="mt-3 w-full text-sm table-sticky"><thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th class="text-right">Nilai</th></tr></thead><tbody>@forelse($costByCode ?? [] as $row)
<tr><td class="font-mono text-xs">{{ $row->code }}</td><td>{{ $row->name }}</td><td>{{ $row->cost_type }}</td><td class="text-right font-mono">{{ number_format((float) $row->total, 0, ',', '.') }}</td></tr>
@empty<tr><td colspan="4" class="p-6 text-center text-slate-500">Belum ada realisasi biaya tercatat di ledger.</td></tr>@endforelse</tbody></table></article>
</section>
@endif

@if($activeTab === 'billing' && isset($billings))
<section class="mt-6 overflow-x-auto rounded-2xl border bg-white">
<table class="w-full text-sm table-sticky"><thead><tr><th>Nomor</th><th>Tanggal</th><th class="text-right">Bruto</th><th class="text-right">Retensi</th><th>Status</th><th>Genealogi</th></tr></thead><tbody>@forelse($billings ?? [] as $billing)
<tr><td class="font-mono text-xs">{{ $billing->number }}</td><td>{{ $billing->billing_date?->format('d/m/Y') }}</td><td class="text-right font-mono">{{ number_format((float) $billing->gross_amount, 0, ',', '.') }}</td><td class="text-right font-mono">{{ number_format((float) $billing->retention_amount, 0, ',', '.') }}</td><td>{{ strtoupper($billing->status) }}</td></tr>
@empty<tr><td colspan="5" class="p-8 text-center text-slate-500">Belum ada progress billing.</td></tr>@endforelse
</tbody></table>
</section>
@endif

@if($activeTab === 'quality' && isset($ncrs))
<section class="mt-6 space-y-3">
@forelse($ncrs ?? [] as $ncr)
<article class="rounded-2xl border bg-white p-5 shadow-sm"><div class="flex flex-wrap items-center justify-between gap-2"><strong>{{ $ncr->number }}</strong><span class="text-xs uppercase text-slate-500">{{ $ncr->source_type }} Â· severitas {{ $ncr->severity }}</span><x-ui.badge :status="$ncr->status === 'closed' ? 'posted' : 'pending_approval'" :label="$ncr->status" /></div><p class="mt-2 text-sm text-slate-600">{{ $ncr->description }}</p>
@if($ncr->actions->isNotEmpty())<ul class="mt-2 space-y-1 text-xs text-slate-500">@foreach($ncr->actions as $action)<li>? {{ str($action->action)->limit(90) }} — {{ $action->status }} @if($action->due_at)(tenggat {{ $action->due_at->format('d/m/Y') }})@endif</li>@endforeach</ul>@endif</article>
@empty
<x-ui.empty icon="shield" title="Tidak ada NCR pada proyek ini" description="Kualitas pekerjaan dalam kendali." />
@endforelse
</section>
@endif

@if($activeTab === 'hse' && isset($incidents))
<section class="mt-6 space-y-3">
@forelse($incidents ?? [] as $incident)
<article class="rounded-2xl border bg-white p-5 shadow-sm"><div class="flex flex-wrap items-center justify-between gap-2"><strong>{{ $incident->number }}</strong><span class="text-xs uppercase text-slate-500">{{ $incident->type }} Â· {{ $incident->severity }} Â· {{ $incident->occurred_at?->format('d/m/Y H:i') }}</span><x-ui.badge :status="$incident->status === 'closed' ? 'posted' : 'exception'" :label="$incident->status" /></div><p class="mt-2 text-sm text-slate-600">{{ $incident->description }}</p></article>
@empty
<x-ui.empty icon="triangle-alert" title="Tidak ada incident tercatat" description="Riwayat HSE proyek ini bersih." />
@endforelse
</section>
@endif
</section>
</x-layouts.app>

