@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 19 ? 'Selamat sore' : 'Selamat malam'));
    $days = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
    $months = ['January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret', 'April' => 'April', 'May' => 'Mei', 'June' => 'Juni', 'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September', 'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'];
    $dateLabel = str_replace(array_keys($days), $days, now()->format('l')).', '.now()->format('d').' '.str_replace(array_keys($months), $months, now()->format('F')).' '.now()->format('Y');
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
@endphp
<x-layouts.app title="Executive Dashboard"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<header class="flex flex-wrap items-end justify-between gap-4">
<div>
<p class="text-xs font-bold uppercase tracking-widest text-[var(--brand-primary)]">{{ $company->code }} · {{ $dateLabel }}</p>
<h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $greeting }}, {{ str(auth()->user()?->name ?? 'Team')->before(' ') }}</h1>
<p class="mt-2 max-w-2xl text-sm text-slate-500">Angka kunci {{ $company->name }} sesuai kewenangan Anda — diperbarui real-time dari dokumen posted.</p>
</div>
<a href="/apps" class="x-ui.button secondary">▦ Semua Aplikasi</a>
</header>

<div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
@forelse($stats as $label => $stat)
@php($meta = $statMeta[$label] ?? ['icon' => 'dashboard', 'tone' => 'brand'])
<x-ui.stat-card :label="$label" :value="is_numeric($stat) ? number_format($stat, 0, ',', '.') : (is_array($stat) ? (is_string($stat['value']) && str_starts_with($stat['value'], 'Rp') ? $stat['value'] : number_format((float) $stat['value'], 0, ',', '.')) : '-')" :hint="$stat['hint'] ?? ''" :icon="$meta['icon']" :tone="$meta['tone']" :class="($widths[$label] ?? 3) >= 6 ? 'sm:col-span-2 xl:col-span-2' : ''" />
@empty
<x-ui.empty icon="dashboard" title="Belum ada widget untuk kewenangan Anda" description="Hubungi Company Admin bila akses operasional diperlukan." />
@endforelse
</div>

@if($executive)
<div class="mt-10 flex items-center justify-between">
<h2 class="text-lg font-black tracking-tight">Cockpit Eksekutif</h2>
<a href="/admin/reports/executive" class="text-xs font-bold text-[var(--brand-primary)]">Laporan Eksekutif →</a>
</div>
<div class="mt-4 grid gap-5 sm:grid-cols-2 xl:grid-cols-5">
<x-ui.stat-card valueClass="text-xl xl:text-[22px]" label="Pendapatan MTD" value="Rp {{ number_format($executive['revenue_mtd'], 0, ',', '.') }}" hint="Billing posted bulan ini" icon="wallet" tone="brand" />
<x-ui.stat-card valueClass="text-xl xl:text-[22px]" label="Pendapatan YTD" value="Rp {{ number_format($executive['revenue_ytd'], 0, ',', '.') }}" hint="Akumulasi tahun berjalan" icon="chart" tone="info" />
<x-ui.stat-card valueClass="text-xl xl:text-[22px]" label="Gross Profit YTD" value="Rp {{ number_format($executive['gp_ytd'], 0, ',', '.') }}" hint="Billing − biaya aktual tercatat" icon="calculator" :tone="$executive['gp_ytd'] < 0 ? 'danger' : 'success'" />
<x-ui.stat-card valueClass="text-xl xl:text-[22px]" label="Nilai Kontrak Aktif" value="Rp {{ number_format($executive['contract_active'], 0, ',', '.') }}" hint="Proyek aktif berjalan" icon="briefcase" tone="violet" />
<x-ui.stat-card label="Win Rate Tender" value="{{ $executive['win_rate'] !== null ? $executive['win_rate'].'%' : '-' }}" hint="Dari tender yang diputuskan" icon="flag" tone="success" />
</div>
@endif

