# Architecture Decisions

- ADR-001 Accepted: modular monolith Laravel untuk konsistensi transaksi lintas domain.
- ADR-002 Accepted: shared database dengan explicit company context dari membership aktif; bukan public SaaS.
- ADR-003 Accepted: RBAC di-assign per membership perusahaan, bukan hard-coded pada user.
- ADR-004 Accepted: audit append-only dengan hash chain; privilege DB diperketat saat deployment.
- ADR-005 Accepted: approval polymorphic, sequential foundation, idempotent, dan melarang self-approval.
- ADR-006 Accepted: standard/edition/amendment/clause ISO kelak configurable; tidak menyalin standar dan tidak mengklaim sertifikasi.
- ADR-007 Pending: BPM Level 0 wajib direkonsiliasi setelah workbook tersedia.
- ADR-008 Accepted: accounting posting terpusat, idempotent, period-controlled, memakai decimal/BCMath dan menolak jurnal tidak seimbang.
- ADR-009 Accepted: stock movement adalah ledger immutable; mutasi saldo memakai transaksi serta row lock dan stok negatif ditolak tanpa override berwenang.
- ADR-010 Accepted: pemilik corrective action tidak boleh menjadi verifier efektivitas, dan auditor tidak boleh sama dengan auditee.
- ADR-011 Accepted: revisi PO menyimpan snapshot versi lama dan men-supersede approval aktif; receipt dibatasi kuantitas PO dan invoice memakai three-way matching.
- ADR-012 Accepted: posting procurement memakai event accounting mapping configurable; invoice exception tidak boleh masuk AP.
- ADR-013 Accepted: signature provider dikonfigurasi berdasarkan format API generik; signature selalu mengikat document version dan SHA-256 tertentu.
- ADR-014 Accepted: progress billing memisahkan gross, retention, advance recovery dan net AR; posting hanya setelah approval serta memakai mapping akun configurable.
- ADR-015 Accepted: penerimaan/pembayaran bank memakai jurnal idempotent dan batas outstanding; period closing ditolak sebelum approval atau selama statement belum direkonsiliasi.
- ADR-016 Accepted: release retensi direklasifikasi dari retention receivable ke AR hanya sebesar saldo retensi posted dan setelah approval.
- ADR-017 Accepted: actual cost bersumber dari immutable project cost ledger; forecast ETC adalah snapshot terpisah dan EAC dihitung saat baca untuk mencegah angka turunan basi.
- ADR-018 Accepted: depresiasi aset memakai straight-line berbasis decimal, satu posting per aset/periode, dan mapping akun configurable.
- ADR-019 Accepted: Manufacturing Control menjadi workspace fokus; setiap perpindahan Raw Material/WIP/Finished Goods wajib mempunyai stock movement dan jurnal idempotent yang saling terkait.
- ADR-020 Accepted: production completion tidak boleh melebihi kuantitas yang diterima Quality Control; rejected output tidak menambah finished goods.
- ADR-021 Accepted: output produksi ditolak wajib diputuskan sebagai rework atau scrap; scrap mereklasifikasi biaya proporsional dari Manufacturing WIP ke akun biaya scrap configurable dan tidak menambah stok barang jadi.
- ADR-022 Accepted: biaya konversi produksi dihitung dari jam aktual dikali tarif work center; tenaga kerja dan overhead diserap ke WIP melalui mapping akun configurable, sementara waktu standar routing menjadi baseline variance.
- ADR-023 Accepted: production completion menyimpan biaya yang telah ditransfer dan melakukan final true-up atas saldo WIP; biaya scrap disimpan per disposition untuk mencegah biaya ganda atau residual WIP tersembunyi.
- ADR-024 Accepted: period closing ditolak bila production order terminal masih memiliki residual WIP; order aktif boleh membawa WIP dengan rekonsiliasi eksplisit.
- ADR-025 Accepted: material issue dibatasi kebutuhan BOM yang diskalakan terhadap planned output dan allowance scrap; output yang seluruhnya selesai/scrap ditutup sebagai `completed_with_scrap`.
- ADR-026 Accepted: trial balance, laba rugi, dan neraca dihitung hanya dari jurnal posted; laba periode ditampilkan sebagai komponen rekonsiliasi liabilitas dan ekuitas tanpa membuat jurnal otomatis terselubung.
- ADR-027 Accepted: aging AR/AP menghitung saldo dari dokumen posted/matched dikurangi receipt/payment posted sampai cut-off; invoice vendor tanpa due date memakai default operasional 30 hari yang harus dikonfigurasi pada kebijakan Finance berikutnya.
- ADR-028 Accepted: bore log disimpan sebagai tabel lapisan ternormalisasi (sequence, depth_from/to, deskripsi) agar dapat difilter/dilaporkan; verifikasi drilling menuntut perekam berbeda dari verifikator.
- ADR-029 Accepted: volume beton aktual pile adalah fungsi agregat delivery approved (single source of truth); input manual volume pada pile tidak digunakan lagi untuk pile dengan delivery; overbreak dihitung ulang otomatis terhadap teoretis dan toleransi proyek.
- ADR-030 Accepted: transisi pile ke completed ditolak bila masih ada uji scheduled; bila company setting require_pile_test_pass aktif, minimal satu hasil passed wajib ada. Approval konsultan direkam terpisah dari perekam hasil.
- ADR-032 Accepted: stock opname memisahkan penghitung dan approver; approval memposting adjustment via ledger service yang sama dengan transaksi harian sehingga aturan negative-stock dan unit-cost konsisten; key idempotency opname:{count}:{line} mencegah double posting.
- ADR-033 Accepted: seleksi pemenang RFK menutup RFQ secara transaksional, menandai quotation lain rejected, dan invitation tanpa respons ditandai no_response; PO tetap dibuat melalui alur PO existing agar revision snapshot tidak diduplikasi.
- ADR-034 Accepted: kartu stok tangki BBM memakai liter bertanda (masuk positif, keluar negatif) dengan idempotency per tangki; rekonsiliasi fisik menuliskan penyesuaian reading_adjustment terpisah sehingga selisih selalu terlacak, bukan ditimpa.
- ADR-035 Accepted: reinforcement cage memakai QC independen (pembuat != pemeriksa) dengan gate berat: selisih aktual vs teoretis melebihi toleransi perusahaan menolak kelulusan; pengiriman hanya untuk cage passed ke titik berstatus cleaning/inspection/cage_installation, satu cage terkirim per titik.
- ADR-036 Accepted: transisi bored pile inspection -> cage_installation dapat diwajibkan menunggu cage lolos QC terkirim melalui company setting require_cage_passed (default off agar migrasi data lama aman).
- ADR-037 Accepted: pengalaman compiler Blade - hindari match()/ternary kompleks dan @if inline di dalam atribut komponen pada satu elemen; kondisi dipindah ke @php variabel sebelum HTML.
- ADR-038 Accepted: fondasi multi-currency hanya menyediakan lookup kurs efektif-terakhir dan konversi ke IDR untuk agregasi laporan; jurnal tetap dicatat dalam mata uang transaksi, dan posting selisih kurs ditunda sampai kebijakan realized/unrealized disepakati.
- ADR-039 Accepted: tanda tangan digital memakai mode INTERNAL saja untuk seluruh alur operasional agar pengalaman user sederhana; provider eksternal tetap tersedia di backend (webhook + adapter) namun disembunyikan dari UI sampai ada kebutuhan sertifikasi elektronik resmi.
- ADR-040 Accepted: selisih kurs hanya diakui saat REALIZED (pelunasan kas dokumen ber mata uang asing); baris FX gain/loss via accounting mapping configurable menyesuaikan pengakuan AR/AP ke kurs dokumen sehingga jurnal tetap seimbang; unrealized revaluation tidak dilakukan.
- ADR-041 Accepted: foto evidence lapangan (drilling/delivery/test/cage/casing/tool) disimpan pada disk privat configurable (`EVIDENCE_DISK`, local atau S3-compatible R2); unduhan selalu ber-authorization, disk non-local mengeluarkan temporary URL berbatas waktu.
- ADR-042 Accepted: dokumen akhir tata kelola (MWO ditutup, NCR tertutup, CAPA efektif) otomatis terdaftar di document registry secara idempotent dengan pola `Document::firstOrCreate` yang sama seperti billing posted dan PO activated.
- ADR-043 Accepted: jatuh tempo default invoice vendor memakai company setting `default_vendor_payment_term_days` (default 30) sehingga aging AP tidak lagi bergantung angka hardcoded; termin pelanggan tetap per-customer dengan fallback `default_payment_term_days`.
- ADR-044 Accepted: pencatatan fuel equipment dapat memotong tangki BBM terpilih sebagai transaksi `issue_to_equipment` ter-audit dalam transaksi yang sama dengan FuelUsage; saldo tangki tidak boleh negatif dan tanpa tangki perilaku lama tetap berjalan.
- ADR-045 Accepted: konsumsi material baja fabrikasi cage memakai stock ledger immutable yang sama dengan modul lain (referensi `reinforcement_cage`) plus jurnal mapping `material_issue` tanpa dimensi proyek; ditolak setelah cage terkirim dan bila nilai jurnal nihil.
- ADR-046 Accepted: pergerakan casing berbiaya otomatis menjurnal sewa via mapping configurable (`expense_debit`/`payable_credit`); tanpa biaya tidak ada jurnal, dan biaya tanpa mapping ditolak agar tidak ada biaya operasional yang lolos di luar GL.
- ADR-048 Accepted: keputusan Bid/No-Bid memakai scoring faktor data nyata (margin, cover HPS, kompetisi, termin) dengan bobot/ambang configurable; faktor tanpa data tidak dikarang dan memaksa hasil Perlu Review â€” bukan pengganti keputusan manusia.
- ADR-049 Accepted: constraint log proyek memakai transisi open â†’ in_progress â†’ resolved yang terjaga; resolusi wajib catatan dan kendala selesai tidak dibuka ulang (buat entri baru).
- ADR-050 Accepted: rencana pengadaan adalah baris per proyek dengan tanggal dibutuhkan; keterlambatan = lewat tanggal tanpa PO dan tidak dibatalkan; PR/PO nyata ditautkan eksplisit dengan validasi dokumen.
- ADR-051 Accepted: EVM ringkas (CPI/SPI) dihitung saat baca dari EV progres terkontrak, PV jadwal, dan AC project cost ledger; hanya tampil bila AC>0 â€” tanpa input manual baru.
- ADR-052 Accepted: cash flow forecast 7/30/90 hari dihitung dari outstanding AR/AP berdasar jatuh tempo (sumber aging); komponen tanpa sumber data (payroll dll) tidak dikarang.
- ADR-053 Accepted: budget proyek memakai baseline versi snapshot immutable; hanya satu approved aktif per proyek yang menjadi Revised Budget pada costing, fallback estimated_cost bila belum ada.

