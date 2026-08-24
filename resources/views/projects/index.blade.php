<x-layouts.app title="Project Control Center">
@php($view = request('view'))
@php($healthLabels = ['green' => 'Healthy', 'yellow' => 'Watch', 'red' => 'Critical'])
<div class="page-container">
<x-ui.page-header title="Project" subtitle="Pantau portfolio proyek, progress, nilai kontrak, biaya, dan kesehatan proyek.">
<x-slot:actions>
<span class="chip chip-default">{{ number_format($kpi['pile_total']) }} titik pile</span>
</x-slot:actions>
</x-ui.page-header>

@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-6">
<x-ui.stat-card label="Total Project" value="{{ number_format($kpi['total']) }}" icon="cube" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Project Aktif" value="{{ number_format($kpi['active']) }}" icon="check" tone="info" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Nilai Kontrak" value="Rp {{ number_format($kpi['contract_value'] / 1_000_000_000, 2, ',', '.') }} M" icon="banknote" tone="violet" :value-class="'text-[20px] leading-tight'" />
<x-ui.stat-card label="Kritis (Health)" value="{{ number_format($kpi['critical']) }}" icon="triangle-alert" tone="{{ $kpi['critical'] > 0 ? 'danger' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Watch (Health)" value="{{ number_format($kpi['watch']) }}" icon="clock" tone="{{ $kpi['watch'] > 0 ? 'warning' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Rata-rata Progres" value="{{ $kpi['avg_progress'] !== null ? number_format($kpi['avg_progress'], 1).'%' : '-' }}" icon="chart" tone="brand" :value-class="'text-[24px] leading-tight'" />
</div>

{{-- ===== TOOLBAR TUNGGAL: filter + view switcher ===== --}}
<form method="get" action="/admin/projects" class="mt-6 flex flex-wrap items-center gap-2 rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-3 shadow-[var(--shadow-xs)] no-print">
<input type="search" name="q" value="{{ request('q') }}" placeholder="Cari project…" class="min-w-[200px] flex-1 rounded-xl border border-[var(--border-default)] bg-[var(--surface-input)] px-3.5 text-sm sm:max-w-xs">
<select name="status" class="rounded-xl border border-[var(--border-default)] bg-[var(--surface-input)] px-3 text-sm">
<option value="">Semua Status</option>
@foreach(['draft' => 'Direncanakan', 'in_progress' => 'Berjalan', 'active' => 'Aktif', 'closed' => 'Selesai'] as $s => $label)
<option value="{{ $s }}" @selected(request('status') === $s)>{{ $label }} ({{ $statusCounts[$s] ?? 0 }})</option>
@endforeach
</select>
<select name="customer" class="rounded-xl border border-[var(--border-default)] bg-[var(--surface-input)] px-3 text-sm">
<option value="">Semua Client</option>
@foreach($customers as $c)
<option value="{{ $c->id }}" @selected(request('customer') == $c->id)>{{ $c->name }}</option>
@endforeach
</select>
<select name="health" class="rounded-xl border border-[var(--border-default)] bg-[var(--surface-input)] px-3 text-sm">
<option value="">Semua Health</option>
@foreach($healthLabels as $h => $hl)
<option value="{{ $h }}" @selected(request('health') === $h)>{{ $hl }}</option>
@endforeach
</select>
<button class="inline-flex min-h-[40px] items-center rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]">Terapkan</button>
<a href="/admin/projects" class="inline-flex min-h-[40px] items-center rounded-xl px-3 text-sm font-bold text-[var(--text-muted)] hover:text-[var(--brand-primary)]">Reset</a>
<div class="ml-auto flex gap-1 rounded-xl bg-[var(--surface-muted)] p-1">
<a href="{{ request()->fullUrlWithQuery(['view' => null]) }}" @class(['rounded-lg px-3 py-1.5 text-xs font-bold', 'bg-[var(--surface-card)] shadow-sm text-[var(--brand-primary)]' => ! $view, 'text-slate-500' => $view])>Portfolio</a>
<a href="{{ request()->fullUrlWithQuery(['view' => 'kanban']) }}" @class(['rounded-lg px-3 py-1.5 text-xs font-bold', 'bg-[var(--surface-card)] shadow-sm text-[var(--brand-primary)]' => $view === 'kanban', 'text-slate-500' => $view !== 'kanban'])>Kanban</a>
<a href="{{ request()->fullUrlWithQuery(['view' => 'timeline']) }}" @class(['rounded-lg px-3 py-1.5 text-xs font-bold', 'bg-[var(--surface-card)] shadow-sm text-[var(--brand-primary)]' => $view === 'timeline', 'text-slate-500' => $view !== 'timeline'])>Timeline</a>
</div>
</form>