@if($revenueTrend && $revenueTrend->isNotEmpty())
<div class="mt-10 grid gap-5 lg:grid-cols-5">
<article class="rounded-[var(--radius-card)] border bg-white p-6 shadow-[var(--shadow-card)] lg:col-span-3">
<div class="flex items-center justify-between"><h2 class="font-bold tracking-tight">Tren Pendapatan & PPN Keluaran</h2><a href="/admin/billing" class="text-xs font-bold text-[var(--brand-primary)]">Billing →</a></div>
<p class="mb-4 mt-0.5 text-xs text-slate-500">Dari progress billing posted 6 bulan terakhir.</p>
<div class="relative h-64"><canvas id="chart-revenue"></canvas></div>
</article>
@if($pileStatus && $pileStatus->isNotEmpty())
<article class="rounded-[var(--radius-card)] border bg-white p-6 shadow-[var(--shadow-card)] lg:col-span-2">
<h2 class="font-bold tracking-tight">Status Titik Bored Pile</h2>
<p class="mb-4 mt-0.5 text-xs text-slate-500">Distribusi seluruh titik lintas proyek.</p>
<div class="relative h-64"><canvas id="chart-piles"></canvas></div>
</article>
@endif
</div>
@endif

@if($projectHealth->isNotEmpty())
<div class="mt-10 flex items-center justify-between">
<h2 class="text-lg font-black tracking-tight">Kesehatan Proyek</h2>
<a href="/admin/projects" class="text-xs font-bold text-[var(--brand-primary)]">Semua Proyek →</a>
</div>
<p class="mt-1 text-sm text-slate-500">Fisik vs rencana, EAC dan margin — status hijau/kuning/merah mengikuti ambang yang dapat diatur di Pengaturan Perusahaan.</p>
<div class="mt-4 overflow-x-auto rounded-[var(--radius-card)] border bg-white shadow-[var(--shadow-card)]">
<table class="w-full text-sm table-sticky"><thead><tr><th>Proyek</th><th class="text-right">Fisik</th><th class="text-right">Rencana</th><th class="text-right">Varians</th><th class="text-right">EAC</th><th class="text-right">Margin Est.</th><th>Status</th></tr></thead><tbody>
@foreach($projectHealth as $row)
<tr onclick="location.href='/admin/projects/{{ $row['project']->id }}'" class="cursor-pointer transition hover:bg-slate-50 dark:hover:!bg-slate-800">
<td class="py-3"><strong>{{ $row['project']->code }}</strong> · {{ $row['project']->name }}</td>
<td class="text-right font-mono tabular-nums">{{ number_format($row['physical'], 1) }}%</td>
<td class="text-right font-mono tabular-nums">{{ number_format($row['planned'], 1) }}%</td>
<td class="text-right font-mono tabular-nums {{ abs($row['variance']) > 0 ? ($row['variance'] < 0 ? 'text-red-600' : 'text-emerald-700') : '' }}">{{ $row['variance'] > 0 ? '+' : '' }}{{ number_format($row['variance'], 1) }} pp</td>
<td class="text-right font-mono tabular-nums">{{ number_format($row['eac'], 0, ',', '.') }}</td>
<td class="text-right font-mono tabular-nums {{ $row['margin'] !== null && $row['margin'] < 0 ? 'text-red-600' : '' }}">{{ $row['margin'] !== null ? number_format($row['margin'], 1).'%' : '-' }}</td>
<td class="py-3">@if($row['health'] === 'green')<span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/25"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>HIJAU</span>@elseif($row['health'] === 'yellow')<span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700 ring-1 ring-inset ring-amber-600/25"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>KUNING</span>@else<span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-700 ring-1 ring-inset ring-red-600/25"><span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>MERAH</span>@endif</td>
</tr>
@endforeach
</tbody></table>
</div>
@endif

