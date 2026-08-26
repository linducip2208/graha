# Demo Data (Enterprise Demo Dataset v2)

Status per 2026-08-26 (ADR-079).

## Prinsip

1. **Deterministik** — tanpa `rand()`; nilai dari fixture tabel tetap supaya
   screenshot, test, dan dashboard risiko konsisten.
2. **Idempotent** — `db:seed` berulang tidak menduplikasi PO/jurnal/invoice/
   pile/test/evidence/acceptance. Kunci stabil (`firstOrCreate` + idempotency
   key `demo-*`, nomor dokumen tetap).
3. **Production-safe** — demo data TIDAK PERNAH otomatis ter-seed di
   production.
4. **Penanda andal** — kolom `is_demo` pada `companies` dan `projects`;
   reset dapat menyasar data demo secara presisi.

## Perintah

```bash
# Baseline saja (company + super admin + permission) — aman di mana pun:
php artisan db:seed

# Demo dataset eksplisit:
php artisan db:seed --class=DemoDataSeeder

# Reset penuh + isi demo (DESTRUKTIF; hanya local/demo/testing):
php artisan demo:reset            # dengan konfirmasi
php artisan demo:reset --force    # tanpa konfirmasi
```

Guard otomatis: `DatabaseSeeder::shouldSeedDemo()` → hanya jalan bila
environment local/demo **dan** `SEED_DEMO_DATA=true`. Production menolak
selalu. `.env.example` menyertakan `SEED_DEMO_DATA` dan `DEMO_SEED_STORAGE`.

## Struktur seeder

| Seeder | Isi |
|---|---|
| DemoOrganizationSeeder | 12 user per role fungsional, role+permission, 2 cabang, 5 departemen |
| DemoCommercialSeeder | 4 customer, 8 vendor, 5 tender (won/lost/submitted/evaluation/no-bid) + outcome & peserta kompetitor, 3 kontrak (2 aktif + 1 completed) dengan milestone/asuransi/korespondensi |
| DemoProjectSeeder | 4 proyek: PRJ-2601 healthy, PRJ-2602 at-risk, PRJ-2603 near-completion, PRJ-2604 planning + zona + WBS + cost code |
| DemoFinanceSeeder | COA 12 akun, 16 mapping, 4 tarif pajak, periode fiskal, billing→AR→penerimaan bukti potong, statement bank |
| DemoFoundationSeeder | 64 pile + bore log/lapisan, cleaning inspection, slurry tests, tremie logs, delivery 3 truk + pour interval, cage QC, pile test, snapshot readiness |
| DemoSupplyChainSeeder | gudang/bin/item/stok awal, alur PO→approval→GR→invoice→match→payment PPh23, 5 equipment (rig×2, crane, excavator, genset), meter log, BBM, downtime breakdown |
| DemoQmsHseSeeder | ITP + hold points, NCR open/closed/supplier-overdue, kalibrasi overdue, keluhan pelanggan resolved, JSA aktif, observasi, insiden near-miss, PPE, exposure manhours (FR/SR/TRIR) |
| DemoDocumentSeeder | registry dokumen multi-versi (draft/approved/superseded/signed) + transmittal + signature internal; PDF placeholder lokal bertanda DEMO/SAMPLE |

## Skenario risiko (Risk Radar)

Pile A healthy; B high overbreak (beton +28%); C slump fail (5 cm);
D truck gap 3 jam; E cage QC failed; F open NCR kritis tertaut uji;
G test failed (pile rejected); H evidence kurang (bila rules aktif);
I durasi drilling abnormal (>3× median). Hasil: radar memuat healthy,
watch, dan critical.

## Storage demo

Default `DEMO_SEED_STORAGE=false` → binary demo (PNG/PDF placeholder) hanya ke
disk `local`, tidak pernah terkirim ke production S3/R2 secara tak sengaja.
Set `true` untuk sengaja mengisi EVIDENCE_DISK.

## Tanggal

Semua tanggal relatif terhadap `now()` (today−90 … today … today+30) sehingga
demo selalu terlihat current, namun tetap deterministik per seed run.
