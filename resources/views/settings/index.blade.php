<x-layouts.app title="Pengaturan"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-3xl font-black">Pengaturan</h1>
<p class="mt-2 text-slate-500">Pusat konfigurasi ERP: organisasi, akuntansi, pajak, approval, dan penandatanganan digital. Semua nilai di sini mengubah perilaku transaksi tanpa perlu ubah kode.</p>

<div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
@if($canOrganization)
<article class="card-lift rounded-2xl border bg-white p-6">
<h2 class="font-bold">🏢 Organisasi & Keanggotaan</h2>
<p class="mt-2 text-sm text-slate-500">Perusahaan, cabang, departemen, serta role & permission per membership.</p>
<a href="/admin/organization" class="mt-4 inline-block rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Kelola organisasi</a>
</article>
@endif
@if($canFinance)
<article class="card-lift rounded-2xl border bg-white p-6">
<h2 class="font-bold">⇄ Accounting Mapping</h2>
<p class="mt-2 text-sm text-slate-500">Pemetaan setiap jenis transaksi ke akun debit/kredit — wajib lengkap sebelum posting.</p>
<a href="/admin/finance/accounting-mappings" class="mt-4 inline-block rounded-xl bg-sky-700 px-4 py-2 text-sm font-bold text-white">Buka mapping</a>
</article>
<article class="card-lift rounded-2xl border bg-white p-6">
<h2 class="font-bold">% Tarif Pajak</h2>
<p class="mt-2 text-sm text-slate-500">PPN keluaran/masukan dan pemotongan PPh. Aktif: <strong>{{ $activeTaxRates }}</strong> dari {{ $taxRates }} tarif.</p>
<a href="/admin/taxes" class="mt-4 inline-block rounded-xl bg-amber-600 px-4 py-2 text-sm font-bold text-white">Kelola tarif</a>
</article>
<article class="card-lift rounded-2xl border bg-white p-6">
<h2 class="font-bold">🔢 Nomor Dokumen</h2>
<p class="mt-2 text-sm text-slate-500">Prefix & padding sequence per jenis dokumen (jurnal, tender, proyek, dll).</p>
<div class="mt-3 space-y-1.5 text-xs font-mono text-slate-600">@forelse($sequences as $sequence)<div class="flex justify-between rounded-lg bg-slate-50 px-3 py-1.5"><span>{{ $sequence->document_type }}</span><span>{{ $sequence->prefix }}/{{ $sequence->padding ?? 4 }} digit</span></div>@empty<p class="text-slate-400">Belum ada sequence.</p>@endforelse</div>
</article>
@endif
@if($canApprove)
<article class="card-lift rounded-2xl border bg-white p-6">
<h2 class="font-bold">✓ Approval Workflow</h2>
<p class="mt-2 text-sm text-slate-500">Tahapan, mode (any/all/quorum), SLA per dokumen. Workflow aktif: <strong>{{ $workflows }}</strong>.</p>
<a href="/admin/approvals" class="mt-4 inline-block rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Kelola workflow</a>
</article>
@endif
@if($canSignature)
<article class="card-lift rounded-2xl border bg-white p-6">
<h2 class="font-bold">✎ Provider Tanda Tangan</h2>
<p class="mt-2 text-sm text-slate-500">Konfigurasi penyedia signing eksternal (API format generik, secret terenkripsi). Terdaftar: <strong>{{ $providers }}</strong>.</p>
<a href="/admin/signatures" class="mt-4 inline-block rounded-xl bg-violet-700 px-4 py-2 text-sm font-bold text-white">Kelola provider</a>
</article>
@endif
@if($canFinance)
<article class="card-lift rounded-2xl border bg-white p-6">
<h2 class="font-bold">📅 Periode Fiskal</h2>
<p class="mt-2 text-sm text-slate-500">Status open/closed mengontrol seluruh posting jurnal; closing butuh approval + rekonsiliasi bank.</p>
<a href="/admin/finance/periods" class="mt-4 inline-block rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Lihat periode</a>
</article>
@endif
</div>
</section></x-layouts.app>
