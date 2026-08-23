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

## Log Eksekusi

### Sesi eksekusi gabungan (2026-08-23) — P0 + mayoritas P1/P2 SELESAI
- **P0 lengkap**: nav direstrukturisasi ke 10 workspace (`config/modules.php`);
  app launcher `/apps` (favorit + terakhir dilihat); My Work `/admin/my-work`
  (approval/CAPA/HSE/MR/signature, permission-scoped); favorites & recent
  (tabel `user_favorites`/`user_recent_views` + endpoint preference);
  global search Ctrl+K `/admin/search` (14 tipe entitas, company+permission
  scoped); breadcrumb bar di layout; quick-create dropdown.
- **P1**: dashboard split per profil (Cockpit Eksekutif: MTD/YTD/GP/kontrak/
  win rate; tabel Kesehatan Proyek fisik-vs-rencana+EAC+margin dengan ambang
  configurable `project_health_yellow_percent`/`project_health_red_percent`;
  antrean Procurement); project detail tabs `/admin/projects/{id}` (11 tab);
  tender detail tabs `/admin/tenders/{id}` (5 tab); **Administrasi Kontrak**
  baru (`contract_changes`: VO/addendum/EOT/claim/LD/bond, approval-aware via
  interface `ApprovalSyncable`, tanpa auto-post jurnal); vendor scorecard
  (`vendor_evaluations`); Ikhtisar Keuangan `/admin/finance/overview`;
  timeline NCR di QMS; quick-action mobile di Field Ops.
- **P2**: API v1 `/api/v1/cages|casings|fuel-tanks|tools|equipment`; kanban
  tender (`?view=kanban`) + komponen `x-ui.kanban`; danger-action confirm
  modal (`data-confirm`); empty state informatif di tab/tab baru.
- **Perbaikan bug pre-existing ditemukan saat audit**:
  1. `routes/web.php` memakai `FinanceController` tanpa import (500 di
     `/admin/finance`) — diperbaiki.
  2. `Navigation::filterItems()` melewati cek permission untuk item parent
     yang punya children — kebocoran menu; kini permission dicek sebelum
     branch children.
  3. Route ordering: `/projects/{project}` menutupi `/projects/field-ops`.
- **Test baru** `tests/Feature/Workspace/WorkspaceNavigationTest.php` (7 test):
  launcher permission-aware, direct URL ditolak backend, My Work scoped,
  tab proyek permission-scoped, search isolation lintas perusahaan,
  favorites/recent toggle, contract change approval + scope.
- **Quality gate**: composer test 102 passed / 346 assertions · pint clean ·
  `npm run build` OK · route:list 206 routes review · screenshot desktop 33
  halaman + mobile 5 halaman (iPhone 414×896) di `public/marketing/screens*`.

### Sesi lanjutan backlog (2026-08-23) — sisa P2 + keputusan #3 SELESAI
- **Kanban tambahan**: NCR (`/admin/qms?view=kanban`), Approval status
  (`/admin/approvals?view=kanban`), Project portofolio
  (`/admin/projects?view=kanban`) — semua dibangun dari koleksi yang sudah
  dimuat tanpa kueri ekstra.
- **Amount-limit approval UI (#3)**: kartu Workflow Configuration kini
  menampilkan rentang nominal `min–max currency` per workflow (role mapping
  via steps), nilai tetap configurable dari form — tidak ada hardcode.
- **Saved views/filter**: filter status chip + pencarian kode/nama di
  `/admin/projects` dan `/admin/tenders`; state tersimpan sebagai query URL
  (dapat dibagikan/di-bookmark) dan berlaku juga pada view kanban.
- **Performance anti-N+1**: `ProjectCostingService::summariesFor()` batch
  agregat (actual/committed/forecast dalam 3 kueri) dipakai dashboard project
  health + halaman `/admin/project-costing`; billing posted diagregasi 1
  kueri group-by.
- **OpenAPI**: `/docs/openapi.yaml` ditambah endpoint cages/casings/
  fuel-tanks/tools/equipment beserta permission & parameter masing-masing.
- **Audit completeness scan**: test baru
  `tests/Feature/Workspace/AuditCompletenessTest.php` — memindai event audit
  kritis lintas domain (approval submit+approve, tender outcome, qms risk,
  journal posting) dan memastikan setiap baris audit mencatat aktor.
- **Perbaikan bug pre-existing**:
  1. Komponen `x-ui.kanban`: direktif Blade berdempet `@endif@if(...)` tidak
     terkompilasi → papan kanban tender sebenarnya 500 saat ada kartu dengan
     subtitle; diperbaiki dengan pemisah.
  2. Pola sama ditemukan di tab outcome tender detail
     (`tenders/show.blade.php`, 3 titik) dan saat menambah filter chip proyek
     (`@endforeach@if`) — semua dipisah; regresi dikunci lewat test
     `test_tender_kanban_and_outcome_tab_render_without_directive_errors`.
  3. Stray `>` pada form pelanggan di halaman tender.
- **Quality gate**: composer test 108 passed / 375 assertions · pint clean ·
  route:list tetap 206 routes · build aset OK · screenshot desktop 33 halaman
  + mobile 5 halaman di-capture ulang tanpa error (server :8899).

### Sisa backlog (keputusan/sesi berikutnya)
- FX realized/unrealized → tetap menunggu kebijakan (Decision Required #1).
- BPM-L0-001 tetap Blocked sampai file `PM 04` resmi tersedia (#2).
