# UI V3 Admin Migration — Before Snapshot

Baseline HEAD: `b06aa02` — route baseline: `storage/app/ui-v3-admin-routes-before.txt` (265 route).

## Kondisi sebelum migrasi (audit aktual, bukan klaim)

| Klasifikasi | Halaman |
|---|---|
| **BROKEN_DRAWER** | finance/accounts, finance/journals, finance/periods, finance/mappings, project-costing (`id="{...}"` literal + `data-drawer-open` tak tertutup — bug skrip konversi sebelumnya) |
| **LEGACY (form permanen)** | organization (2 form), organization/roles (form create + matrix + members menyatu), tenders (4 form: customer/competitor/tender/participant), contracts (form perubahan), cash-bank (4 form operasi), fixed-assets (3 form), approvals (workflow+delegation), billing (draft+retention), taxes (rate), signatures (batch sign), inventory opname/tools/material-requests, rfq, operations casings/fuel-tanks, manufacturing (3 form create) |
| **PARTIAL_V3** | qms, hse (KPI + container, tapi semua form tampil bersamaan) |
| **PASS_V3** | documents, projects, inventory utama, procurement index, dashboard, my-work, apps |

## Mojibake terdeteksi
- signatures/index: `Â·`, `âœï¸` (double-encoded ✍️)
- tenders/show: `Â·`, `ðŸ` (🏆), `â†` (←)

## Route
265 route (GET admin pages: lihat baseline file). Tidak ada yang dihapus pada akhir migrasi.
