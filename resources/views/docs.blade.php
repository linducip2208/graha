<x-layouts.app title="Dokumentasi — Graha Pondasi ERP"><section class="mx-auto max-w-5xl px-6 py-12">
<p class="text-xs font-bold uppercase tracking-widest text-sky-700">Dokumentasi Produk</p>
<h1 class="mt-1 text-3xl font-bold tracking-tight">Cara Kerja ERP Graha Pondasi</h1>
<p class="mt-4 max-w-3xl text-slate-600">Sistem mendukung implementasi dan bukti penerapan sistem manajemen mutu; tidak menyatakan sertifikasi ISO. Ikuti tutorial berurutan sesuai alur bisnis kontraktor pondasi: setup → master data → pengadaan → pelaksanaan → tagihan & pajak → laporan.</p>

<nav class="sticky top-[57px] z-10 mt-8 flex gap-2 overflow-x-auto rounded-2xl border bg-white/95 p-2 backdrop-blur no-print">
@foreach([['#akun','Akun Demo'],['#menu','Struktur Menu'],['#tutorial','Tutorial 7 Fase'],['#fitur','Fitur Utama'],['#pajak','Pajak'],['#qms','QMS & Keamanan']] as [$href,$label])<a href="{{ $href }}" class="whitespace-nowrap rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">{{ $label }}</a>@endforeach
</nav>

<h2 id="akun" class="mt-12 scroll-mt-28 text-2xl font-black">Akun Demo</h2>
<div class="mt-4 overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-sm"><thead><tr><th>Role</th><th>Email</th><th>Password</th><th>Cakupan</th></tr></thead><tbody>
@foreach([
['Super Admin','admin@grahapondasi.test','Semua modul + audit trail'],
['Finance Manager','finance@grahapondasi.test','Billing, pajak, jurnal, posting, laporan keuangan'],
['Project Manager','pm@grahapondasi.test','Proyek, bored pile, equipment, HSE'],
['Procurement Officer','procurement@grahapondasi.test','Vendor, PO, goods receipt, stok'],
['Direktur Operasi','direktur@grahapondasi.test','Approval center, tender, laporan'],
] as [$role,$email,$scope])
<tr><td class="font-bold">{{ $role }}</td><td class="font-mono text-xs">{{ $email }}</td><td class="font-mono text-xs">password</td><td class="text-slate-500">{{ $scope }}</td></tr>
@endforeach</tbody></table></div>

<h2 id="menu" class="mt-12 scroll-mt-28 text-2xl font-black">Struktur Menu</h2>
<p class="mt-2 text-slate-500">Menu tampil adaptif mengikuti permission membership perusahaan Anda.</p>
<div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
@foreach(config('modules.nav') as $group)
@php($childLabels = collect($group['items'])->map(fn ($item) => empty($item['children']) ? null : collect($item['children'])->pluck('label')->implode(' · '))->filter()->implode(' | '))
<x-ui.card><p class="text-xs font-bold uppercase tracking-widest text-slate-400">{{ $group['label'] }}</p><ul class="mt-2 space-y-1.5 text-sm text-slate-700">@foreach($group['items'] as $item)<li>{{ $item['label'] }}</li>@endforeach</ul>@if($childLabels)<p class="mt-2 text-xs leading-relaxed text-slate-400">{{ $childLabels }}</p>@endif</x-ui.card>
@endforeach
</div>

<h2 id="tutorial" class="mt-12 scroll-mt-28 text-2xl font-black">Tutorial 7 Fase — Sesuai Alur Bisnis</h2>
@foreach([
['Fase 1 · Setup Organisasi & Periode', ['Buat perusahaan dan cabang di menu <strong>Perusahaan & Cabang</strong>.', 'Susun departemen bila diperlukan untuk pemilik risiko/QMS.', 'Pastikan periode fiskal tahun berjalan berstatus <strong>open</strong> di Finance.', 'Cek nomor dokumen (journal sequence) sudah tersedia.']],
['Fase 2 · Master Data', ['Daftarkan <strong>pelanggan</strong> beserta termin pembayaran (dipakai jatuh tempo aging).', 'Daftarkan <strong>vendor</strong> lengkap NPWP untuk bukti potong PPh 23.', 'Siapkan <strong>item, unit, gudang, dan bin</strong> sebelum transaksi stok.', 'Isi minimum stock pada item sebagai dasar alert stok kritis.']],
['Fase 3 · Pengadaan (PO → Receipt → Invoice)', ['Buat draft PO, kirim ke approval workflow (direksi).', 'Setelah approved, catat <strong>goods receipt</strong> — stok masuk ledger immutable.', 'Catat invoice vendor dengan DPP sesuai PO + pilih <strong>PPN Masukan</strong>; sistem menjalankan three-way matching.', 'Posting jurnal GRNI lalu AP dari menu Procurement Posting.', 'Bayar vendor dengan memilih tarif pemotongan <strong>PPh 23</strong> — isi nomor bukti potong.']],
['Fase 4 · Pelaksanaan Proyek (Bored Pile)', ['Buat proyek aktif dengan nilai kontrak dan jadwal rencana.', 'Bagi zona, tambahkan titik pile (diameter, kedalaman).', 'Transisikan status tiap titik: planned → setting out → drilling → … → completed.', 'Rekam volume beton aktual; sistem menghitung overbreak terhadap toleransi.', 'Kirim daily report lapangan setiap hari kerja.']],
['Fase 5 · Tagihan & Pajak', ['Buat draft progress billing: DPP, retensi %, uang muka, pilih <strong>PPN Keluaran 11%</strong>.', 'Submit ke approval; validasi hasil approval; posting AR.', 'Cetak <strong>faktur PDF</strong> langsung dari daftar billing.', 'Catat penerimaan pelanggan — bila dipotong PPh Final 4(2), isi bukti potong yang diterima.', 'Pantau rekap bulanan di menu <strong>Pajak & Bukti Potong</strong>.']],
['Fase 6 · Kas, Bank & Tutup Periode', ['Input baris rekening koran dari bank.', 'Rekonsiliasi statement dengan transaksi penerimaan/pembayaran.', 'Ajukan approval period closing — sistem menolak bila masih ada statement belum rekonsiliasi atau residual WIP produksi.']],
['Fase 7 · Laporan & Pengawasan', ['Laporan eksekutif/keuangan/operasional/manufaktur dengan filter periode dan export CSV.', 'Trial balance, laba rugi, neraca hanya dari jurnal posted.', 'AR/AP aging otomatis memakai termin pelanggan.', 'Audit trail hash-chain dapat difilter per event, aktor, dan tanggal.']],
] as [$faseTitle, $steps])
<article class="mt-6 rounded-2xl border bg-white p-6"><h3 class="font-black">{{ $faseTitle }}</h3><ol class="mt-3 list-decimal space-y-1.5 pl-6 text-sm leading-relaxed text-slate-700">@foreach($steps as $step)<li>{!! $step !!}</li>@endforeach</ol></article>
@endforeach

