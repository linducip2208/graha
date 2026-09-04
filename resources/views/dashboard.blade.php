@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 19 ? 'Selamat sore' : 'Selamat malam'));
    $statMeta = [
        'Tender Aktif' => ['icon' => 'flag', 'tone' => 'info'],
        'Proyek Berjalan' => ['icon' => 'cube', 'tone' => 'brand'],
        'Menunggu Persetujuan' => ['icon' => 'check', 'tone' => 'warning'],
        'AR Outstanding' => ['icon' => 'wallet', 'tone' => 'violet'],
        'Stok Kritis' => ['icon' => 'archive', 'tone' => 'danger'],
        'NCR Terbuka' => ['icon' => 'shield', 'tone' => 'warning'],
        'Incident Terbuka' => ['icon' => 'triangle-alert', 'tone' => 'danger'],
        'Order Produksi Aktif' => ['icon' => 'cog', 'tone' => 'violet'],
    ];
    $kpiCards = $stats instanceof \Illuminate\Support\Collection ? $stats : collect($stats);
@endphp
<x-layouts.app title="Executive Dashboard">
<section class="dashboard-shell mx-auto max-w-[1500px] px-4 py-7 sm:px-6 lg:px-8">

{{-- ===== HEADER ===== --}}
<header class="flex flex-wrap items-end justify-between gap-4">
<div>
<h1 class="text-[26px] font-extrabold leading-tight tracking-tight">{{ $greeting }}, {{ str(auth()->user()?->name ?? 'Team')->before(' ') }} 👋</h1>
<p class="mt-1 text-sm text-[var(--text-muted)]">Berikut ringkasan bisnis Anda hari ini · {{ now()->format('d M Y') }}</p>
</div>
<div class="flex items-center gap-2">
@can('finance.manage')
<a href="/admin/experience" class="rounded-xl border border-[var(--border-default)] bg-[var(--surface-card)] px-3.5 py-2 text-xs font-bold text-[var(--text-secondary)] transition hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]">Kelola Widget</a>
@endcan
<a href="{{ route('apps') }}" class="rounded-xl border border-[var(--border-default)] bg-[var(--surface-card)] px-3.5 py-2 text-xs font-bold text-[var(--text-secondary)] transition hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]">▦ Semua Aplikasi</a>
</div>
</header>

{{-- ===== ROW 1: KPI ===== --}}
<div class="mt-6 grid gap-4 sm:grid-cols-2 md:grid-cols-3 2xl:grid-cols-6">
@forelse($kpiCards->take(6) as $label => $stat)
@php($meta = $statMeta[$label] ?? ['icon' => 'dashboard', 'tone' => 'brand'])
<x-ui.stat-card :label="$label" :value="is_numeric($stat) ? number_format($stat, 0, ',', '.') : (is_array($stat) ? (is_string($stat['value']) && str_starts_with($stat['value'], 'Rp') ? $stat['value'] : number_format((float) $stat['value'], 0, ',', '.')) : '-')" :hint="$stat['hint'] ?? ''" :icon="$meta['icon']" :tone="$meta['tone']" valueClass="text-[22px] leading-tight" :class="($widths[$label] ?? 0) >= 6 ? 'sm:col-span-2 md:col-span-3 2xl:col-span-2' : ''" />
@empty
<div class="rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-8 text-center text-sm text-[var(--text-muted)] sm:col-span-2 md:col-span-3 2xl:col-span-6">Belum ada widget untuk kewenangan Anda — hubungi Company Admin bila akses operasional diperlukan.</div>
@endforelse
</div>
@if($kpiCards->count() > 6)
<div class="mt-3 flex flex-wrap gap-2">
@foreach($kpiCards->skip(6) as $label => $stat)
@php($meta = $statMeta[$label] ?? ['icon' => 'dashboard', 'tone' => 'brand'])
<a href="#kpi-lainnya" class="inline-flex items-center gap-2 rounded-full border border-[var(--border-subtle)] bg-[var(--surface-card)] px-3.5 py-1.5 text-xs font-bold text-[var(--text-secondary)] transition hover:border-[var(--brand-primary)] hover:text-[var(--brand-primary)]"><x-ui.icon :name="$meta['icon']" class="h-3.5 w-3.5" />{{ $label }}: {{ is_numeric($stat) ? number_format($stat, 0, ',', '.') : (is_array($stat) ? $stat['value'] : '-') }}</a>
@endforeach
</div>
@endif

