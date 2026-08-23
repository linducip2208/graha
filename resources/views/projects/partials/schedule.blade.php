<div class="rounded-2xl border bg-white p-6 shadow-sm" id="jadwal">
<div><h2 class="font-black">Jadwal Proyek — Gantt & Kurva-S</h2><p class="mt-1 text-xs text-slate-500">Rentang aktivitas aktual tiap titik pile terhadap jendela rencana proyek.</p></div>

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
