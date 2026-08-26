<x-layouts.app title="Foundation Control — {{ $project->code }}">
<div class="page-container">
<x-ui.page-header docs="/docs/bored-pile/foundation-control-tower" title="Foundation Control Tower" subtitle="{{ $project->code }} — {{ $project->name }}" status="{{ str($project->status)->replace('_',' ') }}">
<div class="flex flex-wrap gap-2 no-print">
<a href="{{ route('projects.show', $project) }}" class="rounded-xl border px-4 py-2 text-sm font-semibold">Project Detail</a>
<a href="{{ route('field-ops.index') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold">Field Ops</a>
</div>
</x-ui.page-header>

{{-- Cards distribusi status --}}
<div class="mt-6 grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-7">
    @foreach(['planned','setting_out','drilling','cleaning','inspection','cage_installation','casting'] as $s)
    <div class="rounded-2xl border bg-white p-3 shadow-sm"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ str($s)->replace('_',' ') }}</p><p class="text-xl font-black tabular-nums">{{ $statusCounts[$s] ?? 0 }}</p></div>
    @endforeach
    @foreach(['testing','completed','hold','rework','rejected'] as $s)
    <div class="rounded-2xl border bg-white p-3 shadow-sm"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">{{ str($s)->replace('_',' ') }}</p><p class="text-xl font-black tabular-nums {{ in_array($s, ['hold','rework','rejected']) ? 'text-red-600' : '' }}">{{ $statusCounts[$s] ?? 0 }}</p></div>
    @endforeach
    <div class="rounded-2xl border bg-emerald-50 p-3 shadow-sm"><p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Accepted</p><p class="text-xl font-black tabular-nums text-emerald-800">{{ $acceptedCount }}</p></div>
    <div class="rounded-2xl border bg-white p-3 shadow-sm"><p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Total Pile</p><p class="text-xl font-black tabular-nums">{{ $total }}</p></div>
</div>

{{-- KPI --}}
<h2 class="mt-8 font-black">KPI Produksi & Kualitas</h2>
<div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5">
    <x-ui.stat-card label="Pile Dimulai Hari Ini" value="{{ $kpi['started_today'] }}" />
    <x-ui.stat-card label="Pile Selesai Hari Ini" value="{{ $kpi['completed_today'] }}" />
    <x-ui.stat-card label="Meter Drilling Hari Ini" value="{{ number_format($kpi['meters_today'], 2) }} m" />
    <x-ui.stat-card label="Beton Disetujui Hari Ini" value="{{ number_format($kpi['concrete_today'], 2) }} m³" />
    <x-ui.stat-card label="Rig Aktif" value="{{ $kpi['rigs_active'] }} / {{ $kpi['rigs_total'] }}" hint="terpakai di proyek" />
    <x-ui.stat-card label="Avg Overbreak" value="{{ $kpi['avg_overbreak'] }}%" />
    <x-ui.stat-card label="Test Pass Rate" value="{{ $kpi['test_pass_rate'] === null ? '-' : $kpi['test_pass_rate'].'%' }}" hint="{{ $kpi['tests_pending'] }} uji menunggu hasil" />
    <x-ui.stat-card label="Avg Cycle Time" value="{{ $kpi['avg_cycle_hours'] ?? '-' }} jam" hint="rata-rata pile selesai aktivitas" />
    <div class="card-lift rounded-2xl border bg-red-50 p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-red-700">NCR Terbuka (proyek)</p><p class="mt-2 text-2xl font-black tabular-nums text-red-800">{{ $openNcrCount }}</p></div>
</div>

