<x-layouts.app title="Ikhtisar Keuangan">
<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-2xl font-bold tracking-tight">Ikhtisar Keuangan</h1>
<p class="mt-2 text-slate-500">Posisi kas, piutang, utang, dan pendapatan dalam satu layar â€” sumber data dari jurnal posted.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif

<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
<article class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Saldo Kas & Bank (GL)</p><p class="mt-1 text-2xl font-black">Rp {{ number_format($cashBalance, 0, ',', '.') }}</p><a href="/admin/cash-bank" class="text-xs font-bold text-sky-700">Kas & Bank â†’</a></article>
<article class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Piutang Outstanding</p><p class="mt-1 text-2xl font-black">Rp {{ number_format((float) $arOutstanding, 0, ',', '.') }}</p><a href="/admin/reports/aging" class="text-xs font-bold text-sky-700">Aging â†’</a></article>
<article class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Utang Outstanding</p><p class="mt-1 text-2xl font-black">Rp {{ number_format((float) $apOutstanding, 0, ',', '.') }}</p><a href="/admin/procurement-accounting" class="text-xs font-bold text-sky-700">Posting â†’</a></article>
<article class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Pendapatan YTD</p><p class="mt-1 text-2xl font-black">Rp {{ number_format($revenueYtd, 0, ',', '.') }}</p><p class="text-xs text-slate-500">MTD: Rp {{ number_format($revenueMtd, 0, ',', '.') }}</p></article>
</div>

<div class="mt-6 grid gap-4 sm:grid-cols-2">
<a href="/admin/billing" class="card-lift rounded-2xl border bg-white p-5 shadow-sm"><strong>Progress Billing & Retensi</strong><p class="mt-1 text-sm text-slate-500">{{ $pendingBillings }} dokumen menunggu proses.</p></a>
<a href="/admin/procurement-accounting" class="card-lift rounded-2xl border bg-white p-5 shadow-sm"><strong>Invoice Vendor & Matching</strong><p class="mt-1 text-sm text-slate-500">{{ $openVendorInvoices }} invoice belum selesai dicocokkan.</p></a>
<a href="/admin/taxes" class="card-lift rounded-2xl border bg-white p-5 shadow-sm"><strong>Pajak & Bukti Potong</strong></a>
<a href="/admin/project-costing" class="card-lift rounded-2xl border bg-white p-5 shadow-sm"><strong>Project Costing & EAC</strong></a>
<a href="/admin/fixed-assets" class="card-lift rounded-2xl border bg-white p-5 shadow-sm"><strong>Fixed Asset & Depresiasi</strong></a>
<a href="/admin/reports/financial-statements" class="card-lift rounded-2xl border bg-white p-5 shadow-sm"><strong>Trial Balance & Laporan Keuangan</strong></a>
</div>

<div class="mt-10 overflow-x-auto rounded-2xl border bg-white p-6 shadow-sm">
<div class="flex items-center justify-between"><h2 class="font-bold">Jurnal Terbaru</h2><a href="/admin/finance/journals" class="text-xs font-bold text-sky-700">Buku Besar â†’</a></div>
<table class="mt-3 w-full text-sm table-sticky"><thead><tr><th>Nomor</th><th>Sumber</th><th>Tanggal</th><th class="text-right">Nilai</th></tr></thead><tbody>
@forelse($recentJournals as $journal)
<tr><td class="font-mono text-xs">{{ $journal->number }}</td><td>{{ str($journal->source_type)->replace('_', ' ') }}</td><td>{{ $journal->journal_date->format('d/m/Y') }}</td><td class="text-right font-mono">{{ number_format((float) ($journal->entries->sum('debit') ?: 0), 0, ',', '.') }}</td></tr>
@empty
<tr><td colspan="4" class="p-8 text-center text-slate-500">Belum ada jurnal posted.</td></tr>
@endforelse
</tbody></table>
<article class="mt-6 rounded-2xl border bg-white p-5 shadow-sm">
<h2 class="font-bold">Proyeksi Arus Kas (kumulatif)</h2>
<p class="mt-1 text-xs text-slate-500">Dari outstanding AR/AP berdasarkan jatuh tempo — payroll & kewajiban lain menunggu sumber data.</p>
<div class="mt-3 overflow-x-auto"><table class="w-full text-sm"><thead><tr><th>Window</th><th class="text-right">Masuk (AR)</th><th class="text-right">Keluar (AP)</th><th class="text-right">Neto</th></tr></thead><tbody>
@foreach([7,30,90] as $w)
<tr class="border-t"><td>&le; {{ $w }} hari</td>
<td class="text-right font-mono text-emerald-700">{{ number_format((float) $forecast['inflow']['d'.$w], 0, ',', '.') }}</td>
<td class="text-right font-mono text-red-600">{{ number_format((float) $forecast['outflow']['d'.$w], 0, ',', '.') }}</td>
<td class="text-right font-mono font-bold {{ (float) $forecast['net']['d'.$w] >= 0 ? 'text-emerald-700' : 'text-red-600' }}">{{ number_format((float) $forecast['net']['d'.$w], 0, ',', '.') }}</td></tr>
@endforeach
</tbody></table></div>
</article>
</div>
</section>
</x-layouts.app>