> Catatan penomoran: sebagian commit message gelombang Digital Twin & Experience
> memakai nomor ADR yang tumpang tindih dengan daftar di atas. Registry kanonis
> adalah file ini; keputusan gelombang tersebut didokumentasikan ulang di bawah
> dengan nomor berurutan mulai ADR-066.

- ADR-066 Accepted: penyimpanan objek abstrak via `StoredFile` (metadata registry + SHA-256 checksum); serving file privat selalu ber-authorization per company, disk non-local memakai temporary URL berbatas waktu.
- ADR-067 Accepted: setiap bored pile mempunyai Digital Pile Passport (QR publik terverifikasi tanpa data sensitif + timeline evidence foto) sebagai identitas digital siklus hidup pile.
- ADR-068 Accepted: dokumen as-built dan acceptance dossier dibuat melalui Document Registry versioned (regenerasi = versi baru, tidak pernah overwrite); nomor memakai NumberSequence per company.
- ADR-069 Accepted: penerimaan pile (acceptance) mengikuti lifecycle pending -> qa_review -> engineer_review -> accepted/rejected/conditional dengan gate dari data nyata (konstruksi tuntas, uji, NCR tertaut, as-built teregistrasi, survey aktual); aksi berjenjang dipisah per permission.
- ADR-070 Accepted: aturan minimal foto evidence per kategori bersifat per company dan default OFF agar migrasi data lama aman.
- ADR-071 Accepted: paket serah terima (handover) adalah ZIP berisi as-built + dossier + MANIFEST.csv yang diunggah ke object storage dan didaftarkan di registry; pile belum accepted masuk exception list, bukan diblokir diam-diam.
- ADR-072 Accepted: Foundation Control Tower menampilkan risiko pile secara deterministik (HEALTHY/WATCH/CRITICAL) dari sinyal data nyata (depth mismatch, overbreak, slump, gap antar-truk, cage QC gagal, uji gagal/missing, NCR terbuka, durasi drilling abnormal vs median, evidence kurang); tanpa skor karangan, tanpa ML.