{{-- Advanced KPI (ADR-076/077): klik KPI → filter tabel pile --}}
<h2 class="mt-8 font-black">Advanced Control Tower</h2>
<div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
    <a href="{{ route('projects.foundation-control', ['project' => $project, 'filter' => 'ready_drill']) }}" class="card-lift rounded-2xl border bg-emerald-50 p-5 shadow-sm {{ $filter === 'ready_drill' ? 'ring-2 ring-emerald-500' : '' }}"><p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Ready to Drill</p><p class="mt-2 text-2xl font-black tabular-nums text-emerald-800">{{ $advanced['ready_drill'] }}</p></a>
    <a href="{{ route('projects.foundation-control', ['project' => $project, 'filter' => 'ready_cast']) }}" class="card-lift rounded-2xl border bg-teal-50 p-5 shadow-sm {{ $filter === 'ready_cast' ? 'ring-2 ring-teal-500' : '' }}"><p class="text-xs font-bold uppercase tracking-wide text-teal-700">Ready to Cast</p><p class="mt-2 text-2xl font-black tabular-nums text-teal-800">{{ $advanced['ready_cast'] }}</p></a>
    <a href="{{ route('projects.foundation-control', ['project' => $project, 'filter' => 'accepted']) }}" class="rounded-2xl border bg-white p-5 shadow-sm {{ $filter === 'accepted' ? 'ring-2 ring-indigo-500' : '' }}"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Pile Accepted</p><p class="mt-2 text-2xl font-black tabular-nums">{{ $acceptedCount }}</p></a>
    <a href="{{ route('projects.foundation-control', ['project' => $project, 'filter' => 'not_accepted']) }}" class="card-lift rounded-2xl border bg-amber-50 p-5 shadow-sm {{ $filter === 'not_accepted' ? 'ring-2 ring-amber-500' : '' }}"><p class="text-xs font-bold uppercase tracking-wide text-amber-700">Belum Diterima</p><p class="mt-2 text-2xl font-black tabular-nums text-amber-800">{{ $advanced['not_accepted'] }}</p></a>
    <a href="{{ route('projects.foundation-control', ['project' => $project, 'filter' => 'slurry']) }}" class="card-lift rounded-2xl border bg-cyan-50 p-5 shadow-sm {{ $filter === 'slurry' ? 'ring-2 ring-cyan-500' : '' }}"><p class="text-xs font-bold uppercase tracking-wide text-cyan-700">Critical Slurry</p><p class="mt-2 text-2xl font-black tabular-nums text-cyan-800">{{ $advanced['critical_slurry'] }}</p></a>
    <a href="{{ route('projects.foundation-control', ['project' => $project, 'filter' => 'tremie']) }}" class="card-lift rounded-2xl border bg-orange-50 p-5 shadow-sm {{ $filter === 'tremie' ? 'ring-2 ring-orange-500' : '' }}"><p class="text-xs font-bold uppercase tracking-wide text-orange-700">Tremie Warning</p><p class="mt-2 text-2xl font-black tabular-nums text-orange-800">{{ $advanced['tremie_warnings'] }}</p></a>
    <a href="{{ route('projects.foundation-control', ['project' => $project, 'filter' => 'interruption']) }}" class="card-lift rounded-2xl border bg-red-50 p-5 shadow-sm {{ $filter === 'interruption' ? 'ring-2 ring-red-400' : '' }}"><p class="text-xs font-bold uppercase tracking-wide text-red-700">Concrete Interruption</p><p class="mt-2 text-2xl font-black tabular-nums text-red-800">{{ $advanced['interruptions'] }}</p></a>
    <a href="{{ route('projects.foundation-control', ['project' => $project, 'filter' => 'geometry']) }}" class="card-lift rounded-2xl border bg-violet-50 p-5 shadow-sm {{ $filter === 'geometry' ? 'ring-2 ring-violet-500' : '' }}"><p class="text-xs font-bold uppercase tracking-wide text-violet-700">Geometry Warning</p><p class="mt-2 text-2xl font-black tabular-nums text-violet-800">{{ $advanced['geometry_warnings'] }}</p></a>
    <div class="rounded-2xl border bg-slate-900 p-5 text-white shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-300">Cost Total (aktual)</p><p class="mt-2 text-xl font-black tabular-nums">Rp {{ number_format((float) $advanced['cost_total'], 0) }}</p></div>
    <div class="rounded-2xl border bg-red-900/80 p-5 text-white shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-red-200">Rework Cost</p><p class="mt-2 text-xl font-black tabular-nums">Rp {{ number_format((float) $advanced['cost_rework'], 0) }}</p></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Produktivitas 7 Hari</p><p class="mt-2 text-xl font-black tabular-nums">{{ $advanced['prod7_meters_per_day'] ?? '-' }} m/hari</p><p class="text-[10px] text-slate-400">{{ $advanced['prod7_piles_completed'] }} pile selesai</p></div>
    <div class="card-lift rounded-2xl border bg-indigo-50 p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-indigo-700">Forecast Finish</p><p class="mt-2 text-lg font-black tabular-nums text-indigo-900">{{ $advanced['forecast']['forecast_completion_date'] ?? 'Data kurang' }}</p><p class="text-[10px] uppercase text-indigo-400">{{ str($advanced['forecast']['method'])->replace('_', ' ') }} · confidence {{ $advanced['forecast']['confidence'] }}</p></div>
</div>

