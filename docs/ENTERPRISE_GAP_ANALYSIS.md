# ENTERPRISE GAP ANALYSIS — Graha Pondasi ERP

Status per 2026-08-24. Baseline: main @712896d+. Matriks ringkas
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
| Contract administration | ✅ Ada | ✅ milestone register + insurance register (ADR-062, 2026-08-25) | correspondence link tersisa |
| Project tabs (11) | ✅ Ada | Tab Documents masih registry umum | Backlog G |
| WBS hierarki | ⚠️ Flat | parent_id + budget per baris | Backlog B |
| Budget baseline versi | ❌ Belum | Tabel baseline versioned | Backlog B |
| Cost control EAC | ✅ Ada | CPI/SPI ✅ baru; drill-down PO/GR/jurnal | Sebagian backlog |
| Constraint log | ✅ Baru (ADR-049) | — | — |
| Procurement plan | ✅ Baru (ADR-050) | Auto-status saat PR/PO dibuat dari flow | Backlog C |
| PR→RFQ→PO→GR→Invoice | ✅ Ada | Bid comparison kolom delivery/warranty | Kecil |
| Vendor scorecard | ✅ Ada evaluasi | status approved/suspended/blacklisted | Backlog C |
| Inventory ledger | ✅ Ada | reserved/in-transit, damaged/obsolete state | Backlog C |
| Material traceability lot | ✅ Ada (lot_number) | UI telusur lot end-to-end | Backlog D |
| Reordering | ⚠️ Min stock alert | reorder point/max/lead time + rekomendasi | Backlog C |
| Manufacturing WIP/OEE | ✅ Ada core | ✅ downtime terstruktur (ADR-063, 2026-08-25); formula OEE menyusul | Sebagian |
| Equipment cost/hour | ✅ Ada | — | — |
| Fuel tank↔equipment | ✅ Baru (ADR-044) | — | — |
| Accounting core | ✅ Ada | recurring journal, accrual/prepaid module | Backlog F |
| AR subledger | ✅ Ada aging | ✅ credit note + write-off approval (2026-08-25) | Selesai |
| AP matching 3-way | ✅ Ada | flag mismatch granular di UI | Kecil |
| Cash flow forecast | ✅ Ada (CashFlowForecastService) | — | — |
| Cash flow statement | ✅ Baru (ADR-055, 2026-08-25) | tagging kategori CoA perlu disiplin input | Disiplin input |
| Budget vs actual account | ❌ Belum | butuh budget akun per periode | Backlog F |
| Fixed asset | ✅ Ada | ✅ disposal flow lengkap (ADR-056, 2026-08-25) | Selesai |
| Pajak ID | ✅ PPN/PPh23/4(2) | PPh 21 engine, rekonsiliasi UI | Backlog F |
| QMS NCR/CAPA | ✅ Ada | customer complaint, supplier NCR | Backlog G |
| **ITP** | ✅ Baru (ADR-059, 2026-08-25) | gate ke pile completion (opsional setting) | Backlog G lanjutan |
| **Kalibrasi** | ✅ Baru (ADR-060, 2026-08-25) — register + due status otomatis | reminder scheduler | Kecil |
| HSE | ✅ JSA/PTW/incident | ✅ observasi/PPE/FR-SR KPI (ADR-058, 2026-08-25) | Selesai |
| Document control | ✅ Versi+hash | transmittal, distribution, superseded lifecycle | Backlog G |
| Approval engine | ✅ SLA/quorum/delegasi | escalation reminder otomatis ada; kondisi project-scope | Backlog H |
| Signing internal | ✅ hash-lock | QR verification page, batch sign | Backlog H |
| Access scope per project | ❌ Belum | company_user.data_scope sudah disiapkan (WIP IAM paralel) | Menunggu landung IAM |
| Audit diff viewer | ✅ Baru (ADR-064, 2026-08-25) | — | — |
| API v1 | ✅ subset | contracts, plans, constraints endpoint | Backlog H |

## Keputusan yang diblokir / menunggu owner

1. **BPM-L0-001** — workbook `docs/PM 04` tidak ada di repo: proses Level 0
   tidak boleh ditebak (tetap BLOCKED).
2. **FX unrealized** — hanya realized yang diposting (ADR-040); revaluation
   menanti kebijakan.
3. **IAM parallel WIP** — cache permission tanpa isolasi test memutus suite;
   harus tambah flush invalidator sebelum mendarat.