<div class="mt-10 grid gap-5 lg:grid-cols-2">
@if($procurementQueue)
<x-ui.card bodyClass="p-6">
<div class="flex items-center justify-between"><h2 class="font-bold tracking-tight">Antrean Procurement</h2><a href="/admin/procurement" class="text-xs font-bold text-[var(--brand-primary)]">Procurement →</a></div>
<div class="mt-4 grid gap-3 sm:grid-cols-3">
<a href="/admin/procurement/rfq" class="rounded-xl border bg-slate-50/60 p-4 transition hover:border-[var(--brand-primary)]"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">RFQ Terbuka</p><p class="mt-1 text-2xl font-black tabular-nums">{{ $procurementQueue['rfqOpen'] }}</p></a>
<a href="/admin/procurement" class="rounded-xl border bg-slate-50/60 p-4 transition hover:border-[var(--brand-primary)]"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">PO Menunggu Terima</p><p class="mt-1 text-2xl font-black tabular-nums">{{ $procurementQueue['poPendingReceive'] }}</p></a>
<a href="/admin/procurement" class="rounded-xl border bg-slate-50/60 p-4 transition hover:border-[var(--brand-primary)]"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Komitmen PO</p><p class="mt-1 text-lg font-black tabular-nums">Rp {{ number_format((float) $procurementQueue['poValue'], 0, ',', '.') }}</p></a>
</div>
</x-ui.card>
@endif
@if($aging)
<x-ui.card bodyClass="p-6">
<div class="flex items-center justify-between"><h2 class="font-bold tracking-tight">Aging Piutang & Utang</h2><a href="/admin/reports/aging" class="text-xs font-bold text-[var(--brand-primary)]">Detail Aging →</a></div>
<p class="mb-4 mt-0.5 text-xs text-slate-500">Piutang Rp {{ number_format((float) $aging['ar_total'], 0, ',', '.') }} · utang Rp {{ number_format((float) $aging['ap_total'], 0, ',', '.') }}</p>
<div class="relative h-48"><canvas id="chart-aging"></canvas></div>
</x-ui.card>
@endif
</div>

<div class="mt-10 grid gap-5 lg:grid-cols-2">
@if($approvals !== null)
<x-ui.card bodyClass="p-6">
<div class="flex items-center justify-between"><h2 class="font-bold tracking-tight">Menunggu Persetujuan</h2><a href="/admin/approvals" class="text-xs font-bold text-[var(--brand-primary)]">Approval Center →</a></div>
<div class="mt-3 space-y-2">@forelse($approvals as $approval)
<div class="flex items-center justify-between rounded-xl border p-3 text-sm transition hover:border-[var(--brand-primary)]"><div><strong>{{ class_basename($approval->approvable_type) }} #{{ $approval->approvable_id }}</strong><span class="block text-xs text-slate-500">Tahap {{ $approval->current_sequence }} @if($approval->due_at)· SLA {{ $approval->due_at->format('d/m H:i') }}@endif</span></div>@if($approval->due_at && $approval->due_at->isPast())<x-ui.badge status="exception" label="overdue" />@else<x-ui.badge status="pending_approval" label="pending" />@endif</div>
@empty<x-ui.empty icon="check" title="Tidak ada dokumen menunggu" description="Semua approval dalam batas SLA." />@endforelse</div>
</x-ui.card>
@endif
@if($journals->isNotEmpty())
<x-ui.card bodyClass="p-6">
<div class="flex items-center justify-between"><h2 class="font-bold tracking-tight">Jurnal Terbaru</h2><a href="/admin/finance/journals" class="text-xs font-bold text-[var(--brand-primary)]">Buku Besar →</a></div>
<table class="mt-3 w-full text-sm"><thead><tr><th>Nomor</th><th>Sumber</th><th>Tanggal</th><th class="text-right">Nilai</th></tr></thead><tbody>@foreach($journals as $journal)<tr class="transition hover:bg-slate-50 dark:hover:!bg-slate-800"><td class="py-2.5 font-mono text-xs">{{ $journal->number }}</td><td>{{ str($journal->source_type)->replace('_', ' ') }}</td><td>{{ $journal->journal_date->format('d/m/Y') }}</td><td class="text-right font-mono tabular-nums">{{ number_format((float) ($journal->entries->sum('debit') ?: 0), 0, ',', '.') }}</td></tr>@endforeach</tbody></table>
</x-ui.card>
@endif
</div>

