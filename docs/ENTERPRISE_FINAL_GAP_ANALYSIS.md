# Enterprise Final Gap Analysis

Audit terhadap `main` commit `bc067e5` pada 26 Agustus 2026. Status tidak menilai nama menu, tetapi control, traceability, safety, operability, dan exception handling yang benar-benar tersedia.

| Area | Status | Existing yang dipertahankan | Gap terverifikasi / tindakan |
|---|---|---|---|
| System Health & Observability | DONE | `SystemHealthService`, scheduler heartbeat, database/cache/queue/mail/storage/runtime/disk/backup checks, privileged queue actions, System Health di Pengaturan | Checks cepat dan tanpa bucket scan. External worker/cron tetap harus dipasang operator deployment. |
| Backup & Restore | PARTIAL | Metadata `BackupRecord`, gzip + SHA-256, private local output, safe `MYSQL_PWD`, retention keep-last-good, UI dan `backup:verify` | Production restore execution sengaja BLOCKED sampai automated preflight/maintenance/pre-restore/typed confirmation selesai; prosedur aman tersedia di disaster recovery guide. |
| Approval Governance | PARTIAL | Workflow/step/request/decision, any/all/quorum, SLA due time, approved delegation, reminder command, self-approval protection | Matrix belum memiliki threshold/project/document/currency yang lengkap; delegation perlu circular/company validation dan OOO integration. |
| Notification Preferences | PARTIAL | Database notifications, approval/operational notification, dispatcher | Belum ada preference per-user/event/channel/frequency dan digest dedupe. |
| Security Administration | PARTIAL | Session DB, Sanctum, roles/permissions, security headers, password hashing | Belum ada security center, revoke session/token UI, dormant detection/policy, password policy, atau MFA. MFA dinilai PARTIAL bila hanya hooks/config pada fase ini. |
| Audit & Compliance | PARTIAL | Append-only model guard, SHA-256 previous-entry chain, actor/company/subject/timestamp, approval IP/user agent | Viewer/export/correlation/diff belum lengkap; belum ada `audit:verify`; perlu redaction dan CSV injection protection. |
| Import/Export Enterprise | MISSING | Geometry CSV import dan Experience import/export bersifat domain-specific | Belum ada reusable staged import center, mapping, error report, idempotent business keys, current-view export. |
| Global Search Depth | PARTIAL | Permission dan company-filtered search untuk project/pile/customer/vendor/PO/RFQ/MR/billing/journal/NCR/document/equipment/contract/tender | Item, vendor invoice, approval/CAPA dan UX grouping/context perlu diperluas; hasil sudah dibatasi dan eager-load pada relasi penting. |
| Reporting & Saved Views | PARTIAL | Executive/finance/operations/manufacturing/aging reports, export routes, forecast services | Belum ada saved filters dan scheduled report delivery; report center belum menyatukan seluruh kategori. |
| Contract / VO / Claims | PARTIAL | Contract changes, milestones, insurance, correspondence, downtime linkage, retention release | `contract_variations`, EOT, formal claim register/evidence builder, dan deterministic LD exposure belum lengkap. Extend contract admin, jangan menggandakan evidence. |
| Finance Closing & Forecast | PARTIAL | Fiscal periods, approval-gated close, bank reconciliation gate, WIP gate, cash forecast 7/30/90, aging, project cost ledger | Closing checklist/readiness/reopen control, 60-day forecast, commitments/tax inputs, profitability drill-down perlu dilengkapi. |
| Procurement Intelligence | PARTIAL | Vendor lifecycle/evaluation, RFQ/quotation, PO, GR/invoice matching, tax/FX, overdue data | Vendor score aggregation, price history/UOM guard, exception attention queue dan mismatch dashboard perlu dipusatkan. |
| Inventory Intelligence | PARTIAL | Stock balances/movements, reorder service, min/max columns, opname, lot trace tests | Slow/dead/fast/excess/project exposure analytics dan controlled transfer approval perlu UI/report terpusat. |
| Equipment Reliability | PARTIAL | Equipment meter, maintenance work order, fuel usage, downtime logs, cost/hour tests | Calendar due/meter windows, spare-part stock issue linkage, breakdown Pareto dan total cost intelligence perlu dilengkapi. |
| QMS / HSE Analytics | PARTIAL | NCR/CAPA, audits, incidents/actions, toolbox meetings, PTW, JSA | CAPA effectiveness review, recurrence analytics, configurable checklist version/snapshot belum lengkap. PTW tetap optional policy. |
| Mobile Field Reliability | PARTIAL | Responsive field UI, direct-upload fallback/idempotent finalize, evidence GPS/captured time, API endpoints | Offline local draft, client UUID submission idempotency, sync state/retry/low-connectivity UX belum menyeluruh. GPS harus tetap berlabel device-reported. |
| Deployment / Setup Wizard | MISSING | `.env.example`, deployment docs, queue/scheduler configuration, storage profile UI | Belum ada `/setup`, initialization marker, CLI reopen, environment/preflight sequence, atau post-install health result. |
| Documentation Completeness | PARTIAL | Documentation Center, 50 artikel, 53 screenshot lokal, docs build/audit/capture | Artikel area enterprise baru dan screenshot aktual belum ada. Disk `docs` lokal sudah benar dan tidak boleh dialihkan ke company object storage. |
| Attention Center | PARTIAL | Domain dashboard alerts dan `ProjectHealthService` | Belum ada `AttentionService` lintas-domain; warning masih tersebar. Harus diagregasi SQL tanpa duplikasi aturan. |
| Demo Dataset v2 | PARTIAL | Deterministic/idempotent demo foundation, finance, QMS/HSE, storage metadata | Scenario enterprise baru perlu diperluas hanya setelah schema/service final; tidak boleh membuat KPI palsu atau menyentuh production. |

## Baseline P0

- Git: `main` sama dengan `origin/main`; commit terbaru `bc067e5 feat: add company storage profiles`.
- Pint: PASS.
- Vite production build: PASS.
- Routes: PASS, 341 routes.
- Documentation audit: PASS, 50 artikel dan 53 screenshot ready.
- Full test suite dijalankan terpisah karena wrapper Composer memiliki timeout 300 detik; hasil dicatat pada quality gate implementasi.

## Keputusan implementasi

1. Gelombang pertama: Health Center + backup metadata/verification karena menjadi dependency observability, setup, attention, dan disaster recovery.
2. Extend model/service existing untuk approval, finance, contract, procurement, inventory, equipment, QMS/HSE; tidak membuat menu paralel.
3. HR tetap OUT OF SCOPE. Payroll tidak menjadi input cash forecast.
4. MFA penuh boleh tetap PARTIAL bila dependency TOTP/QR/recovery code tidak tersedia secara bersih; tidak akan dibuat pseudo-MFA.
5. Restore production tetap BLOCKED dari eksekusi otomatis sampai preflight, backup otomatis, typed confirmation, dan authorization teruji. Verifikasi backup boleh dirilis lebih dahulu tanpa klaim bahwa restore aman.
6. Screenshot aktual memerlukan demo server/browser; file selalu disimpan pada disk lokal `docs`.
