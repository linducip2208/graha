{{-- Pour curve, geometri lubang & deviasi survey (ADR-075) — dari data nyata, tanpa interpolasi fiktif. --}}
@php
$curve = app(\App\Services\PourCurveService::class)->curve($pile);
$survey = app(\App\Services\PileSurveyService::class)->deviation($pile);
$geometry = $pile->geometryReadings()->get();
@endphp
<x-ui.card class="mt-4">
<h2 class="font-black">Concrete Pour Curve</h2>
@if(count($curve['points']) === 0)
<p class="mt-2 text-sm text-slate-400">Belum ada data interval — kurva hanya digambar dari interval tercatat, tanpa interpolasi.</p>
@else
<div class="mt-3 overflow-x-auto">
<svg viewBox="0 0 640 260" class="min-w-[520px] w-full" role="img" aria-label="Kurva pour beton">
    <line x1="48" y1="220" x2="600" y2="220" stroke="#cbd5e1"/>
    <line x1="48" y1="20" x2="48" y2="220" stroke="#cbd5e1"/>
    <text x="10" y="30" font-size="11" fill="#94a3b8">m³</text>
    <text x="560" y="238" font-size="11" fill="#94a3b8">kedalaman m</text>
    @php
        $maxDepth = max(0.001, (float) collect($curve['points'])->max('depth'));
        $maxVol = max(0.001, max((float) $curve['total_theoretical'], (float) $curve['total_actual']));
        $toX = fn ($d) => 48 + 552 * ((float) $d / $maxDepth);
        $toY = fn ($v) => 220 - 190 * ((float) $v / $maxVol);
    @endphp
    <polyline fill="none" stroke="#2563eb" stroke-width="2" stroke-dasharray="6 4"
        points="{{ collect($curve['points'])->map(fn ($p) => round($toX($p['depth']), 1).','.$toY($p['theoretical']))->implode(' ') }}"/>
    <polyline fill="none" stroke="#059669" stroke-width="2"
        points="{{ collect($curve['points'])->filter(fn ($p) => $p['actual'] !== null)->map(fn ($p) => round($toX($p['depth']), 1).','.$toY($p['actual']))->implode(' ') }}"/>
</svg>
</div>
<p class="mt-1 text-xs text-slate-500"><span class="font-bold text-blue-700">— Teoretis</span> · <span class="font-bold text-emerald-700">— Aktual kumulatif</span> · total aktual {{ $curve['total_actual'] }} m³ vs teoretis {{ number_format($curve['total_theoretical'], 3) }} m³</p>
<table class="mt-3 w-full min-w-[480px] text-xs">
<thead><tr><th>Kedalaman (m)</th><th>Teoretis (m³)</th><th>Aktual kum. (m³)</th><th>Varian %</th></tr></thead>
<tbody>
@foreach(collect($curve['points'])->take(24) as $point)
<tr class="border-t {{ $point['overconsumed'] ? 'bg-red-50' : '' }}">
<td class="font-mono">{{ $point['depth'] }}</td><td class="font-mono">{{ $point['theoretical'] }}</td><td class="font-mono">{{ $point['actual'] }}</td>
<td class="font-mono font-bold {{ ($point['variance_percent'] ?? 0) > 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $point['variance_percent'] ?? '-' }}{{ $point['overconsumed'] ? ' ⚠' : '' }}</td>
</tr>
@endforeach
</tbody>
</table>
@if(collect($curve['points'])->count() > 24)<p class="text-xs text-slate-400">Menampilkan 24 titik pertama dari {{ count($curve['points']) }} interval.</p>@endif
@endif

@can('project.manage')
<details class="mt-4 rounded-xl border p-3 no-print">
    <summary class="cursor-pointer text-sm font-bold">+ Rekam Interval Pour</summary>
    <form method="post" action="{{ route('field-ops.pour-interval.store') }}" class="mt-3 grid gap-3 sm:grid-cols-4">
        @csrf
        <input type="hidden" name="bored_pile_id" value="{{ $pile->id }}">
        <label class="text-xs font-bold uppercase text-slate-500">Waktu<input type="datetime-local" name="recorded_at" required class="mt-1 w-full rounded-xl border-stone-300 text-sm"></label>
        <label class="text-xs font-bold uppercase text-slate-500">Kedalaman/Level (m)<input type="number" step=".001" name="depth_or_level_m" required min="0" class="mt-1 w-full rounded-xl border-stone-300 text-sm"></label>
        <label class="text-xs font-bold uppercase text-slate-500">Volume inkremental (m³)<input type="number" step=".0001" name="incremental_volume_m3" required min="0" class="mt-1 w-full rounded-xl border-stone-300 text-sm"></label>
        <div class="flex items-end"><button class="min-h-[44px] rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Simpan Interval</button></div>
    </form>
