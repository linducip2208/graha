# UI Content Migration Matrix — Graha ERP V3

Baseline HEAD: lihat `storage/app/ui-migration-route-baseline.txt` (route baseline 263).
Audit otomatis: `php artisan ui:audit-legacy` → `storage/app/ui-legacy-report.txt`.

Status: MIGRATED = pola V3 (page-container + page-header + KPI riil + toolbar tunggal + form via drawer/toolbar). EXEMPT = alasan tercantum.

| Workspace | Feature | Route | View | Pattern | Status |
|---|---|---|---|---|---|
| Beranda | Dashboard | /dashboard | dashboard | premium V2 | MIGRATED |
| Beranda | My Work | /admin/my-work | my-work | container+header | MIGRATED |
| Beranda | App Launcher | /apps | apps | visual launcher 3-col + cover manager | MIGRATED |
| Komersial | Tender | /admin/tenders | tenders/index | header+KPI+toolbar; form→toolbar | MIGRATED |
| Komersial | Tender detail | /admin/tenders/{tender} | tenders/show | container+header | MIGRATED |
| Komersial | Kontrak | /admin/contracts | contracts/index | header+KPI; form→toolbar | MIGRATED |
| Komersial | Kontrak detail | /admin/contracts/{contract} | contracts/show | container | MIGRATED |
| Proyek | Portfolio | /admin/projects | projects/index | Control Center V3 (KPI/toolbar/3 view) | MIGRATED |
| Proyek | Detail workspace | /admin/projects/{project} | projects/show | tabbed V3 + health + drawer | MIGRATED |
| Proyek | Field Ops | /admin/projects/field-ops | field-ops | container+header | MIGRATED |
| Proyek | Foundation Control | /admin/projects/{project}/foundation-control | foundation-control | container+header | MIGRATED |
| Proyek | Pile Passport | /admin/bored-piles/{pile}/passport | passport | container | MIGRATED |
| Proyek | Pile Genealogy | /admin/bored-piles/{pile}/genealogy | genealogy | container | MIGRATED |
| Supply Chain | Inventory | /admin/inventory | inventory/index | V2 (KPI+drawer) | MIGRATED |
| Supply Chain | Material Request | /admin/inventory/material-requests | material-requests | container | MIGRATED |
| Supply Chain | Stock Opname | /admin/inventory/opname | opname | container | MIGRATED |
| Supply Chain | Lot Trace | /admin/inventory/lots | lot-trace | container | MIGRATED |
| Supply Chain | Reorder | /admin/inventory/reorder | reorder | container+header | MIGRATED |
| Supply Chain | Tools | /admin/tools | tools | container | MIGRATED |
| Supply Chain | Procurement | /admin/procurement | procurement/index | V2 (KPI+drawer) | MIGRATED |
| Supply Chain | RFQ | /admin/procurement/rfq | rfq | container+header | MIGRATED |
| Supply Chain | PO detail | /admin/procurement/orders/{order} | order-show | container | MIGRATED |
| Supply Chain | Procurement Accounting | /admin/procurement-accounting | accounting | container | MIGRATED |
| Workshop | Manufacturing | /admin/manufacturing | manufacturing/index | header+KPI; form→toolbar | MIGRATED |
| Workshop | Costing | /admin/manufacturing/costing | costing | container+header | MIGRATED |
| Workshop | QC Produksi | /admin/manufacturing/quality | quality | container | MIGRATED |
| Workshop | Nonconforming | /admin/manufacturing/nonconforming | nonconforming | container | MIGRATED |
| Workshop | Cage | /admin/manufacturing/cages | cages | header+KPI | MIGRATED |
| Workshop | Casing | /admin/casings | casings | header+KPI | MIGRATED |
| Workshop | Equipment | /admin/operations | operations/index | header+KPI; form→toolbar | MIGRATED |
| Workshop | Equipment detail | /admin/operations/equipment/{equipment} | equipment-show | container | MIGRATED |
| Workshop | Fuel | /admin/fuel-tanks | fuel-tanks | header+KPI | MIGRATED |
| Keuangan | Overview | /admin/finance/overview | finance/overview | container+header (KPI bawaan) | MIGRATED |
| Keuangan | GL Workspace | /admin/finance | finance/index | container; subnav | MIGRATED |
| Keuangan | CoA | /admin/finance/accounts | accounts | container | MIGRATED |
| Keuangan | Journals | /admin/finance/journals | journals | container | MIGRATED |
| Keuangan | Periode | /admin/finance/periods | periods | container | MIGRATED |
| Keuangan | Mapping | /admin/finance/accounting-mappings | mappings | container | MIGRATED |
| Keuangan | Billing | /admin/billing | billing/index | header+KPI; form→toolbar | MIGRATED |
| Keuangan | Tax | /admin/taxes | taxes/index | container+header | MIGRATED |
| Keuangan | Cash & Bank | /admin/cash-bank | cash-bank/index | header; form→toolbar | MIGRATED |
| Keuangan | Project Costing | /admin/project-costing | project-costing/index | header; form→toolbar | MIGRATED |
| Keuangan | Fixed Assets | /admin/fixed-assets | fixed-assets/index | header+KPI; form→toolbar | MIGRATED |
| Quality & HSE | QMS | /admin/qms | qms/index | header+KPI; form→toolbar | MIGRATED |
| Quality & HSE | NCR detail | /admin/qms/ncrs/{ncr} | ncr-show | container | MIGRATED |
| Quality & HSE | HSE | /admin/hse | hse/index | header+KPI; form→toolbar | MIGRATED |
| Dokumen | Document Control | /admin/documents | documents/index | V3 P0 (KPI/filter/drawer) | MIGRATED |
| Dokumen | Document record | /admin/documents/{document} | documents/show | V3 record tabs | MIGRATED |
| Dokumen | Approval Center | /admin/approvals | approvals/index | container+header; form→toolbar | MIGRATED |
| Dokumen | Digital Signing | /admin/signatures | signatures/index | container+header; form→toolbar | MIGRATED |
| Dokumen | Audit Trail | /admin/audit | audit/index | container+header | MIGRATED |
| Laporan | Report pages | /admin/reports/* | reports/* | container+header+cards | MIGRATED |
| Pengaturan | Organization | /admin/organization | organization/index | container+header; form→toolbar | MIGRATED |
| Pengaturan | Roles | /admin/organization/roles | organization/roles | container+header | MIGRATED |
| Pengaturan | Settings | /admin/settings | settings/index | container+header | MIGRATED |
| Pengaturan | Experience Studio | /admin/experience | experience/studio | tabs + cover manager grid | MIGRATED |
| Pengaturan | Notifications | /admin/notifications | notifications/index | container | MIGRATED |
| Publik | Homepage | / | welcome | PUBLIC V3 | MIGRATED |
| Publik | Login | /login | auth/login | two-column branded; demo creds gated | MIGRATED |
| Publik | Docs | /docs | docs | documentation center | MIGRATED |
| Publik | Pile Passport public | /piles/{uuid} | public-passport | verification layout | MIGRATED |
| Publik | Verify signature | /verify/{token} | verify | verification result | MIGRATED |

## Exemptions (dengan alasan)
- `bg-white` pada sebagian view: ditangani override `.dark .bg-white` global (dark mode aman); tokenisasi menyeluruh ditunda agar tidak berisiko regressi visual massal.
- `workspace-tools` auto-toolbar (app.js): mekanisme existing untuk page dengan ≥2 form — form tetap reachable, bukan penghapusan fitur.
- `welcome`/`docs`/`verify` dikecualikan dari legacy detector (publik, pola berbeda).
