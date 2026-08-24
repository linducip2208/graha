<x-layouts.app title="Genealogi {{ $pile->pile_number }} — {{ $pile->project->code }}">
<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<header class="flex flex-wrap items-start justify-between gap-3">
<div>
<h1 class="text-2xl font-bold tracking-tight">Genealogi {{ $pile->pile_number }}</h1>
<p class="mt-1 text-sm text-slate-500">{{ $pile->project->code }} — {{ $pile->project->name }} · Zona {{ $pile->zone?->name ?? '-' }} · Ø{{ $pile->diameter_mm }} mm</p>
</div>
<div class="flex flex-wrap gap-2 no-print">
<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase text-slate-700">{{ str($pile->status)->replace('_',' ') }}</span>
<a href="{{ route('piles.as-built', $pile) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Unduh As-Built PDF</a>
<a href="{{ route('piles.as-built.batch', $pile->project_id) }}" class="rounded-xl border px-4 py-2 text-sm font-semibold">Batch semua pile</a>
</div>
</header>

@if($anomalies)
<div class="mt-5 grid gap-2 sm:grid-cols-2">
@foreach($anomalies as $flag)
<div class="rounded-xl border p-3 text-sm {{ $flag['severity'] === 'critical' ? 'border-red-300 bg-red-50 text-red-800' : 'border-amber-300 bg-amber-50 text-amber-800' }}">
<strong class="font-bold">{{ str($flag['code'])->replace('_',' ')->title() }}:</strong> {{ $flag['detail'] }}
</div>
@endforeach
</div>
@else
<p class="mt-5 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">Tidak ada anomali terdeteksi pada data yang tersedia.</p>
@endif

<div class="mt-6 grid gap-4 lg:grid-cols-4">
<x-ui.stat-card label="Kedalaman Rencana" value="{{ $pile->planned_depth_m }} m" hint="Aktual {{ $pile->actual_depth_m ?? '-' }} m" />
<x-ui.stat-card label="Beton Teoretis" value="{{ $pile->theoretical_concrete_m3 ?? '-' }} m³" hint="Aktual {{ $pile->actual_concrete_m3 ?? '-' }} m³" />
<x-ui.stat-card label="Overbreak" value="{{ $pile->overbreak_percent ?? 0 }}%" hint="{{ $pile->overbreak_exceeded ? 'Melebihi toleransi' : 'Dalam toleransi' }}" />
<x-ui.stat-card label="Cage / Casing" value="{{ $cages->count() }} / {{ $casings->count() }}" hint="Terkirim ke titik ini" />
</div>

<div class="mt-8 grid gap-6 xl:grid-cols-2">
<x-ui.card>
<h2 class="font-black">Bore Log & Drilling</h2>
@foreach($drillings as $drilling)
<details class="mt-2 rounded-xl border p-3" @if($loop->first) open @endif>
<summary class="cursor-pointer text-sm font-bold">{{ $drilling->drilling_started_at?->format('d/m/Y H:i') ?? '-' }} — {{ strtoupper($drilling->status) }} oleh {{ $drilling->recorder?->name }}{{ $drilling->verifier ? ' · diverifikasi '.$drilling->verifier->name : '' }}</summary>
<table class="mt-2 w-full text-xs"><thead><tr><th>Dari (m)</th><th>Ke (m)</th><th>Deskripsi Tanah</th></tr></thead><tbody>
@foreach($drilling->layers as $layer)<tr class="border-t"><td>{{ $layer->depth_from_m }}</td><td>{{ $layer->depth_to_m }}</td><td>{{ $layer->soil_description }}</td></tr>@endforeach
</tbody></table>
</details>
@endforeach
@if($drillings->isEmpty())<p class="mt-2 text-sm text-slate-400">Belum ada drilling record.</p>@endif
</x-ui.card>