</details>
@endcan
</x-ui.card>

<x-ui.card class="mt-4">
<h2 class="font-black">Hole Geometry / Caliper</h2>
@if($geometry->isEmpty())
<p class="mt-2 text-sm text-slate-400">Belum ada pembacaan geometri lubang.</p>
@else
<table class="mt-3 w-full min-w-[560px] text-xs">
<thead><tr><th>Kedalaman (m)</th><th>Diameter ukur (mm)</th><th>Dev X (mm)</th><th>Dev Y (mm)</th><th>Vertikalitas (%)</th><th>Sumber</th></tr></thead>
<tbody>@foreach($geometry as $reading)
<tr class="border-t">
<td class="font-mono">{{ $reading->depth_m }}</td><td class="font-mono">{{ $reading->measured_diameter_mm ?? '-' }}</td>
<td class="font-mono">{{ $reading->deviation_x_mm ?? '-' }}</td><td class="font-mono">{{ $reading->deviation_y_mm ?? '-' }}</td>
<td class="font-mono">{{ $reading->verticality_percent ?? '-' }}</td>
<td><span class="rounded-md bg-slate-100 px-2 py-0.5 font-semibold">{{ $reading->source }}</span></td>
</tr>
@endforeach</tbody>
</table>
@endif
@can('project.manage')
<details class="mt-3 rounded-xl border p-3 no-print">
    <summary class="cursor-pointer text-sm font-bold">+ Import CSV Geometri (depth,diameter,dev_x,dev_y,verticality)</summary>
    <form method="post" action="{{ route('field-ops.geometry.import') }}" class="mt-3 grid gap-3 sm:grid-cols-3">
        @csrf
        <input type="hidden" name="bored_pile_id" value="{{ $pile->id }}">
        <label class="text-xs font-bold uppercase text-slate-500">Sumber
            <select name="source" required class="mt-1 w-full rounded-xl border-stone-300 text-sm">@foreach(\App\Models\PileGeometryReading::SOURCES as $src)<option value="{{ $src }}">{{ str($src)->replace('_', ' ') }}</option>@endforeach</select>
        </label>
        <label class="text-xs font-bold uppercase text-slate-500 sm:col-span-2">Data CSV<textarea name="csv" rows="4" required placeholder="depth,diameter,dev_x,dev_y,vertikalitas&#10;2,1010,5,-3,0.12&#10;6,1008,8,-6,0.18" class="mt-1 w-full rounded-xl border-stone-300 font-mono text-xs"></textarea></label>
        <div class="sm:col-span-3"><button class="min-h-[44px] rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Import CSV</button></div>
    </form>
</details>
@endcan
</x-ui.card>

<x-ui.card class="mt-4">
<h2 class="font-black">Survey Deviation</h2>
<div class="mt-2 grid gap-3 sm:grid-cols-4">
    <div><dt class="text-xs uppercase text-slate-400">Deviasi Horizontal</dt><dd class="font-mono font-bold">{{ $survey['horizontal_deviation_m'] !== null ? $survey['horizontal_deviation_m'].' m' : '-' }}</dd></div>
    <div><dt class="text-xs uppercase text-slate-400">Deviasi Elevasi Top</dt><dd class="font-mono font-bold">{{ $survey['elevation_deviation_m'] !== null ? $survey['elevation_deviation_m'].' m' : '-' }}</dd></div>
    <div><dt class="text-xs uppercase text-slate-400">Deviasi Cut-off</dt><dd class="font-mono font-bold">{{ $survey['cutoff_deviation_m'] !== null ? $survey['cutoff_deviation_m'].' m' : '-' }}</dd></div>
    <div><dt class="text-xs uppercase text-slate-400">Status (toleransi {{ $survey['tolerance_m'] }} m)</dt>
        <dd><span class="rounded-md px-2 py-1 text-[11px] font-bold uppercase {{ ['PASS' => 'bg-emerald-100 text-emerald-800', 'WARNING' => 'bg-amber-100 text-amber-800', 'OUT_OF_TOLERANCE' => 'bg-red-100 text-red-700'][$survey['status']] ?? 'bg-slate-100 text-slate-600' }}">{{ str($survey['status'])->replace('_', ' ') }}</span></dd>
    </div>
</div>
<p class="mt-2 text-[11px] text-slate-400">Status adalah indikator deterministik atas toleransi perusahaan — disposisi engineering tetap diputuskan manusia. Data sumber manual/import tidak dilabeli certified survey.</p>
</x-ui.card>