{{-- ===== ROW 2: CHART UTAMA + ATTENTION ===== --}}
<div class="mt-6 grid gap-5 lg:grid-cols-12">
@if($revenueTrend && $revenueTrend->isNotEmpty())
<article class="dashboard-panel panel-revenue rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-xs)] lg:col-span-8">
<div class="flex items-center justify-between"><h2 class="text-[15px] font-extrabold tracking-tight">Performa Pendapatan</h2><a href="/admin/billing" class="text-xs font-bold text-[var(--brand-primary)]">Billing →</a></div>
<p class="mt-0.5 text-xs text-[var(--text-muted)]">DPP billing posted vs PPN keluaran · 6 bulan terakhir</p>
<div class="relative mt-4 h-64"><canvas id="chart-revenue"></canvas></div>
</article>
@elseif($executive)
<article class="dashboard-panel panel-revenue rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-xs)] lg:col-span-8">
<h2 class="text-[15px] font-extrabold tracking-tight">Ringkasan Eksekutif</h2>
<div class="mt-4 grid gap-4 sm:grid-cols-3">
<div class="rounded-xl bg-[var(--surface-muted)] p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-[var(--text-muted)]">Pendapatan MTD</p><p class="mt-1 text-xl font-extrabold tabular-nums">Rp {{ number_format($executive['revenue_mtd'], 0, ',', '.') }}</p></div>
<div class="rounded-xl bg-[var(--surface-muted)] p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-[var(--text-muted)]">Nilai Kontrak</p><p class="mt-1 text-xl font-extrabold tabular-nums">Rp {{ number_format($executive['contract_active'], 0, ',', '.') }}</p></div>
<div class="rounded-xl bg-[var(--surface-muted)] p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-[var(--text-muted)]">Win Rate</p><p class="mt-1 text-xl font-extrabold tabular-nums">{{ $executive['win_rate'] !== null ? $executive['win_rate'].'%' : '-' }}</p></div>
</div>
</article>
@endif
<article class="dashboard-panel panel-attention rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-xs)] lg:col-span-4">
<h2 class="text-[15px] font-extrabold tracking-tight">Perlu Perhatian</h2>
<div class="mt-4 space-y-2.5">
@forelse($attention as $item)
@php($tones = ['warning' => 'bg-amber-50 text-amber-600', 'danger' => 'bg-red-50 text-red-600', 'success' => 'bg-emerald-50 text-emerald-600', 'brand' => 'bg-sky-50 text-sky-600'])
@php($tone = $tones[$item['tone']] ?? $tones['brand'])
<{{ $item['href'] ? 'a href="'.$item['href'].'"' : 'div' }} class="flex items-center gap-3 rounded-xl border border-[var(--border-subtle)] p-3 transition hover:border-[var(--brand-primary)]">
<span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $tone }}"><x-ui.icon :name="$item['icon']" class="h-4 w-4" /></span>
<span class="min-w-0 flex-1 text-[13px] font-semibold">{{ $item['label'] }}</span>
<span class="text-lg font-extrabold tabular-nums {{ $item['count'] > 0 ? '' : 'text-[var(--text-muted)]' }}">{{ $item['count'] }}</span>
</{{ $item['href'] ? 'a' : 'div' }}>
@empty
<p class="rounded-xl bg-[var(--surface-muted)] p-4 text-sm text-[var(--text-muted)]">Tidak ada item perlu perhatian.</p>
@endforelse
</div>
</article>
</div>

