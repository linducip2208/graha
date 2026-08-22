<x-layouts.app title="Executive Dashboard"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<p class="text-xs font-bold uppercase tracking-widest text-sky-700">{{ $company->code }}</p>
<h1 class="mt-1 text-3xl font-black">Ringkasan Operasional</h1>
<p class="mt-2 text-slate-500">Angka kunci {{ $company->name }} sesuai kewenangan Anda — diperbarui real-time dari dokumen posted.</p>

<div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
@forelse($stats as $label => $stat)
<x-ui.stat-card :label="$label" :value="is_numeric($stat) ? number_format($stat, 0, ',', '.') : (is_array($stat) ? (is_string($stat['value']) && str_starts_with($stat['value'], 'Rp') ? $stat['value'] : number_format((float) $stat['value'], 0, ',', '.')) : '-')" :hint="$stat['hint'] ?? ''" />
@empty
<x-ui.empty icon="dashboard" title="Belum ada widget untuk kewenangan Anda" description="Hubungi Company Admin bila akses operasional diperlukan." />
@endforelse
</div>

@if($revenueTrend && $revenueTrend->isNotEmpty())
<div class="mt-8 grid gap-5 lg:grid-cols-5">
<article class="rounded-2xl border bg-white p-6 shadow-sm lg:col-span-3">
<h2 class="font-bold">Tren Pendapatan & PPN Keluaran</h2>
<p class="mb-4 text-xs text-slate-500">Dari progress billing posted 6 bulan terakhir.</p>
<div class="relative h-64"><canvas id="chart-revenue"></canvas></div>
</article>
@if($pileStatus && $pileStatus->isNotEmpty())
<article class="rounded-2xl border bg-white p-6 shadow-sm lg:col-span-2">
<h2 class="font-bold">Status Titik Bored Pile</h2>
<p class="mb-4 text-xs text-slate-500">Distribusi seluruh titik lintas proyek.</p>
<div class="relative h-64"><canvas id="chart-piles"></canvas></div>
</article>
@endif
</div>
@endif

@if($aging)
<div class="mt-8 grid gap-5 lg:grid-cols-2">
<article class="rounded-2xl border bg-white p-6 shadow-sm">
<h2 class="font-bold">Aging Piutang & Utang</h2>
<p class="mb-4 text-xs text-slate-500">Outstanding per bucket umur pada tanggal hari ini.</p>
<div class="relative h-60"><canvas id="chart-aging"></canvas></div>
</article>
<article class="overflow-x-auto rounded-2xl border bg-white p-6 shadow-sm">
<h2 class="font-bold mb-1">Ringkasan Bucket</h2>
<p class="mb-3 text-xs text-slate-500">Total piutang Rp {{ number_format((float) $aging['ar_total'], 0, ',', '.') }} · total utang Rp {{ number_format((float) $aging['ap_total'], 0, ',', '.') }}</p>
<table class="w-full text-sm table-sticky"><thead><tr><th>Bucket</th><th class="text-right">Nilai</th></tr></thead><tbody>@foreach($aging['buckets'] as $bucket => $total)<tr><td>{{ $bucket }}</td><td class="text-right font-mono">{{ number_format((float) $total, 2, ',', '.') }}</td></tr>@endforeach</tbody></table>
</article>
</div>
@endif