{{-- ===== VIEW: KANBAN (existing, dipertahankan) ===== --}}
@if($kanban)
<x-ui.kanban :columns="$kanban" class="mt-6" />
@endif

{{-- ===== VIEW: TIMELINE (Gantt + Kurva-S existing, kontekstual) ===== --}}
@if($view === 'timeline' && $schedule)
<div class="mt-6 rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-6 shadow-[var(--shadow-xs)]" id="jadwal">
<div class="flex flex-wrap items-center justify-between gap-3">
<div><h2 class="font-black">Jadwal Proyek — Gantt & Kurva-S</h2><p class="mt-1 text-xs text-slate-500">Rentang aktivitas aktual tiap titik pile terhadap jendela rencana proyek.</p></div>
@if($allProjects->count() > 1)<form method="get" class="no-print"><input type="hidden" name="view" value="timeline"><select name="project" onchange="this.form.submit()" class="rounded-xl border p-2 text-sm">@foreach($allProjects as $p)<option value="{{ $p->id }}" @selected($schedule['project']->id === $p->id)>{{ $p->code }} — {{ $p->name }}</option>@endforeach</select></form>@endif
</div>

<h3 class="mt-5 text-xs font-bold uppercase tracking-widest text-slate-500">Gantt Titik Pile</h3>
<div class="mt-3 overflow-x-auto">
<div class="min-w-[720px] rounded-xl border p-4">
<div class="relative mb-2 h-4 border-b border-slate-200">
@foreach($schedule['months'] as $tick)<span class="absolute -translate-x-1/2 text-[10px] font-semibold text-slate-400" style="left: {{ $tick['position'] }}%">{{ $tick['label'] }}</span>@endforeach
</div>
@forelse($schedule['bars'] as $bar)
<div class="relative mb-1.5 h-7 rounded-lg bg-slate-100">
<span class="absolute inset-y-0 left-2 z-10 flex items-center text-[11px] font-bold text-slate-600">{{ $bar['pile']->pile_number }} <span class="ml-2 hidden font-normal text-slate-400 sm:inline">{{ str($bar['pile']->status)->replace('_',' ') }}</span></span>
<span class="absolute top-1.5 h-4 rounded-full {{ $bar['running'] ? 'bg-gradient-to-r from-sky-500 to-cyan-500' : 'bg-gradient-to-r from-emerald-500 to-teal-500' }}" style="left: {{ $bar['left'] }}%; width: {{ max($bar['width'], 1) }}%" title="{{ $bar['pile']->pile_number }}"></span>
</div>
@empty<p class="py-6 text-center text-sm text-slate-500">Belum ada aktivitas pile tercatat.</p>@endforelse
<div class="mt-2 flex gap-4 text-[11px] text-slate-500"><span><span class="mr-1 inline-block h-2 w-4 rounded-full bg-emerald-500"></span>Selesai</span><span><span class="mr-1 inline-block h-2 w-4 rounded-full bg-sky-500"></span>Berjalan</span></div>
</div>
</div>