<h2 class="mt-10 text-lg font-black tracking-tight print:hidden">Akses Cepat</h2>
<div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4 print:hidden">
@if(auth()->user()->hasPermission('tender.view',$company->id))<a href="/admin/tenders" class="card-lift rounded-[var(--radius-card)] border bg-white p-5 shadow-[var(--shadow-card)]"><strong>Marketing & Tender</strong><p class="mt-2 text-sm leading-relaxed text-slate-500">Pelanggan, tender, hasil dan konversi proyek.</p></a>@endif
@if(auth()->user()->hasPermission('project.view',$company->id))<a href="/admin/projects" class="card-lift rounded-[var(--radius-card)] border bg-white p-5 shadow-[var(--shadow-card)]"><strong>Project & Bored Pile</strong><p class="mt-2 text-sm leading-relaxed text-slate-500">Zona, titik, progress dan concrete overbreak.</p></a>@endif
@if(auth()->user()->hasPermission('procurement.view',$company->id))<a href="/admin/procurement" class="card-lift rounded-[var(--radius-card)] border bg-white p-5 shadow-[var(--shadow-card)]"><strong>Procurement</strong><p class="mt-2 text-sm leading-relaxed text-slate-500">Vendor, PO versi, three-way matching.</p></a>@endif
@if(auth()->user()->hasPermission('finance.view',$company->id))<a href="/admin/billing" class="card-lift rounded-[var(--radius-card)] border bg-white p-5 shadow-[var(--shadow-card)]"><strong>Billing & Pajak</strong><p class="mt-2 text-sm leading-relaxed text-slate-500">Progress billing, retensi, PPN dan bukti potong.</p></a>@endif
</div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    if (!window.Chart) return;
    const dark = document.documentElement.classList.contains('dark');
    Chart.defaults.color = dark ? '#94a3b8' : '#334155';
    Chart.defaults.borderColor = dark ? '#22304d' : '#e2e8f0';
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    const brand = getComputedStyle(document.documentElement).getPropertyValue('--brand-primary').trim() || '#0369a1';
    const palette = [brand, '#0891b2', '#7c3aed', '#f59e0b', '#10b981'];
    @if($revenueTrend && $revenueTrend->isNotEmpty())
    new Chart(document.getElementById('chart-revenue'), {
        type: 'bar',
        data: { labels: @json($revenueTrend->pluck('label')), datasets: [
            { label: 'DPP Billing', data: @json($revenueTrend->pluck('dpp')), backgroundColor: brand, borderRadius: 8 },
            { label: 'PPN Keluaran', data: @json($revenueTrend->pluck('tax')), backgroundColor: '#f59e0b', borderRadius: 8 },
        ]},
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { ticks: { callback: v => 'Rp ' + (v/1e6) + ' jt' } } }, plugins: { legend: { position: 'bottom' } } }
    });
    @endif
    @if($pileStatus && $pileStatus->isNotEmpty())
    new Chart(document.getElementById('chart-piles'), {
        type: 'doughnut',
        data: { labels: @json($pileStatus->keys()->map(fn ($s) => str($s)->replace('_', ' ')->title())), datasets: [{ data: @json($pileStatus->values()), backgroundColor: palette, borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
    });
    @endif
    @if($aging)
    new Chart(document.getElementById('chart-aging'), {
        type: 'bar',
        data: { labels: @json($aging['buckets']->keys()), datasets: [{ label: 'Outstanding', data: @json($aging['buckets']->values()), backgroundColor: ['#10b981', '#f59e0b', '#f97316', '#ef4444'], borderRadius: 8 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => 'Rp ' + Number(ctx.raw).toLocaleString('id-ID') } } }, scales: { x: { ticks: { callback: v => (v/1e6) + ' jt' } } } }
    });
    @endif
});
</script>
</x-layouts.app>
