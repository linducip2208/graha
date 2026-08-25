# UI V3 Admin Matrix — Status per Route/View

Snapshot route: `storage/app/ui-v3-admin-routes-before.txt` (265) vs after (265 + 0 removed).
Audit otomatis: `php artisan ui:audit-legacy` → `storage/app/ui-legacy-report.txt` (68 view dipindai).

| Route | View | Status | Header | KPI | Toolbar | Drawer Create | Record Workspace | Catatan |
|---|---|---|---|---|---|---|---|---|
| /admin/documents | documents/index | PASS_V3 | ✓ | ✓ | ✓ | ✓ | ✓ (show tabs) | golden reference |
| /admin/documents/{id} | documents/show | PASS_V3 | ✓ | — | tabs | — | ✓ | overview/versions/approval/activity |
| /admin/projects | projects/index | PASS_V3 | ✓ | ✓ | ✓ | — (create via tender convert) | ✓ | golden reference |
| /admin/projects/{project} | projects/show | PASS_V3 | ✓ | ✓ | tabs | ✓ (zone/pile) | ✓ 12 tab | |
| /admin/inventory | inventory/index | PASS_V3 | ✓ | ✓ | ✓ | ✓ (setup/movement) | — | golden reference |
| /admin/procurement | procurement/index | PASS_V3 | ✓ | ✓ | ✓ | ✓ (vendor/PO) | PO card actions + order-show | vendor eval form → drawer |
| /admin/procurement/rfq | rfq | PASS_V3 | ✓ | — | ✓ | ✓ (RFQ) | comparison table | |
| /admin/procurement/orders/{order} | order-show | PASS_V3 | ✓ | — | — | — | ✓ | |
| /admin/procurement-accounting | accounting | PASS_V3 | ✓ | — | — | — | — | container V2 |
| /admin/manufacturing | manufacturing/index | PASS_V3 | ✓ | ✓ | ✓ | ✓ (BOM/Order/Mapping) | record actions inline | |
| /admin/manufacturing/costing | costing | PASS_V3 | ✓ | — | — | — | — | |
| /admin/manufacturing/quality | quality | PASS_V3 | ✓ | — | — | — | — | |
| /admin/manufacturing/nonconforming | nonconforming | PASS_V3 | ✓ | — | — | — | — | |
| /admin/manufacturing/cages | cages | PASS_V3 | ✓ | ✓ | — | — | — | |
| /admin/casings | casings | PASS_V3 | ✓ | ✓ | — | ✓ (register) | — | |
| /admin/operations | operations/index | PASS_V3 | ✓ | ✓ | ✓ | ✓ (BOM/PO) | equipment-show | |
| /admin/operations/equipment/{id} | equipment-show | PASS_V3 | ✓ | — | — | — | ✓ | |
| /admin/fuel-tanks | fuel-tanks | PASS_V3 | ✓ | ✓ | — | ✓ (tank) | — | |
| /admin/finance/overview | finance/overview | PASS_V3 | ✓ | ✓ | — | — | — | |
| /admin/finance | finance/index | PASS_V3 | ✓ | — | subnav | — | — | GL hub |
| /admin/finance/accounts | accounts | PASS_V3 | ✓ | ✓ | ✓ (search/type/status) | ✓ (akun) | — | drawer bug FIXED |
| /admin/finance/journals | journals | PASS_V3 | ✓ | — | — | ✓ (jurnal) | — | drawer bug FIXED |
| /admin/finance/periods | periods | PASS_V3 | ✓ | — | — | ✓ (periode) | — | drawer bug FIXED |
| /admin/finance/accounting-mappings | mappings | PASS_V3 | ✓ | — | — | ✓ (mapping) | — | drawer bug FIXED |
| /admin/billing | billing/index | PASS_V3 | ✓ | ✓ | ✓ | ✓ (billing/retention) | record actions | |
| /admin/taxes | taxes/index | PASS_V3 | ✓ | — | year selector | ✓ (rate) | — | |
| /admin/cash-bank | cash-bank/index | PASS_V3 | ✓ | — | ✓ | ✓ (account/receipt/payment/statement) | reconciliation | 4 form → drawer |
| /admin/project-costing | project-costing/index | PASS_V3 | ✓ | ✓ | — | ✓ (forecast) | — | drawer bug FIXED |
| /admin/fixed-assets | fixed-assets/index | PASS_V3 | ✓ | ✓ | — | ✓ (kategori/aset/mapping) | depreciate action | 3 form → drawer |
| /admin/qms | qms/index | PASS_V3 | ✓ | ✓ | tabs (5) | ✓ (risk/ncr/audit/objective/survey) | NCR cards + timeline | |
| /admin/qms/ncrs/{ncr} | ncr-show | PASS_V3 | ✓ | — | — | — | ✓ | |
| /admin/hse | hse/index | PASS_V3 | ✓ | ✓ | tabs (3) | ✓ (jsa/incident/review) | record actions | |
| /admin/approvals | approvals/index | PASS_V3 | ✓ | — | — | ✓ (workflow/delegasi) | record actions | |
| /admin/signatures | signatures/index | PASS_V3 | ✓ | — | — | ✓ (batch sign) | record actions | mojibake FIXED |
| /admin/audit | audit/index | PASS_V3 | ✓ | — | ✓ filter | — | — | |
| /admin/reports/* | reports/* | PASS_V3 | ✓ | ✓ cards | ✓ date | export | — | header custom intensional |
| /admin/organization | organization/index | PASS_V3 | ✓ | ✓ | — | ✓ (cabang/departemen) | — | Organization Center |
| /admin/organization/roles | roles | PASS_V3 | ✓ | — | tabs (3) | ✓ (role/member) | ✓ left-list + tabs | permission search + pilih semua |
| /admin/settings | settings/index | PASS_V3 | ✓ | — | — | — | — | sections |
| /admin/experience | experience/studio | PASS_V3 | ✓ | — | fieldsets | ✓ (covers/hero) | versions table | |
| /admin/notifications | notifications/index | PASS_V3 | ✓ | — | — | — | — | |
| /admin/my-work | my-work | PASS_V3 | ✓ | ✓ | — | — | — | action center |
| /admin/tenders | tenders/index | PASS_V3 | ✓ | ✓ | ✓ | ✓ (tender/pelanggan/kompetitor) | detail + outcome | |
| /admin/tenders/{tender} | tenders/show | PASS_V3 | ✓ | — | — | — | ✓ tabs | mojibake FIXED |
| /admin/contracts | contracts/index | PASS_V3 | ✓ | ✓ | — | ✓ (perubahan) | — | |
| /admin/contracts/{contract} | contracts/show | PASS_V3 | ✓ | — | — | — | ✓ | |
| /admin/projects/field-ops | field-ops | PASS_V3 | ✓ | — | — | — | record actions | |
| /admin/projects/{project}/foundation-control | foundation-control | PASS_V3 | ✓ | ✓ | — | — | ✓ | |
| /admin/bored-piles/{pile}/passport | passport | PASS_V3 | ✓ | — | — | — | ✓ | |
| /admin/bored-piles/{pile}/genealogy | genealogy | PASS_V3 | ✓ | — | — | — | ✓ | |
| /admin/inventory/opname | opname | PASS_V3 | ✓ | — | — | ✓ (opname) | approve action | |
| /admin/inventory/lots | lot-trace | PASS_V3 | ✓ | — | ✓ | — | — | |
| /admin/inventory/reorder | reorder | PASS_V3 | ✓ | — | — | — | — | |
| /admin/inventory/material-requests | material-requests | PASS_V3 | ✓ | — | — | ✓ (MR) | record actions | |
| /admin/tools | tools | PASS_V3 | ✓ | — | — | ✓ (register) | checkout actions | |
