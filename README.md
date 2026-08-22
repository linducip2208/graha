# Graha Pondasi ERP

Sistem ERP terintegrasi untuk kontraktor pondasi (bored pile): dari tender, kontrak, pelaksanaan lapangan, manufacturing cage, procurement & inventory, sampai accounting Indonesia, QMS ISO 9001, HSE, document control, approval berjenjang, dan digital signing — dalam satu jejak data yang dapat diaudit.

## Stack

- Laravel 13 · PHP 8.3 · MySQL 8.4
- Blade + Tailwind CSS v4 (Vite)
- PHPUnit · Laravel Pint
- Modular monolith, multi-company (company isolation ketat)

## Module Map

| Grup Menu | Modul | Keterangan |
|---|---|---|
| Dashboard | Executive Dashboard | Kartu per-permission, chart pendapatan/PPN, aging AR/AP |
| Organisasi | Company, Branch, Department, RBAC per membership | `organization.*` |
| Marketing & Tender | Pelanggan (term pembayaran), Tender, Outcome, Konversi Proyek | `tender.*` |
| Project & Bored Pile | Proyek, Zona, Titik Pile (9-status lifecycle), Gantt & Kurva-S, **Field Operations** (drilling record + bore log ternormalisasi, concrete delivery dengan slump/accept-reject, pile testing PIT/PDA/CSL/SLT/DLT dengan gate kelulusan) | `project.*` |
| Supply Chain | Item/UoM/Warehouse/Bin, ledger immutable, stok kritis alert harian | `inventory.*` |
| Procurement | Vendor, PO versi + revision snapshot, three-way matching, GRNI posting | `procurement.*` |
| Engineering & Workshop | BOM, routing work center, WIP reconciliation, QC disposition, equipment hour meter/fuel/MWO | `manufacturing.*`, `equipment.*` |
| Finance & Accounting | COA, jurnal balanced idempotent, mapping configurable, periode fiskal + closing gate, Progress Billing (retensi/uang muka/**PPN keluaran**/faktur PDF terbilang), release retensi, kas-bank + rekonsiliasi, **Pajak & Bukti Potong** (PPN masukan, PPh 23, PPh final 4(2)), project costing EAC, fixed asset depresiasi | `finance.*`, `accounting.post` |
| Governance | Document control versi + hash SHA-256, download isolation | `document.*` |
| Approval & Signing | Approval engine sequential/any/all/quorum + SLA + delegasi; digital signing provider-agnostic terikat version+hash | `approval.*`, `signature.*` |
| Quality, HSE & ISO | Risiko/peluang, NCR/CAPA (independence guard), audit mutu internal, evidence expiry; JSA, izin kerja, incident | `qms.*`, `hse.*` |
| Pengaturan | Default perusahaan (termin, retensi %, PPN %, toleransi overbreak, catatan faktur) | `finance.manage` |
| Administrasi | Notifikasi (in-app bell + email), Audit Trail hash-chain viewer | `audit.view` |

## Alur Bisnis Utama

```
Tender → Estimasi → Won → Award → Signing → Handover → Proyek aktif
  ├─ RFQ/PO → Goods Receipt → Inventory
  ├─ Manufacturing cage → Delivery ke titik
  ├─ Drilling record → Cage installation → Concrete delivery (approve = volume aktual) → Testing (gate completed)
  └─ Progress Billing (PPN keluaran) → Customer Receipt (PPh final dipotong) → Jurnal → Costing → Closing
```

## Installation

```bash
git clone https://github.com/linducip2208/graha.git && cd graha
composer install --no-interaction
cp .env.example .env && php artisan key:generate
# konfigurasi DB MySQL di .env, lalu:
php artisan migrate --seed     # --seed memuat data demo lengkap
npm ci && npm run build
php artisan serve
```

### Akun Demo (password semua: `password`)

| Role | Email | Cakupan |
|---|---|---|
| Super Admin | admin@grahapondasi.test | Semua modul + audit trail |
| Finance Manager | finance@grahapondasi.test | Billing, pajak, jurnal, laporan |
| Project Manager | pm@grahapondasi.test | Proyek, field ops, equipment, HSE |
| Procurement Officer | procurement@grahapondasi.test | Vendor, PO, gudang |
| Direktur Operasi | direktur@grahapondasi.test | Approval center, tender |

## Queue & Scheduler (production)

```
php artisan queue:work --tries=3          # supervisor
php artisan schedule:work                 # atau cron: * * * * * php artisan schedule:run
```

Job terjadwal: backup DB harian 02:15, expiry evidence QMS 01:30, SLA approval per jam, notifikasi stok kritis 07:00, tenggat CAPA/NCR 08:00.

## Storage & Signature Provider

File dokumen disimpan di `storage/app/private` (download lewat route ber-authorization, bukan public disk). Digital signing memakai pola **format-based generic adapter**: nama provider, endpoint, credential diinput user (terenkripsi), webhook HMAC + idempotency + replay protection di `/webhooks/signatures/{provider}`.

## Accounting Mapping

Tidak ada nomor akun yang di-hardcode. Setiap event transaksi (`progress_billing`, `customer_receipt`, `vendor_invoice`, `vendor_payment`, `goods_receipt`, dll.) dipetakan ke akun debit/kredit/tax via UI Accounting Mapping, dengan effective date. Jurnal wajib seimbang, idempotent, dan dikontrol periode fiskal.

## Testing

```bash
composer test        # config:clear + artisan test (SQLite :memory:)
vendor/bin/pint      # code style
npm run build        # frontend build
node scripts/screenshot.cjs   # screenshot marketing (butuh server di :8899)
```

## Deployment

Lihat `DEPLOYMENT.md` + template `deploy/nginx.conf` dan `deploy/supervisor.conf`. Ringkas: PHP 8.3 + ekstensi bcmath/pdo_mysql, MySQL 8.4, `php artisan migrate --force`, storage link private, queue worker + scheduler via supervisor, HTTPS wajib (security headers sudah aktif).

## Backup & Restore

Backup harian otomatis via command `backup:database` (retensi 14 hari) ke storage. Restore manual: import dump terakhir ke database kosong lalu jalankan `migrate --force` (migration additive).

## Security

Company isolation di controller + invariant service; RBAC per membership; CSRF + security headers; upload dokumen privat; audit log append-only hash-chain; rate limit login & webhook; secret signature provider terenkripsi.

## Known Limitations

- Business Process Mapping Level 0 (`docs/PM 04`) belum tersedia → baseline alur bisnis internal (lihat docs).
- Multi-currency baru fondasi kolom; kurs & selisih kurs belum.
- HR/payroll sengaja di luar scope.
- Dark mode: override permukaan umum; chart per-halaman masih default terang saat toggle runtime (refresh menyinkronkan).