<h2 id="fitur" class="mt-12 scroll-mt-28 text-2xl font-black">Fitur Utama</h2>
<div class="mt-4 grid gap-4 sm:grid-cols-2">
@foreach([
['Approval Engine', 'Sequential, any, all, quorum, SLA per tahap, delegasi berkewenangan, larangan self-approval, notifikasi in-app + email.'],
['Jurnal Otomatis', 'Semua transaksi membentuk jurnal balanced & idempotent lewat accounting mapping configurable tanpa hardcode akun.'],
['Three-Way Matching', 'Invoice vendor dicocokkan PO vs penerimaan pada level DPP sebelum boleh masuk AP.'],
['Progress Billing', 'Contract cap, retensi, pemulihan uang muka, PPN keluaran, faktur PDF, release retensi dengan approval.'],
['Inventory Ledger', 'Stock movement immutable, anti stok negatif, alert minimum stock harian.'],
['Manufacturing Control', 'BOM, routing work center, WIP reconciliation, QC disposition (rework/scrap), biaya konversi.'],
['Equipment & HSE', 'Hour meter, anomali BBM, maintenance order, JSA/permit kerja, incident hingga management review.'],
['Audit Hash-Chain', 'Setiap aksi tercatat append-only dengan rantai hash — tamper-evident untuk audit ISO.'],
] as [$title, $desc])
<article class="card-lift rounded-2xl border bg-white p-5"><h3 class="font-bold">{{ $title }}</h3><p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $desc }}</p></article>
@endforeach
</div>

<h2 id="pajak" class="mt-12 scroll-mt-28 text-2xl font-black">Pajak dalam Sistem</h2>
<div class="mt-4 overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-sm"><thead><tr><th>Jenis</th><th>Muncul Saat</th><th>Jurnal</th></tr></thead><tbody>
<tr><td class="font-bold">PPN Keluaran</td><td>Posting progress billing bertarif</td><td class="text-slate-500">Kredit akun PPN Keluaran (mapping <code>tax_credit</code>)</td></tr>
<tr><td class="font-bold">PPN Masukan</td><td>Posting invoice vendor bertarif</td><td class="text-slate-500">Debit akun PPN Masukan (mapping <code>tax_debit</code>)</td></tr>
<tr><td class="font-bold">PPh dipotong klien</td><td>Penerimaan pelanggan dengan pemotongan</td><td class="text-slate-500">Debit Pajak Dibayar di Muka (<code>withholding_debit</code>)</td></tr>
<tr><td class="font-bold">PPh 23 vendor</td><td>Pembayaran vendor dengan pemotongan</td><td class="text-slate-500">Kredit Hutang PPh (<code>withholding_credit</code>)</td></tr>
</tbody></table></div>
<p class="mt-3 text-xs text-slate-500">Pelunasan dihitung kas + pemotongan; AR/AP dianggap lunas saat keduanya mencapai nilai dokumen.</p>

<h2 id="qms" class="mt-12 scroll-mt-28 text-2xl font-black">QMS &amp; Keamanan Data</h2>
<ul class="mt-3 list-disc space-y-1.5 pl-6 text-sm leading-relaxed text-slate-700">
<li>NCR/CAPA dengan pemisahan wajib: pemilik tindakan ≠ verifikator efektivitas; auditor ≠ auditee.</li>
<li>Bukti QMS punya masa berlaku; scheduler menandai evidence kadaluarsa dan mengirim notifikasi tenggat CAPA/NCR.</li>
<li>Audit log append-only + hash chain; viewer internal menyediakan filter event/aktor/tanggal.</li>
<li>Notifikasi SLA approval dikirim otomatis per jam sampai dokumen diputuskan (dedupe per dokumen).</li>
</ul>
</section></x-layouts.app>
