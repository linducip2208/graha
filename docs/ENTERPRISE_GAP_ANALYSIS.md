# ENTERPRISE GAP ANALYSIS — Graha Pondasi ERP

Status per 2026-08-25. Baseline: main @16de0f0+. Matriks ringkas
Modul → Status → Gap → Rencana. Prinsip: tidak ada fitur palsu;
yang "Blocked" menunggu keputusan/file eksternal.

| Modul | Status | Gap utama | Aksi |
|---|---|---|---|
| Workspace shell (10 grup) | ✅ Ada | — | Pertahankan |
| Dashboard per-role | ✅ Ada | KPI row & attention grouping | Redesign UI bertahap |
| Tender pipeline+intel | ✅ Ada | — | — |
| **Bid/No-Bid scoring** | ✅ Baru (ADR-048) | Bobot default perlu kalibrasi data riil | Review tiap kuartal |
| **Lost reason analytics** | ✅ Baru | Isi primary_reason saat outcome | Disiplin input |
| Tender→Contract→Project | ✅ Ada (convert) | Contract creation eksplisit terpisah | Sudah sesuai desain |
| Contract administration | ✅ Ada | ✅ milestone + insurance + correspondence + API v1 contracts (2026-08-25) | Selesai |
| Project tabs (11) | ✅ Ada | Tab Documents masih registry umum | Backlog G |
| WBS hierarki | ✅ Baru (ADR-055) — parent 4 level + budget per baris | — | Selesai |
| Budget baseline versi | ✅ Baru (BudgetBaselineService) | — | Selesai |
| Cost control EAC | ✅ Ada | CPI/SPI ✅; drill-down PO/GR/jurnal | Sebagian backlog |
| Constraint log | ✅ Baru (ADR-049) | — | — |
| Procurement plan | ✅ Baru (ADR-050) | auto-status saat PR/PO dibuat dari flow | Backlog C |
| PR→RFQ→PO→GR→Invoice | ✅ Ada | ✅ bid comparison kolom garansi (wave 9, 2026-08-25) | Selesai |
| Vendor scorecard | ✅ Ada evaluasi + status approved/suspended/blacklisted | — | Selesai |
| Inventory ledger | ✅ Ada | ✅ state damaged/obsolete/in-transit (wave 9, 2026-08-25); reserved ada | Selesai |
| Material traceability lot | ✅ Ada (lot_number) | ✅ UI telusur lot end-to-end (ADR-056) | Selesai |
| Reordering | ✅ Baru (reorder engine ADR-073/074) | — | Selesai |
| Manufacturing WIP/OEE | ✅ Ada core | ✅ downtime terstruktur (ADR-063); formula OEE menyusul | Sebagian |
| Equipment cost/hour | ✅ Ada | — | — |
| Fuel tank↔equipment | ✅ Baru (ADR-044) | — | — |
| Accounting core | ✅ Ada | ✅ recurring journal (ADR-066) + prepaid amortisasi (wave 8); accrual lain menyusul | Sebagian |
| AR subledger | ✅ Ada aging | ✅ credit note + write-off approval (2026-08-25) | Selesai |
| AP matching 3-way | ✅ Ada | ✅ flag mismatch granular: quantity_flag + short_items + amount_difference (wave 9) | Selesai |
| Cash flow forecast | ✅ Ada (CashFlowForecastService) | — | — |
| Cash flow statement | ✅ Baru (ADR-055, 2026-08-25) | tagging kategori CoA perlu disiplin input | Disiplin input |
| Budget vs actual account | ✅ Baru (ADR-067, 2026-08-25) — per akun per periode fiskal | disiplin input budget | Selesai |
| Fixed asset | ✅ Ada | ✅ disposal flow lengkap (ADR-056, 2026-08-25) | Selesai |
| Pajak ID | ✅ PPN/PPh23/4(2) | ✅ kalkulator PPh 21 estimasi (ADR-070, 2026-08-25) | e-Bupot tetap eksternal; rekonsiliasi UI |
| QMS NCR/CAPA | ✅ Ada | ✅ customer complaint + supplier NCR (ADR-065, 2026-08-25) | Selesai |
| **ITP** | ✅ Baru (ADR-059) + ✅ gate pile completion opsional (ADR-069, 2026-08-25) | — | — |
| **Kalibrasi** | ✅ Baru (ADR-060) — register + due status + ✅ reminder scheduler (2026-08-25) | — | — |
| HSE | ✅ JSA/PTW/incident | ✅ observasi/PPE/FR-SR KPI (ADR-058, 2026-08-25) | Selesai |
| Document control | ✅ Versi+hash | ✅ transmittal + acknowledged + korespondensi kontrak (ADR-068/071, 2026-08-25) | Selesai |
| Approval engine | ✅ SLA/quorum/delegasi | escalation reminder otomatis ada; kondisi project-scope | Backlog H |
| Signing internal | ✅ hash-lock | ✅ QR verification page + batch sign (ADR-074 era) | Selesai |
| Access scope per project | ❌ Belum | company_user.data_scope sudah disiapkan (WIP IAM paralel) | Menunggu landung IAM |
| Audit diff viewer | ✅ Baru (ADR-064, 2026-08-25) | — | — |
| API v1 | ✅ subset | ✅ constraints, plans, contracts endpoint lengkap (wave 9) | Selesai |
| **Ready-to-Drill / Ready-to-Cast** | DONE Baru (ADR-073, 2026-08-26) | snapshot readiness di Control Tower KPI | Selesai |
| **Bottom cleaning gate** | DONE Baru (ADR-073) | opsional per company; default record-only | Selesai |
| **Slurry control** | DONE Baru (ADR-074) | limit configurable, default OFF; keputusan QA | Selesai |
| **Tremie log + embedment flag** | DONE Baru (ADR-074) | flag indikator; tidak auto-reject | Selesai |
| **Truck timeline (sequence/gap/waiting)** | DONE Baru (ADR-074) | risk engine memakai gap yang sama | Selesai |
| **Pour curve theoretical vs actual** | DONE Baru (ADR-075) | tanpa interpolasi; disiplin input interval | Disiplin input |
| **Hole geometry / caliper CSV** | PARTIAL Baru (ADR-075) | import manual; integrasi caliper vendor BLOCKED menunggu format | Sebagian |
| **Survey deviation PASS/WARNING/OUT** | DONE Baru (ADR-075) | disposisi engineering tetap manusia | Selesai |
| **Per-pile costing + rework attribution** | DONE Baru (ADR-076) | beton dari PO tertaut; DO tanpa PO dilaporkan untraced (jujur) | Disiplin input PO |
| **Productivity engine** | DONE Baru (ADR-077) | benchmark antar-periode menyusul | PARTIAL |
| **Rig performance utilization** | DONE Baru (ADR-077) | window 30 hari tetap | Selesai |
| **Delay reason registry terpusat** | DONE Baru (ADR-076) | migrasi nilai reason lama ke registry menyusul | PARTIAL |
| **Lookahead 3/7 hari** | DONE Baru (ADR-077) | rencana manual via planned_date | Selesai |
| **Completion forecast deterministik** | DONE Baru (ADR-077) | confidence berbasis kecukupan data; tanpa AI by design | Selesai |
| **Storage dashboard agregat** | DONE Baru (ADR-078) | tanpa bucket scan | Selesai |
| **Retention archive/pending-delete/delete** | DONE Baru (ADR-078) | kategori historis terproteksi; scheduler otomatis sengaja tidak ada | Selesai |
| **Presigned direct upload + fallback** | DONE Baru (ADR-078) | hanya disk S3-compatible; local = mode server | Selesai |
| **Upload queue idempotent** | DONE Baru (ADR-078) | offline PWA/service-worker belum ada (jujur bukan offline-first) | PARTIAL |
| **Pile cap / foundation group readiness** | BELUM | konsep grup belum dimodelkan di data existing | Backlog |
| **Demo dataset enterprise v2** | DONE Baru (ADR-079) | 64 pile/4 proyek/9 skenario risiko; deterministic+idempotent+production-safe | Selesai |


## Keputusan yang diblokir / menunggu owner

1. **BPM-L0-001** — workbook `docs/PM 04` tidak ada di repo: proses Level 0
   tidak boleh ditebak (tetap BLOCKED).
2. **FX unrealized** — hanya realized yang diposting (ADR-040); revaluation
   menanti kebijakan.
3. **IAM parallel WIP** — cache permission tanpa isolasi test memutus suite;
   harus tambah flush invalidator sebelum mendarat.