{{-- ===== ROW 3: PROJECT TABLE + ACTIVITY ===== --}}
<div class="mt-6 grid gap-5 lg:grid-cols-12">
@if($projectHealth->isNotEmpty())
<article class="dashboard-panel panel-project rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)] lg:col-span-8">
<div class="flex items-center justify-between px-5 pt-4"><h2 class="text-[15px] font-extrabold tracking-tight">Kinerja Proyek</h2><a href="/admin/projects" class="text-xs font-bold text-[var(--brand-primary)]">Semua Proyek →</a></div>
<div class="mt-3 overflow-x-auto px-2 pb-2">
<table class="w-full text-sm"><thead><tr><th class="!bg-transparent">Proyek</th><th class="!bg-transparent text-right">Fisik</th><th class="!bg-transparent text-right">Varians</th><th class="!bg-transparent text-right">EAC</th><th class="!bg-transparent">Progres</th><th class="!bg-transparent">Status</th></tr></thead><tbody>
@foreach($projectHealth as $row)
<tr onclick="location.href='/admin/projects/{{ $row['project']->id }}'" class="cursor-pointer">
<td class="py-3.5 pl-3"><strong class="text-[13px]">{{ $row['project']->code }}</strong><span class="block max-w-48 truncate text-xs text-[var(--text-muted)]">{{ $row['project']->name }}</span></td>
<td class="text-right font-mono tabular-nums">{{ number_format($row['physical'], 1) }}%</td>
<td class="text-right font-mono tabular-nums {{ $row['variance'] < 0 ? 'text-red-600' : ($row['variance'] > 0 ? 'text-emerald-600' : 'text-[var(--text-muted)]') }}">{{ $row['variance'] > 0 ? '+' : '' }}{{ number_format($row['variance'], 1) }}</td>
<td class="text-right font-mono tabular-nums">{{ number_format($row['eac'], 0, ',', '.') }}</td>
<td class="px-3"><div class="flex items-center gap-2"><div class="h-1.5 w-full min-w-16 max-w-24 overflow-hidden rounded-full bg-[var(--surface-muted)]"><div class="h-full rounded-full {{ $row['health'] === 'red' ? 'bg-red-500' : ($row['health'] === 'yellow' ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min(100, (float) $row['physical']) }}%"></div></div><span class="text-[10px] font-bold text-[var(--text-muted)]">{{ number_format($row['planned'], 0) }}%</span></div></td>
<td class="py-3.5 pr-3"><span class="inline-flex items-center gap-1.5 text-[11px] font-bold {{ $row['health'] === 'red' ? 'text-red-600' : ($row['health'] === 'yellow' ? 'text-amber-600' : 'text-emerald-600') }}"><span class="h-2 w-2 rounded-full bg-current"></span>{{ strtoupper($row['health']) }}</span></td>
</tr>
@endforeach
</tbody></table>
</div>
</article>
@endif
@if($activity->isNotEmpty())
<article class="dashboard-panel panel-activity rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-xs)] {{ $projectHealth->isNotEmpty() ? 'lg:col-span-4' : 'lg:col-span-12' }}">
<div class="flex items-center justify-between"><h2 class="text-[15px] font-extrabold tracking-tight">Aktivitas Terbaru</h2><a href="/admin/audit" class="text-xs font-bold text-[var(--brand-primary)]">Audit →</a></div>
<ul class="mt-4 space-y-3.5">
@foreach($activity as $log)
<li class="flex gap-3">
<span class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[var(--surface-muted)] text-[var(--text-secondary)]"><x-ui.icon name="clock" class="h-3.5 w-3.5" /></span>
<div class="min-w-0"><p class="truncate text-[13px] font-semibold">{{ str($log->event)->replace('_', ' ')->title() }}</p><p class="text-xs text-[var(--text-muted)]">{{ $log->actor?->name ?? 'Sistem' }} · {{ $log->created_at->diffForHumans() }}</p></div>
</li>
@endforeach
</ul>
</article>
@endif
</div>

