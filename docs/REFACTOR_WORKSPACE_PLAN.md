# REFACTOR WORKSPACE PLAN — Graha Pondasi ERP

> Dokumen ini mengunci spesifikasi refactor total (Odoo-style workspace UX)
> hasil command Codex. Eksekusi dilakukan bertahap di sesi berikutnya.
> Baseline kode: `main` @ commit setelah `4dfbcd6`. Semua fitur existing
> WAJIB dipertahankan; ini refactor navigasi/presentasi/integrasi, bukan rewrite.

## Prinsip Non-Negotiable
- Jangan rewrite dari nol; jangan hapus modul berjalan.
- Pertahankan invariant: company isolation, service layer, ApprovalEngine,
  InventoryService, BoredPileService, FieldOpsService, ManufacturingService,
  balanced journal + idempotency, period lock, immutable ledger/audit,
  accounting mapping configurable.
- Setiap perubahan permission-aware di BACKEND, bukan hanya menyembunyikan UI.
- Semua migration additive & backward compatible.

## Pola UX Baru
`Workspace → Record → Tabs → Actions`
Navigasi utama MAKSIMAL 10 workspace:
Beranda · Komersial · Proyek · Supply Chain · Workshop & Equipment ·
Keuangan · Quality & HSE · Dokumen & Approval · Laporan · Pengaturan

## Mapping Existing → Workspace Target

| Workspace | Isi (route existing yang direuse) |
|---|---|
| Beranda | /dashboard (role-based), /admin/my-work (BARU), favorites/recent (BARU), quick create (BARU) |
| Komersial | /admin/tenders (+detail tab: estimasi/peserta/kompetitor/outcome/lessons), pelanggan, kontrak admin (BARU: VO/addendum/EOT/claim/bond) |
| Proyek | /admin/projects (portfolio+kanban), project detail TABS (overview/planning/bored pile/field ops/materials/procurement/cost/billing/documents/quality/HSE/activity), gantt+s-curve, field-ops mobile-first |
| Supply Chain | /admin/inventory (+tabs stok/opname/MR/tools), /admin/procurement (+tabs PR?/RFQ/PO/receipt/invoice/vendor eval) |
| Workshop & Equipment | /admin/manufacturing(+costing/quality/nonconforming), /admin/manufacturing/cages, /admin/casings, /admin/operations, /admin/fuel-tanks |
| Keuangan | Overview BARU; Receivables(billing+receipt+retention); Payables(invoice+payment); Cash&Bank; Accounting(COA/jurnal/periode/mapping/statements); Tax(/admin/taxes); Assets |
| Quality & HSE | /admin/qms (+sasaran mutu, kepuasan pelanggan, timeline NCR), /admin/hse |
| Dokumen & Approval | /admin/documents (+linking lintas modul), /admin/approvals (center: my/submitted/overdue/completed/delegation/config), /admin/signatures (internal-only), /admin/audit |
| Laporan | reports/executive,finance,operations,manufacturing,aging,financial-statements (+quality,hse jika data cukup) |
| Pengaturan | organization(+roles UI), settings hub, numbering, tax rates |

Fitur lintas workspace: global search Ctrl+K, breadcrumb konsisten,
notification center deep-link (existing bell), activity timeline dari
audit_logs existing (jangan duplikasi).

## Role Template Default (seed baru, permission granular tetap)
Super Admin · Direktur · Management Read Only · Finance Manager · Accountant ·
Project Manager · Site Manager · Field Supervisor · Engineering ·
Procurement Manager · Purchasing · Warehouse · Workshop Manager ·
Equipment Officer · QC · QMS Manager · HSE Officer · Document Controller ·
Internal Auditor

Access scope (jika memungkinkan): role + company + branch + project scope +
amount limit via workflow config (JANGAN hardcode).

## Dashboard Per Role (reuse widget/charts existing, tambah yang kurang)
- Executive: revenue MTD/YTD, GP, cash, AR/AP, contract value, margin,
  tender pipeline (dari TenderIntelligenceService), project health table
  (physical vs planned %, EAC, schedule variance, health hijau/kuning/merah
  berbasis threshold configurable), bored pile funnel
  Planned→Drilling→Cage→Concrete→Testing→Completed, attention-required gabungan
- PM / Procurement / Finance / Warehouse / QMS / HSE / Field Supervisor:
  sesuai daftar lengkap di master command §7 — sumber data existing saja.

## Fitur Baru yang Benar-Benar Belum Ada (build order P0→P2)
P0: app launcher page (/apps), global search palette (Ctrl+K, company+permission
scoped: Project/Tender/Customer/Vendor/PO/RFQ/MR/Billing/Journal/Pile/NCR/
CAPA/Document/Equipment), My Work page, breadcrumb component, favorites &
recent tables+UI, navigation restructure ke 10 workspace, dashboard role split.
P1: project detail tabs (satu workspace), tender detail tabs, project health
& profitability cockpit (contract/budget/committed/EAC/variance/margin),
planning baseline-vs-actual + look-ahead 2/4 minggu, contract administration
(VO/addendum/EOT/claim/LD/bond — approval-aware, TANPA auto-post jurnal tanpa
rule), mobile-first Field Ops (tombol besar per aksi existing), evidence
morph untuk cage/casing/concrete/test/tools/GR/NCR/CAPA/incident, procurement
workspace tabs + vendor scorecard mulai rekam, finance/qms/hse workspace tabs.
P2: API v1 tambahan (cages/casings/fuel-tanks/tools) + OpenAPI update, FX
realized/unrealized (Decision Required dulu), audit completeness scan,
performance (N+1 dashboard/portfolio/My Work), saved views/filter/group-by,
kanban (tender/project/NCR/approval saja), danger-action modal konsisten,
table standard (sticky+pagination+row-click+export perm-gated), empty states
informatif semua modul.

## Decision Required (jangan spekulatif)
1. Selisih kurs realized vs unrealized → FxService::realizedDifference()
   sudah teruji; wiring ke cash-bank menunggu kebijakan.
2. BPM-L0-001 tetap Blocked sampai file `PM 04` resmi tersedia.
3. Amount-limit approval (Rp500jt dsb.) → nilai via workflow config.

## Test Wajib Saat Refactor
Navigation role-aware (menu benar per role, direct URL ditolak backend) ·
Dashboard scoped · Search isolation+permission · My Work assignments ·
Evidence authz+private download · Document linking cross-module authz ·
Contract admin approval+scope · Field UX tetap melewati service gates.

## Quality Gate Akhir
composer test · vendor/bin/pint · npm run build · route:list review ·
schedule:list · migration additive only · screenshot desktop+mobile
dashboard/menu/workspace untuk laporan.

## Urutan Eksekusi Saran (per sesi)
Sesi 1: P0.1–P0.5 (audit final, launcher, nav restructure, My Work,
favorites/recent) + test navigation.
Sesi 2: Global search + breadcrumb + quick create + dashboard role split.
Sesi 3: Project & Tender workspace (tabs) + health/profitability.
Sesi 4: Mobile field ops + evidence expansion + document linking.
Sesi 5: Contract administration + procurement/finance/qms-hse workspace tabs.
Sesi 6: API missing + performance + saved views/kanban + polish + docs final.