<x-ui.card>
<h2 class="font-black">Delivery Beton & Slump</h2>
<div class="mt-2 overflow-x-auto"><table class="w-full text-xs"><thead><tr><th>DO</th><th>Truk</th><th>Tiba</th><th>Terdima</th><th>Ditolak</th><th>Slump</th><th>Sampel</th><th>Status</th></tr></thead><tbody>
@foreach($deliveries as $d)<tr class="border-t"><td>{{ $d->delivery_order_number }}</td><td>{{ $d->truck_number }}</td><td>{{ optional($d->arrived_at)->format('d/m H:i') ?? '-' }}</td><td>{{ $d->accepted_volume_m3 }}</td><td>{{ $d->rejected_volume_m3 }}</td><td>{{ $d->slump_cm ?? '-' }}</td><td>{{ $d->sample_number ?? '-' }}</td><td>{{ $d->status }}</td></tr>@endforeach
@if($deliveries->isEmpty())<tr><td colspan="8" class="p-3 text-center text-slate-400">Belum ada delivery.</td></tr>@endif
</tbody></table></div>
</x-ui.card>

<x-ui.card>
<h2 class="font-black">Pengujian Pile</h2>
<ul class="mt-2 space-y-1 text-sm">
@forelse($tests as $t)<li class="rounded-xl border p-2 text-xs"><strong>{{ $t->test_type }}</strong> {{ $t->number }} · jadwal {{ optional($t->scheduled_date)->format('d/m/y') }} · hasil <span class="font-bold uppercase">{{ $t->result_status }}</span>@if($t->report_number) · laporan {{ $t->report_number }}@endif{{ $t->consultant_approved_at ? ' · disetujui konsultan' : '' }}</li>
@empty<li class="text-slate-400">Belum ada pengujian.</li>@endforelse
</ul>
</x-ui.card>

<x-ui.card>
<h2 class="font-black">Cage & Casing</h2>
<ul class="mt-2 space-y-1 text-xs">
@foreach($cages as $cage)<li class="rounded-xl border p-2"><strong>{{ $cage->number }}</strong> · QC {{ strtoupper($cage->qc_status) }} · berat {{ $cage->theoretical_weight_kg ?? '-' }}/{{ $cage->actual_weight_kg ?? '-' }} kg · terkirim {{ optional($cage->delivered_at)->format('d/m/y') }}</li>
@endforeach
@foreach($casings as $cs)<li class="rounded-xl border p-2"><strong>Casing {{ $cs->code }}</strong> · {{ $cs->ownership === 'owned' ? 'milik' : 'sewa' }} · siklus {{ $cs->usage_cycle_count }}× · kondisi {{ $cs->condition ?? '-' }}</li>
@endforeach
@if($cages->isEmpty() && $casings->isEmpty())<li class="text-slate-400">Belum ada cage/casing di titik ini.</li>@endif
</ul>
</x-ui.card>

<x-ui.card>
<h2 class="font-black">Foto Evidence</h2>
<div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
@forelse($evidences as $ev)
<a href="{{ route('evidence.file', $ev) }}" target="_blank" class="group relative"><img src="{{ route('evidence.file', $ev) }}" alt="{{ $ev->original_name }}" class="h-20 w-full rounded-lg border object-cover"><span class="absolute bottom-1 left-1 rounded bg-black/60 px-1 text-[9px] text-white">{{ \App\Models\FieldEvidence::LABELS[$ev->evidence_type] ?? $ev->evidence_type }}</span></a>
@empty<p class="col-span-4 text-sm text-slate-400">Belum ada evidence.</p>@endforelse
</div>
</x-ui.card>

<x-ui.card>
<h2 class="font-black">Linimasa Aktivitas</h2>
<ol class="mt-2 space-y-1 text-xs">
@foreach($activities as $a)<li class="flex flex-wrap items-center gap-2 rounded-lg border p-2"><span class="font-mono">{{ optional($a->started_at)->format('d/m H:i') ?? '?' }}</span><span class="font-bold">{{ str($a->from_status)->replace('_',' ')->title() }} → {{ str($a->to_status)->replace('_',' ')->title() }}</span>@if($a->finished_at)<span class="text-emerald-700">selesai {{ $a->finished_at->format('H:i') }}</span>
@endif
@if($a->notes)<span class="text-slate-400">{{ \Illuminate\Support\Str::limit($a->notes, 60) }}</span>@endif</li>
@endforeach
@if($activities->isEmpty())<li class="text-slate-400">Belum ada aktivitas tercatat.</li>@endif
</ol>
</x-ui.card>
</div>
</section></x-layouts.app>
