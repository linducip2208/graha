<x-layouts.app title="Project & Bored Pile"><section class="mx-auto max-w-7xl px-6 py-10"><h1 class="text-2xl font-bold tracking-tight">Project & Bored Pile</h1>@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif @if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif
<div class="mt-8 flex flex-wrap items-center gap-2 no-print"><a href="{{ request()->fullUrlWithQuery(['view' => null]) }}" @class(['rounded-xl border px-4 py-2 text-sm font-semibold', 'bg-sky-700 text-white' => !request('view'), 'bg-white' => request('view')])>Tabel</a><a href="/admin/projects?view=kanban" @class(['rounded-xl border px-4 py-2 text-sm font-semibold', 'bg-sky-700 text-white' => request('view') === 'kanban', 'bg-white' => request('view') !== 'kanban'])>Kanban</a><form method="get" class="ml-auto flex gap-2"><input name="q" value="{{ request('q') }}" placeholder="Cari kode/nama proyek…" class="w-56 rounded-xl border p-2 text-sm">@if(request('view'))<input type="hidden" name="view" value="{{ request('view') }}">@endif<button class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Cari</button></form></div>
<form method="get" class="mt-3 flex flex-wrap items-center gap-2 no-print"><span class="text-xs font-bold uppercase tracking-wide text-slate-500">Status:</span>@foreach(['draft' => 'Direncanakan', 'in_progress' => 'Berjalan', 'active' => 'Aktif', 'closed' => 'Selesai'] as $s => $label)<a href="/admin/projects?status={{ $s }}{{ request('view') ? '&view='.request('view') : '' }}" @class(['rounded-full border px-3 py-1 text-xs font-semibold', 'bg-sky-100 text-sky-800 border-sky-300' => request('status') === $s, 'bg-white text-slate-600' => request('status') !== $s])>{{ $label }} <span class="font-mono">{{ $statusCounts[$s] ?? 0 }}</span></a>@endforeach @if(request('status'))<a href="/admin/projects" class="rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-red-700">Reset</a>@endif</form>
@if($kanban)<x-ui.kanban :columns="$kanban" class="mt-6" />@endif
@if(!$kanban && $projects->isNotEmpty())<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">@foreach($projects as $p)<a href="/admin/projects/{{ $p->id }}" class="card-lift rounded-2xl border bg-white p-5 shadow-sm"><div class="flex items-center justify-between gap-2"><strong>{{ $p->code }}</strong><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold uppercase text-slate-600">{{ str_replace('_',' ',$p->status) }}</span></div><p class="mt-1 text-sm text-slate-600">{{ $p->name }}</p><div class="mt-3 flex justify-between text-xs text-slate-500"><span>{{ $p->bored_piles_count }} titik pile</span>@if($p->contract_value)<span class="font-mono">Rp {{ number_format((float) $p->contract_value / 1_000_000, 0, ',', '.') }} jt</span>@endif</div></a>@endforeach</div>@endif

@if($schedule && !request('view'))
<div class="mt-8 rounded-2xl border bg-white p-6 shadow-sm" id="jadwal">
<div class="flex flex-wrap items-center justify-between gap-3">
<div><h2 class="font-black">Jadwal Proyek — Gantt & Kurva-S</h2><p class="mt-1 text-xs text-slate-500">Rentang aktivitas aktual tiap titik pile terhadap jendela rencana proyek.</p></div>
@if($allProjects->count() > 1)<form method="get" class="no-print"><select name="project" onchange="this.form.submit()" class="rounded-xl border p-2 text-sm">@foreach($allProjects as $p)<option value="{{ $p->id }}" @selected($schedule['project']->id === $p->id)>{{ $p->code }} — {{ $p->name }}</option>@endforeach</select></form>@endif
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

<div class="mt-8 grid gap-6 lg:grid-cols-2"><form method="post" action="/admin/project-zones" class="grid gap-3 rounded-2xl border bg-white p-6 no-print">@csrf<h2 class="font-bold">Tambah Zona</h2><select name="project_id" required class="rounded-xl border p-3">@foreach($allProjects as $p)<option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>@endforeach</select><input name="code" placeholder="Kode zona" required class="rounded-xl border p-3"><input name="name" placeholder="Nama zona" required class="rounded-xl border p-3"><button class="rounded-xl bg-slate-800 p-3 text-white">Simpan zona</button></form>
<form method="post" action="/admin/bored-piles" class="grid gap-3 rounded-2xl border bg-white p-6 no-print">@csrf<h2 class="font-bold">Tambah Titik Bored Pile</h2><select name="project_id" required class="rounded-xl border p-3">@foreach($allProjects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select><select name="project_zone_id" required class="rounded-xl border p-3">@foreach($zones as $z)<option value="{{ $z->id }}">{{ $z->project->name }} / {{ $z->name }}</option>@endforeach</select><div class="grid grid-cols-3 gap-3"><input name="pile_number" placeholder="Pile no." required class="rounded-xl border p-3"><input name="diameter_mm" type="number" step=".01" placeholder="Diameter mm" required class="rounded-xl border p-3"><input name="planned_depth_m" type="number" step=".001" placeholder="Depth m" required class="rounded-xl border p-3"></div><button class="rounded-xl bg-sky-700 p-3 text-white">Simpan titik</button></form></div>
@endif

<div class="mt-8 overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-sm table-sticky"><thead><tr><th>Proyek/Zona</th><th>Pile</th><th>Diameter</th><th>Depth</th><th>Beton</th><th>Overbreak</th><th>Status</th></tr></thead><tbody>@forelse($piles as $pile)<tr class="cursor-pointer hover:bg-slate-50 dark:hover:!bg-slate-800" onclick="location.href='/admin/projects/{{ $pile->project_id }}?tab=piles'"><td>{{ $pile->project->code }}/{{ $pile->zone->code }}</td><td class="font-mono">{{ $pile->pile_number }}</td><td>{{ $pile->diameter_mm }} mm</td><td>{{ $pile->actual_depth_m??$pile->planned_depth_m }} m</td><td>{{ $pile->actual_concrete_m3??'-' }}</td><td class="{{ $pile->overbreak_exceeded?'text-red-700 font-bold':'' }}">{{ $pile->overbreak_percent??'-' }}%</td><td>{{ str_replace('_',' ',strtoupper($pile->status)) }}</td></tr>@empty<tr><td colspan="7" class="p-8 text-center">Belum ada titik bored pile.</td></tr>@endforelse</tbody></table></div>{{ $piles->links() }}</section></x-layouts.app>