{{-- ===== ROW 4: SECONDARY COMPACT ===== --}}
@if($aging || ($pileStatus && $pileStatus->isNotEmpty()) || $procurementQueue)
<div class="mt-6 grid gap-5 lg:grid-cols-3">
@if($aging)
<article class="dashboard-panel panel-finance rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-xs)]">
<div class="flex items-center justify-between"><h2 class="text-[15px] font-extrabold tracking-tight">Aging AR/AP</h2><a href="/admin/reports/aging" class="text-xs font-bold text-[var(--brand-primary)]">Detail →</a></div>
<p class="mt-0.5 text-xs text-[var(--text-muted)]">Piutang Rp {{ number_format((float) $aging['ar_total'], 0, ',', '.') }} · utang Rp {{ number_format((float) $aging['ap_total'], 0, ',', '.') }}</p>
<div class="relative mt-3 h-44"><canvas id="chart-aging"></canvas></div>
</article>
@endif
@if($pileStatus && $pileStatus->isNotEmpty())
<article class="dashboard-panel panel-foundation rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-xs)]">
<div class="flex items-center justify-between"><h2 class="text-[15px] font-extrabold tracking-tight">Status Bored Pile</h2><a href="/admin/projects" class="text-xs font-bold text-[var(--brand-primary)]">Proyek →</a></div>
<div class="relative mt-3 h-44"><canvas id="chart-piles"></canvas></div>
</article>
@endif
@if($procurementQueue)
<article class="dashboard-panel panel-procurement rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-xs)]">
<div class="flex items-center justify-between"><h2 class="text-[15px] font-extrabold tracking-tight">Procurement</h2><a href="/admin/procurement" class="text-xs font-bold text-[var(--brand-primary)]">Buka →</a></div>
<div class="mt-4 space-y-3">
<div class="flex items-center justify-between rounded-xl bg-[var(--surface-muted)] px-4 py-3"><span class="text-xs font-bold text-[var(--text-secondary)]">RFQ Terbuka</span><span class="text-lg font-extrabold tabular-nums">{{ $procurementQueue['rfqOpen'] }}</span></div>
<div class="flex items-center justify-between rounded-xl bg-[var(--surface-muted)] px-4 py-3"><span class="text-xs font-bold text-[var(--text-secondary)]">PO Menunggu Terima</span><span class="text-lg font-extrabold tabular-nums">{{ $procurementQueue['poPendingReceive'] }}</span></div>
<div class="flex items-center justify-between rounded-xl bg-[var(--surface-muted)] px-4 py-3"><span class="text-xs font-bold text-[var(--text-secondary)]">Komitmen PO</span><span class="text-sm font-extrabold tabular-nums">Rp {{ number_format((float) $procurementQueue['poValue'], 0, ',', '.') }}</span></div>
</div>
</article>
@endif
@if($journals->isNotEmpty())
<article class="dashboard-panel panel-accounting rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-xs)]">
<div class="flex items-center justify-between"><h2 class="text-[15px] font-extrabold tracking-tight">Jurnal Terbaru</h2><a href="/admin/finance/journals" class="text-xs font-bold text-[var(--brand-primary)]">Buku Besar →</a></div>
<table class="mt-3 w-full text-[13px]"><tbody>@foreach($journals as $journal)<tr class="border-b border-[var(--border-subtle)] last:border-0"><td class="py-2.5 font-mono text-xs">{{ $journal->number }}</td><td class="text-[var(--text-muted)]">{{ str($journal->source_type)->replace('_', ' ') }}</td><td class="text-right font-mono tabular-nums">{{ number_format((float) ($journal->entries->sum('debit') ?: 0), 0, ',', '.') }}</td></tr>@endforeach</tbody></table>
</article>
@endif
</div>
@endif
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) return;
    const css = getComputedStyle(document.documentElement);
    const v = (name, fallback) => css.getPropertyValue(name).trim() || fallback;
    const dark = document.documentElement.classList.contains('dark');
    Chart.defaults.color = v('--text-muted', '#8292a8');
    Chart.defaults.borderColor = v('--border-subtle', '#e8edf4');
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 11;
    const brand = v('--brand-primary', '#0369a1');
    const palette = [brand, v('--tone-info', '#0284c7'), v('--tone-violet', '#7c3aed'), v('--tone-warning', '#d97706'), v('--tone-success', '#059669')];
    const grid = { grid: { color: v('--border-subtle', '#e8edf4'), drawTicks: false }, border: { display: false } };
    @if($revenueTrend && $revenueTrend->isNotEmpty())
    new Chart(document.getElementById('chart-revenue'), {
        type: 'bar',
        data: { labels: @json($revenueTrend->pluck('label')), datasets: [
            { label: 'DPP Billing', data: @json($revenueTrend->pluck('dpp')), backgroundColor: brand, borderRadius: 8, maxBarThickness: 42 },
            { label: 'PPN Keluaran', data: @json($revenueTrend->pluck('tax')), backgroundColor: v('--tone-warning', '#d97706'), borderRadius: 8, maxBarThickness: 42 },
        ]},
        options: { responsive: true, maintainAspectRatio: false, scales: { x: grid, y: { ...grid, ticks: { callback: v2 => 'Rp ' + (v2/1e6) + ' jt' } } }, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } }, tooltip: { backgroundColor: v('--text-primary', '#0f172a'), padding: 10, cornerRadius: 8, displayColors: false } } }
    });
    @endif
    @if($pileStatus && $pileStatus->isNotEmpty())
    new Chart(document.getElementById('chart-piles'), {
        type: 'doughnut',
        data: { labels: @json($pileStatus->keys()->map(fn ($s) => str($s)->replace('_', ' ')->title())), datasets: [{ data: @json($pileStatus->values()), backgroundColor: palette, borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'circle' } } } }
    });
    @endif
    @if($aging)
    new Chart(document.getElementById('chart-aging'), {
        type: 'bar',
        data: { labels: @json($aging['buckets']->keys()), datasets: [{ label: 'Outstanding', data: @json($aging['buckets']->values()), backgroundColor: [v('--tone-success', '#059669'), v('--tone-warning', '#d97706'), '#f97316', v('--tone-danger', '#dc2626')], borderRadius: 8, maxBarThickness: 22 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: { ...grid, ticks: { callback: v2 => (v2/1e6) + ' jt' } }, y: { grid: { display: false }, border: { display: false } } }, plugins: { legend: { display: false }, tooltip: { backgroundColor: v('--text-primary', '#0f172a'), padding: 10, cornerRadius: 8, callbacks: { label: ctx => 'Rp ' + Number(ctx.raw).toLocaleString('id-ID') } } } }
    });
    @endif
});
</script>
</x-layouts.app>