{{-- Lookahead 3/7 hari --}}
<h2 class="mt-8 font-black">Lookahead 3 / 7 Hari</h2>
<p class="text-xs text-slate-400">Deterministik dari status pile + planned date + snapshot readiness terakhir — tanpa penjadwalan otomatis.</p>
<div class="mt-2 grid gap-4 lg:grid-cols-2">
    @foreach(['lookahead3' => 3, 'lookahead7' => 7] as $laKey => $laDays)
    <div class="rounded-2xl border bg-white p-4 shadow-sm">
        <h3 class="text-sm font-bold">Window {{ $laDays }} Hari</h3>
        <table class="mt-2 w-full text-xs">
        <thead><tr class="border-b uppercase tracking-wider"><th class="py-1.5 text-left">Pile</th><th class="text-left">Zona</th><th class="text-left">Rig</th><th class="text-left">Readiness</th><th class="text-right">Blocker</th></tr></thead>
        <tbody>@forelse(${$laKey} as $row)
            <tr class="border-b">
                <td class="py-1.5 font-bold">{{ $row['pile']->pile_number }}@if($row['planned_date'])<span class="block text-[10px] font-normal text-slate-400">{{ $row['planned_date']->format('d/m') }}</span>@endif</td>
                <td>{{ $row['zone'] ?? '-' }}</td><td>{{ $row['rig'] ?? '-' }}</td>
                <td><span class="rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase {{ ['READY' => 'bg-emerald-100 text-emerald-800', 'NOT_READY' => 'bg-red-100 text-red-700'][$row['readiness_status']] ?? 'bg-slate-100 text-slate-600' }}">{{ str($row['readiness_status'])->replace('_', ' ') }}</span></td>
                <td class="text-right font-mono">{{ $row['blockers'] }}{{ $row['active_constraints'] > 0 ? ' · ⚠'.$row['active_constraints'] : '' }}</td>
            </tr>
        @empty<tr><td colspan="5" class="py-3 text-center text-slate-400">Tidak ada pile terencana.</td></tr>@endforelse</tbody>
        </table>
    </div>
    @endforeach
</div>

@if($filter)
<p class="mt-6 inline-block rounded-xl bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">Filter aktif: {{ str($filter)->replace('_', ' ') }} · {{ $rows->count() }} pile <a href="{{ route('projects.foundation-control', $project) }}" class="ml-2 underline">hapus filter</a></p>
@endif

{{-- Risiko --}}
<h2 class="mt-8 font-black">Risk Radar</h2>
<p class="text-xs text-slate-400">Deterministik dari data nyata — tanpa AI. WATCH ≥ 15 poin, CRITICAL bila ada temuan kritis atau skor ≥ 60.</p>
<div class="mt-2 flex flex-wrap gap-3">
    <span class="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-sm font-bold text-emerald-800">HEALTHY · {{ $riskCounts['healthy'] }}</span>
    <span class="rounded-xl border border-amber-300 bg-amber-50 px-3 py-1.5 text-sm font-bold text-amber-800">WATCH · {{ $riskCounts['watch'] }}</span>
    <span class="rounded-xl border border-red-300 bg-red-50 px-3 py-1.5 text-sm font-bold text-red-700">CRITICAL · {{ $riskCounts['critical'] }}</span>
</div>
<div class="mt-3 overflow-x-auto rounded-2xl border bg-white shadow-sm">
<table class="w-full text-xs">
    <thead><tr class="border-b bg-slate-50 uppercase tracking-wider"><th class="p-2 text-left">Pile</th><th class="p-2 text-left">Status</th><th class="p-2 text-left">Risiko</th><th class="p-2 text-left">Alasan</th><th class="p-2"></th></tr></thead>
    <tbody>
    @foreach($rows as $row)
        @if($row['risk']['level'] !== 'healthy')
        <tr class="border-b hover:bg-indigo-50/40">
            <td class="p-2 font-bold">{{ $row['pile']->pile_number }}</td>
            <td class="p-2">{{ $row['status_label'] }}@if($row['pile']->acceptance) · {{ $row['pile']->acceptance->status }}@endif</td>
            <td class="p-2"><span class="rounded-md px-2 py-0.5 font-bold uppercase {{ $row['risk']['level'] === 'critical' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800' }}">{{ $row['risk']['level'] }} ({{ $row['risk']['score'] }})</span></td>
            <td class="p-2 text-slate-600">
                @if(count($row['risk']['reasons']) > 0)
                <ul class="list-disc pl-4">@foreach($row['risk']['reasons'] as $reason)<li>{{ $reason['detail'] }}</li>@endforeach</ul>
                @else
                <span class="text-slate-400">—</span>
                @endif
            </td>
            <td class="p-2"><a href="{{ route('piles.passport', $row['pile']) }}" class="font-bold text-indigo-600 hover:underline">Passport →</a></td>
        </tr>
        @endif
    @endforeach
    @if($rows->isEmpty())<tr><td colspan="5" class="p-4 text-center text-slate-400">Belum ada pile.</td></tr>@endif
    </tbody>
</table>
</div>

