<x-layouts.app title="Pengaturan"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-2xl font-bold tracking-tight">Pengaturan</h1>
<p class="mt-2 text-slate-500">Nilai default perusahaan yang dipakai seluruh modul, plus pintasan ke konfigurasi lanjutan. Semua perubahan berlaku untuk dokumen baru tanpa menyentuh kode.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

@if($canFinance)
<form method="post" action="/admin/settings" class="mt-8 space-y-6">
@csrf
<x-ui.form-section title="Identitas Perusahaan" description="Dipakai pada kop faktur tagihan PDF dan dokumen keluaran.">
<div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
<label class="block text-sm font-semibold">Alamat kantor<textarea name="company_address" rows="2" class="mt-1 w-full rounded-xl border p-3">{{ $values['company_address'] }}</textarea></label>
<label class="block text-sm font-semibold">Telepon<input name="company_phone" value="{{ $values['company_phone'] }}" class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">Email<input type="email" name="company_email" value="{{ $values['company_email'] }}" class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">NPWP<input name="company_npwp" value="{{ $values['company_npwp'] }}" placeholder="00.000.000.0-000.000" class="mt-1 w-full rounded-xl border p-3"></label>
</div>
</x-ui.form-section>

<x-ui.form-section title="Pengadaan, Billing & Mutu" description="Nilai awal form operasional dan gate kualitas.">
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
<label class="block text-sm font-semibold">Termin Pembayaran (hari)<input type="number" min="0" max="365" name="default_payment_term_days" value="{{ $values['default_payment_term_days'] }}" required class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">Termin Utang Vendor (hari)<input type="number" min="0" max="365" name="default_vendor_payment_term_days" value="{{ $values['default_vendor_payment_term_days'] }}" required class="mt-1 w-full rounded-xl border p-3" title="Jatuh tempo default invoice vendor tanpa due date — dipakai laporan aging AP"></label>
<label class="block text-sm font-semibold">Retensi Default (%)<input type="number" step=".0001" min="0" max="100" name="default_retention_percent" value="{{ $values['default_retention_percent'] }}" required class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">PPN Default (%)<input type="number" step=".0001" min="0" max="100" name="default_ppn_percent" value="{{ $values['default_ppn_percent'] }}" required class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">Toleransi Overbreak (%)<input type="number" step=".001" min="0" max="100" name="default_overbreak_tolerance_percent" value="{{ $values['default_overbreak_tolerance_percent'] }}" required class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">Toleransi Kedalaman Pile (%)<input type="number" step=".001" min="0" max="100" name="pile_depth_tolerance_percent" value="{{ $values['pile_depth_tolerance_percent'] }}" required class="mt-1 w-full rounded-xl border p-3" title="Ambang anomali penyimpangan kedalaman aktual vs rencana pada genealogy pile"></label>
<label class="block text-sm font-semibold">Slump Min (cm)<input type="number" step=".01" min="0" max="50" name="slump_min_cm" value="{{ $values['slump_min_cm'] }}" required class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">Slump Maks (cm)<input type="number" step=".01" min="0" max="50" name="slump_max_cm" value="{{ $values['slump_max_cm'] }}" required class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">Bobot Bid: Margin (%)<input type="number" step=".01" min="0" max="100" name="bid_weight_margin" value="{{ $values['bid_weight_margin'] }}" required class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">Bobot Bid: Cover HPS (%)<input type="number" step=".01" min="0" max="100" name="bid_weight_hps" value="{{ $values['bid_weight_hps'] }}" required class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">Bobot Bid: Kompetisi (%)<input type="number" step=".01" min="0" max="100" name="bid_weight_competition" value="{{ $values['bid_weight_competition'] }}" required class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">Bobot Bid: Termin (%)<input type="number" step=".01" min="0" max="100" name="bid_weight_payment" value="{{ $values['bid_weight_payment'] }}" required class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">Ambang Rekomendasi BID (skor)<input type="number" step=".01" min="1" max="100" name="bid_threshold_recommended" value="{{ $values['bid_threshold_recommended'] }}" required class="mt-1 w-full rounded-xl border p-3"></label>
<label class="block text-sm font-semibold">Ambang NO-BID (skor)<input type="number" step=".01" min="0" max="99" name="bid_threshold_no_bid" value="{{ $values['bid_threshold_no_bid'] }}" required class="mt-1 w-full rounded-xl border p-3"></label>
<label class="flex items-center gap-3 self-end rounded-xl border p-3 text-sm"><input type="checkbox" name="require_pile_test_pass" value="1" @checked($values['require_pile_test_pass'] === '1')> <span class="font-semibold">Wajib uji pile passed sebelum completed</span></label>
</div>
<div class="mt-4">
<label class="block text-sm font-semibold">Catatan Kaki Faktur<span class="help-text block">Teks tambahan di bagian bawah faktur PDF</span><textarea name="invoice_footer_note" rows="2" class="mt-1 w-full rounded-xl border p-3">{{ $values['invoice_footer_note'] }}</textarea></label>
</div>
</x-ui.form-section>
<div class="flex flex-wrap gap-3 items-center"><button class="rounded-xl bg-[var(--brand-primary)] px-6 py-3 font-bold text-white transition hover:bg-[var(--brand-primary-hover)]">Simpan pengaturan</button><span class="text-xs text-slate-400">Hanya role dengan izin Finance Manage yang dapat menyimpan.</span></div>
</form>
@endif

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
<a href="/admin/finance/accounting-mappings" class="mt-4 inline-block rounded-xl bg-[var(--brand-primary)] px-4 py-2 text-sm font-bold text-white">Buka mapping</a>
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
<a href="/admin/experience" class="mt-6 inline-block rounded-xl bg-gradient-to-r from-sky-700 to-cyan-700 px-6 py-3 font-bold text-white no-print">Buka Experience Studio — Tampilan & White Label</a>
</section></x-layouts.app>