@if($schedule['curve']->isNotEmpty())
<h3 class="mt-8 text-xs font-bold uppercase tracking-widest text-slate-500">Kurva-S — Rencana vs Realisasi (% nilai kontrak)</h3>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<div class="relative mt-3 h-64"><canvas id="chart-scurve"></canvas></div>
<script>
document.addEventListener('DOMContentLoaded', () => { if (!window.Chart) return; new Chart(document.getElementById('chart-scurve'), {
type: 'line',
data: { labels: @json($schedule['curve']->pluck('label')), datasets: [
{ label: 'Rencana', data: @json($schedule['curve']->pluck('planned')), borderColor: '#94a3b8', borderDash: [6, 4], tension: .35, pointRadius: 2 },
{ label: 'Realisasi (billing posted)', data: @json($schedule['curve']->pluck('actual')), borderColor: '#0284c7', backgroundColor: 'rgba(2,132,199,.08)', fill: true, tension: .35, pointRadius: 3 } ] },
options: { responsive: true, maintainAspectRatio: false, scales: { y: { ticks: { callback: v => v + '%' }, suggestedMax: 100 } }, plugins: { legend: { position: 'bottom' } } } }); });
</script>
@endif
</div>
@endif

{{-- ===== VIEW DEFAULT: PORTFOLIO TABLE ===== --}}
@if(! $kanban && $view !== 'timeline')
<article class="mt-6 overflow-hidden rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]">
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead>
<tr><th>Project</th><th>Client</th><th>Progres</th><th class="text-right">Nilai Kontrak</th><th>Mulai / Selesai</th><th class="text-right">Margin</th><th>Status</th><th>Health</th><th class="text-right">Aksi</th></tr>
</thead>
<tbody>
@forelse($projects as $p)
@php($h = $healthMap[$p->id] ?? null)
<tr class="h-[54px]">
<td class="max-w-[260px]"><a href="/admin/projects/{{ $p->id }}" class="font-bold text-[var(--brand-primary)] hover:underline">{{ $p->code }}</a><span class="block truncate text-xs text-slate-500">{{ $p->name }}</span></td>
<td class="whitespace-nowrap">{{ $p->customer?->name ?? '—' }}</td>
<td>
<div class="flex items-center gap-2">
<div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-[var(--brand-primary)]" style="width: {{ $h ? min(100, $h['physical']) : 0 }}%"></div></div>
<span class="whitespace-nowrap font-mono text-xs font-bold">{{ $h ? number_format($h['physical'], 1).'%' : '-' }}</span>
</div>
</td>
<td class="whitespace-nowrap text-right font-mono">{{ $p->contract_value ? 'Rp '.number_format((float) $p->contract_value / 1_000_000, 0, ',', '.').' jt' : '—' }}</td>
<td class="whitespace-nowrap text-xs text-slate-500">{{ $p->planned_start?->format('d/m/y') ?? '—' }} → {{ $p->planned_end?->format('d/m/y') ?? '—' }}</td>
<td class="whitespace-nowrap text-right font-mono {{ ($h['margin'] ?? null) !== null && $h['margin'] < 0 ? 'font-bold text-red-600' : '' }}">{{ ($h['margin'] ?? null) !== null ? number_format($h['margin'], 1).'%' : '—' }}</td>
<td>@php($statusChip = ['active' => 'chip-approved', 'in_progress' => 'chip-approved', 'draft' => 'chip-draft', 'closed' => 'chip-default'][$p->status] ?? 'chip-default')<span class="chip {{ $statusChip }}">{{ str_replace('_', ' ', $p->status) }}</span></td>
<td>
@if(($h['health'] ?? null) === 'red')<span class="inline-flex items-center gap-1.5 text-xs font-bold text-red-600"><span class="h-2 w-2 rounded-full bg-red-500"></span>Critical</span>
@elseif(($h['health'] ?? null) === 'yellow')<span class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-600"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Watch</span>
@elseif(($h['health'] ?? null) === 'green')<span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Healthy</span>
@else<span class="text-xs text-slate-400">—</span>@endif
</td>
<td class="whitespace-nowrap text-right"><a href="/admin/projects/{{ $p->id }}" class="font-bold text-[var(--brand-primary)] hover:underline">Buka →</a></td>
</tr>
@empty
<tr><td colspan="9" class="p-2"><x-ui.empty icon="cube" title="Tidak ada project" description="Ubah filter, atau project baru muncul setelah tender dimenangkan dan dikonversi." /></td></tr>
@endforelse
</tbody>
</table>
</div>
</article>
@endif
</div>
</x-layouts.app>