<div class="mt-8 grid gap-5 lg:grid-cols-2">
@if($approvals !== null)
<article class="rounded-2xl border bg-white p-6 shadow-sm">
<div class="flex items-center justify-between"><h2 class="font-bold">Menunggu Persetujuan</h2><a href="/admin/approvals" class="text-xs font-bold text-sky-700">Approval Center →</a></div>
<div class="mt-3 space-y-2">@forelse($approvals as $approval)
<div class="flex items-center justify-between rounded-xl border p-3 text-sm"><div><strong>{{ class_basename($approval->approvable_type) }} #{{ $approval->approvable_id }}</strong><span class="block text-xs text-slate-500">Tahap {{ $approval->current_sequence }} @if($approval->due_at)· SLA {{ $approval->due_at->format('d/m H:i') }}@endif</span></div>@if($approval->due_at && $approval->due_at->isPast())<x-ui.badge status="exception" label="overdue" />@else<x-ui.badge status="pending_approval" label="pending" />@endif</div>
@empty<x-ui.empty icon="check" title="Tidak ada dokumen menunggu" description="Semua approval dalam batas SLA." /></@endforelse</div>
</article>
@endif
@if($journals->isNotEmpty())
<article class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
<div class="flex items-center justify-between"><h2 class="font-bold">Jurnal Terbaru</h2><a href="/admin/finance/journals" class="text-xs font-bold text-sky-700">Buku Besar →</a></div>
<table class="mt-3 w-full text-sm"><thead><tr><th>Nomor</th><th>Sumber</th><th>Tanggal</th><th class="text-right">Nilai</th></tr></thead><tbody>@foreach($journals as $journal)<tr><td class="font-mono text-xs">{{ $journal->number }}</td><td>{{ str($journal->source_type)->replace('_', ' ') }}</td><td>{{ $journal->journal_date->format('d/m/Y') }}</td><td class="text-right font-mono">{{ number_format((float) ($journal->entries->sum('debit') ?: 0), 0, ',', '.') }}</td></tr>@endforeach</tbody></table>
</article>
@endif
</div>

<h2 class="mt-10 text-xl font-black print:hidden">Akses Cepat</h2>
<div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4 print:hidden">
@if(auth()->user()->hasPermission('tender.view',$company->id))<a href="/admin/tenders" class="card-lift rounded-2xl border bg-white p-5"><strong>Marketing & Tender</strong><p class="mt-2 text-sm text-slate-500">Pelanggan, tender, hasil dan konversi proyek.</p></a>@endif
@if(auth()->user()->hasPermission('project.view',$company->id))<a href="/admin/projects" class="card-lift rounded-2xl border bg-white p-5"><strong>Project & Bored Pile</strong><p class="mt-2 text-sm text-slate-500">Zona, titik, progress dan concrete overbreak.</p></a>@endif
@if(auth()->user()->hasPermission('procurement.view',$company->id))<a href="/admin/procurement" class="card-lift rounded-2xl border bg-white p-5"><strong>Procurement</strong><p class="mt-2 text-sm text-slate-500">Vendor, PO versi, three-way matching.</p></a>@endif
@if(auth()->user()->hasPermission('finance.view',$company->id))<a href="/admin/billing" class="card-lift rounded-2xl border bg-white p-5"><strong>Billing & Pajak</strong><p class="mt-2 text-sm text-slate-500">Progress billing, retensi, PPN dan bukti potong.</p></a>@endif
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
    const palette = ['#0284c7', '#0891b2', '#7c3aed', '#f59e0b', '#10b981'];
    @if($revenueTrend && $revenueTrend->isNotEmpty())
    new Chart(document.getElementById('chart-revenue'), {
        type: 'bar',
        data: { labels: @json($revenueTrend->pluck('label')), datasets: [
            { label: 'DPP Billing', data: @json($revenueTrend->pluck('dpp')), backgroundColor: '#0284c7', borderRadius: 6 },
            { label: 'PPN Keluaran', data: @json($revenueTrend->pluck('tax')), backgroundColor: '#f59e0b', borderRadius: 6 },
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
        data: { labels: @json($aging['buckets']->keys()), datasets: [{ label: 'Outstanding', data: @json($aging['buckets']->values()), backgroundColor: ['#10b981', '#f59e0b', '#f97316', '#ef4444'], borderRadius: 6 }] },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => 'Rp ' + Number(ctx.raw).toLocaleString('id-ID') } } }, scales: { x: { ticks: { callback: v => (v/1e6) + ' jt' } } } }
    });
    @endif
});
</script>
</x-layouts.app>