{{-- Peta / grid --}}
<h2 class="mt-8 font-black">{{ $mapMode === 'plan' ? 'Plan View' : 'Grid Layout (fallback)' }}</h2>
@if($mapMode === 'plan')
<div class="relative mt-2 h-96 rounded-2xl border bg-gradient-to-br from-slate-50 to-stone-100 shadow-inner dark:from-[#0d1728] dark:to-[#101c30]">
    @foreach($geoPoints as $point)
    <a href="{{ route('piles.passport', $point['pile']) }}" title="{{ $point['pile']->pile_number }} — {{ strtoupper($point['level']) }}"
       class="absolute -translate-x-1/2 -translate-y-1/2" style="left: {{ $point['left'] }}%; top: {{ $point['top'] }}%">
       <span class="flex h-7 w-7 items-center justify-center rounded-full border-2 text-[9px] font-black text-white shadow {{ $point['level'] === 'critical' ? 'border-red-700 bg-red-500' : ($point['level'] === 'watch' ? 'border-amber-600 bg-amber-400 text-amber-950' : 'border-emerald-700 bg-emerald-500') }}">{{ \Illuminate\Support\Str::limit($point['pile']->pile_number, 3, '') }}</span>
    </a>
    @endforeach
    <span class="absolute bottom-2 right-3 text-[10px] text-slate-400">Posisi dinormalisasi dari koordinat lat/lng aktual</span>
</div>
@else
<div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach($zoneGroups as $zoneIndex => $group)
    @if(count($group) > 0)
    <div class="rounded-2xl border bg-white p-4 shadow-sm">
        <h3 class="text-sm font-bold">Zona {{ $group[0]['pile']->zone?->name ?? '-' }}</h3>
        <div class="mt-2 flex flex-wrap gap-1.5">
            @foreach($group as $item)
            @if($item['pile']->status === 'completed')
            <a href="{{ route('piles.passport', $item['pile']) }}" title="{{ $item['pile']->pile_number }} · {{ $item['pile']->status }} · {{ strtoupper($item['level']) }}" class="min-h-[44px] min-w-[52px] rounded-lg border bg-emerald-50 border-emerald-200 px-1.5 py-1 text-center text-[10px] font-bold leading-tight{{ $item['level'] !== 'healthy' ? ' ring-2 ring-offset-1 ' . ($item['level'] === 'critical' ? 'ring-red-500' : 'ring-amber-400') : '' }}">{{ $item['pile']->pile_number }}<br><span class="font-normal text-slate-400">{{ $item['status_short'] }}</span></a>
            @elseif($item['pile']->status === 'hold')
            <a href="{{ route('piles.passport', $item['pile']) }}" title="{{ $item['pile']->pile_number }} · hold · {{ strtoupper($item['level']) }}" class="min-h-[44px] min-w-[52px] rounded-lg border bg-red-100 border-red-300 px-1.5 py-1 text-center text-[10px] font-bold leading-tight{{ $item['level'] !== 'healthy' ? ' ring-2 ring-offset-1 ' . ($item['level'] === 'critical' ? 'ring-red-500' : 'ring-amber-400') : '' }}">{{ $item['pile']->pile_number }}<br><span class="font-normal text-red-500">HOLD</span></a>
            @else
            <a href="{{ route('piles.passport', $item['pile']) }}" title="{{ $item['pile']->pile_number }} · {{ $item['pile']->status }} · {{ strtoupper($item['level']) }}" class="min-h-[44px] min-w-[52px] rounded-lg border bg-slate-50 border-slate-200 px-1.5 py-1 text-center text-[10px] font-bold leading-tight{{ $item['level'] !== 'healthy' ? ' ring-2 ring-offset-1 ' . ($item['level'] === 'critical' ? 'ring-red-500' : 'ring-amber-400') : '' }}">{{ $item['pile']->pile_number }}<br><span class="font-normal text-slate-400">{{ $item['status_short'] }}</span></a>
            @endif
            @endforeach
        </div>
    </div>
    @endif
    @endforeach
</div>
@endif

{{-- Papan harian --}}
<h2 class="mt-8 font-black">Daily Production Board</h2>
<div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
    <x-ui.stat-card label="Dimulai Hari Ini" value="{{ $kpi['started_today'] }} pile" />
    <x-ui.stat-card label="Selesai Hari Ini" value="{{ $kpi['completed_today'] }} pile" />
    <x-ui.stat-card label="Drilled Meter Hari Ini" value="{{ number_format($kpi['meters_today'], 2) }} m" />
    <x-ui.stat-card label="Beton Disetujui" value="{{ number_format($kpi['concrete_today'], 2) }} m³" />
    <x-ui.stat-card label="Uji Pending" value="{{ $kpi['tests_pending'] }}" hint="menunggu hasil rekam" />
    <x-ui.stat-card label="NCR Terbuka" value="{{ $openNcrCount }}" />
</div>
</div></x-layouts.app>
