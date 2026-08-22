# Requirements Traceability Matrix

| ID | Kebutuhan | Implementasi | Test | Status |
|---|---|---|---|---|
| FND-ORG-001 | Multi-company/isolation | company_user, ResolveCompany | CompanyIsolationTest | Implemented |
| FND-IAM-001 | RBAC backend | roles, permissions, middleware | OrganizationAuthorizationTest | Implemented foundation |
| FND-NUM-001 | Numbering configurable | NumberSequenceService | NumberSequenceServiceTest | Implemented |
| FND-APR-001 | Approval reusable/idempotent | ApprovalEngine | ApprovalEngineTest | Sequential foundation |
| FND-APR-002 | No self-approval | ApprovalEngine | ApprovalEngineTest | Implemented |
| FND-APR-003 | Quorum/any/all dan SLA | ApprovalEngine | AdvancedApprovalTest | Implemented |
| FND-APR-004 | Delegasi sementara terotorisasi | approval_delegations | AdvancedApprovalTest | Implemented |
| FND-DOC-001 | Registry/version/hash | DocumentVersionService | DocumentVersionServiceTest | Implemented foundation |
| FND-DOC-002 | Download terisolasi company | DocumentController | DocumentDownloadIsolationTest | Implemented |
| FND-AUD-001 | Audit immutable | AuditLog/AuditTrail | approval integration | Partial |
| BPM-L0-001 | Excel Level 0 | source missing | pending | Blocked |
| TND-001 | Tender won/lost | TenderService::recordOutcome | TenderLifecycleTest | Implemented core |
| TND-002 | Win/loss rate aman pembagian nol | TenderService::metrics | TenderLifecycleTest | Implemented |
| TND-003 | Konversi tender menang idempotent | TenderService::convertWonToProject | TenderLifecycleTest | Implemented core |
| EST-001 | BOQ/RAB/RAP versioned dan decimal | EstimatingService | EstimatingAndAwardTest | Implemented core |
| CON-001 | Activation gate kontrak | ProjectAwardService | EstimatingAndAwardTest | Implemented core |
| CON-002 | Checklist project handover | ProjectHandover/items | EstimatingAndAwardTest | Implemented core |
| PRJ-001 | Project zone/WBS/cost code | project_zones/project_wbs/project_cost_codes | migration suite | Implemented core |
| BP-001 | Controlled bored pile lifecycle | BoredPileService | BoredPileWorkflowTest | Implemented |
| BP-002 | Concrete theoretical/actual/overbreak | BoredPileService::recordConcrete | BoredPileWorkflowTest | Implemented |
| PRJ-002 | Project closing gate | ProjectClosingService | BoredPileWorkflowTest | Implemented core |
| INV-001 | Immutable stock ledger | StockMovement/InventoryService | InventoryLedgerTest | Implemented core |
| INV-002 | Negative stock prevention | InventoryService | InventoryLedgerTest | Implemented |
| INV-003 | Idempotent receipt/issue/transfer | InventoryService | InventoryLedgerTest | Implemented |
| PRC-001 | Budget check purchase request | PurchaseRequestService | PurchaseRequestBudgetTest | Implemented core |
| MFG-001 | BOM-production-stock traceability | ManufacturingService | ManufacturingTraceabilityTest | Implemented core |
| EQP-001 | Hour meter monotonic | EquipmentService | EquipmentFuelTest | Implemented |
| FUEL-001 | Liter/hour dan anomaly threshold | EquipmentService | EquipmentFuelTest | Implemented core |
| ACC-001 | Double-entry balanced journal | AccountingService | DoubleEntryTest | Implemented |
| ACC-002 | Idempotent posting dan immutable entry | AccountingService/JournalEntry | DoubleEntryTest | Implemented |
| ACC-003 | Fiscal period lock | AccountingService | DoubleEntryTest | Implemented |
| COST-001 | Project cost ledger dari debit proyek | ProjectCostLedger | DoubleEntryTest foundation | Implemented core |
| QMS-001 | Standard/edition/amendment configurable | qms_standards/qms_clauses | QmsWorkflowTest | Implemented core |
| QMS-002 | Risk score 1-5 | QmsService::createRisk | QmsWorkflowTest | Implemented |
| QMS-003 | NCR/CAPA independent verification | QmsService::verifyCapa | QmsWorkflowTest | Implemented |
| AUD-001 | Auditor independence | QmsService::scheduleAudit | QmsWorkflowTest | Implemented |
| QMS-004 | Evidence expiry status | QmsService::refreshEvidenceStatus | QmsWorkflowTest | Implemented |
| RPT-001 | Tiga laporan utama dengan filter tanggal | ReportController/reports.index | ReportAuthorizationTest | Implemented core |
| RPT-002 | Export terpisah berdasarkan permission | ReportController::export | ReportAuthorizationTest | Implemented CSV |
| AUT-001 | Monitor SLA approval terjadwal | routes/console.php | schedule:list verification | Implemented core |
| AUT-002 | Evidence expiry otomatis | routes/console.php/QmsService | QmsWorkflowTest | Implemented |
| DASH-001 | Widget dan quick action berbasis backend permission | dashboard route/view | DashboardPermissionTest | Implemented core |
| MFG-002 | UI BOM dan production completion ke inventory | OperationsController/operations.index | ManufacturingTraceabilityTest | Implemented core |
| EQP-002 | UI equipment, meter, fuel dan maintenance | OperationsController/operations.index | EquipmentFuelTest | Implemented core |
| PRC-002 | PO version snapshot dan approval invalidation | PurchaseOrderService::revise | PurchaseOrderMatchingTest | Implemented core |
| PRC-003 | Goods receipt idempotent dan capped terhadap PO | PurchaseOrderService::receive | PurchaseOrderMatchingTest | Implemented |
| PRC-004 | Three-way match kuantitas dan nilai | PurchaseOrderService::match | PurchaseOrderMatchingTest | Implemented core |
| PRC-005 | PO activation gate wajib approval selesai | PurchaseOrderService::activateApproved | PurchaseOrderMatchingTest | Implemented |
| APR-006 | Approval inbox/configuration/delegation UI | ApprovalController/approvals.index | AdvancedApprovalTest | Implemented core |
| ACC-004 | GR Inventory/GRNI dan invoice GRNI/AP dari mapping | ProcurementAccountingService | ProcurementPostingTest | Implemented core |
| SIG-001 | Internal signature terikat version/hash | DocumentSignatureService::signInternal | DocumentSignatureTest | Implemented |
| SIG-002 | Provider signature generic dan secret encrypted | SignatureProvider | DocumentSignatureTest | Implemented core |
| SIG-003 | Webhook HMAC, idempotency dan replay limit | DocumentSignatureService::handleWebhook | DocumentSignatureTest | Implemented |
| BILL-001 | Progress billing retention/advance/net dengan decimal | ProgressBillingService::create | ProgressBillingTest | Implemented core |
| BILL-002 | Billing activation gate dan contract cap | ProgressBillingService | ProgressBillingTest | Implemented |
| BILL-003 | AR/retention/advance/revenue balanced posting | ProgressBillingService::post | ProgressBillingTest | Implemented core |
| HSE-001 | JSA approval gate dan permit validity | HseService | HseWorkflowTest | Implemented core |
| HSE-002 | Incident action independent verification/close gate | HseService | HseWorkflowTest | Implemented core |
| QMS-005 | Management review automatic evidence snapshot | ManagementReviewService | HseWorkflowTest | Implemented core |
| SEC-001 | Security response headers | SecurityHeaders | SecurityHeadersTest | Implemented |
| OPS-001 | Scheduled private MySQL backup dan retention | BackupDatabase/routes console | deployment verification | Implemented core |
| CASH-001 | Penerimaan AR dan pembayaran AP capped serta idempotent | CashBankService | CashBankClosingTest | Implemented core |
| BANK-001 | Statement bank immutable dan rekonsiliasi terkontrol | CashBankService::reconcile | CashBankClosingTest | Implemented core |
| CLOSE-001 | Period closing wajib approval dan statement bersih | FiscalPeriodClosingService | CashBankClosingTest | Implemented core |
| BILL-004 | Release retensi capped, approved, idempotent dan balanced | RetentionReleaseService | RetentionReleaseTest | Implemented core |
| COST-001 | Actual, committed, CTC, EAC dan variance per proyek | ProjectCostingService | ProjectCostingTest | Implemented core |
| ASSET-001 | Fixed asset straight-line depreciation period-controlled | FixedAssetService | FixedAssetTest | Implemented core |
| MFG-004 | Material issue otomatis Raw Material ke Manufacturing WIP | ManufacturingService | ManufacturingTraceabilityTest | Implemented |
| MFG-005 | Production completion otomatis WIP ke Finished Goods | ManufacturingService | ManufacturingTraceabilityTest | Implemented |
| MFG-006 | QC release gate sebelum output menjadi finished goods | ProductionInspection/ManufacturingService | ManufacturingTraceabilityTest | Implemented |
| MFG-007 | Disposition output ditolak sebagai rework atau scrap dengan jurnal configurable | ProductionDisposition/ManufacturingService | ManufacturingTraceabilityTest | Implemented core |
| MFG-008 | Work center, routing, jam aktual, labor/overhead absorption dan variance | WorkCenter/RoutingOperation/ProductionOperationLog | ManufacturingTraceabilityTest | Implemented core |
| MFG-009 | Rekonsiliasi material/labor/overhead ke finished goods, scrap, dan residual WIP | ManufacturingWipService/reports.manufacturing | ManufacturingTraceabilityTest | Implemented core |
| MFG-010 | Material issue cap berdasarkan BOM/output quantity/scrap allowance | ManufacturingService::issueMaterial | ManufacturingTraceabilityTest | Implemented |
| ACC-005 | Period closing gate untuk anomali residual WIP manufaktur | FiscalPeriodClosingService | ManufacturingTraceabilityTest/CashBankClosingTest | Implemented core |
| OPS-002 | Automated production configuration readiness gate | ProductionReadinessCheck/GitHub Actions | command execution/CI | Implemented core |
| ACC-006 | Trial balance, laba rugi, dan neraca dari posted journals | FinancialStatementService/reports.financial-statements | FinancialStatementTest | Implemented core |
| ACC-007 | Aging piutang/utang dengan paid offset dan bucket cut-off | ReceivablePayableAgingService/reports.aging | ReceivablePayableAgingTest | Implemented core |
| BP-FO-001 | Master pile diperluas (grid, lat/long, elevasi, toe/cut-off, grade, rig, PIC) | migration 230000 + BoredPile casts | FieldOperationsTest | Implemented |
| BP-FO-002 | Drilling record + bore log ternormalisasi + verifikasi independen | BoredPileDrilling(+Layer), FieldOpsService | FieldOperationsTest | Tested |
| BP-FO-003 | Concrete direct delivery (slump, accept/reject, approve) | ConcreteDelivery, FieldOpsService | FieldOperationsTest | Tested |
| BP-FO-004 | Pile testing + gate completed | PileTest, completionGate di BoredPileService | FieldOperationsTest | Tested |
| BP-FO-005 | UI field ops mobile-friendly | projects/field-ops.blade.php + nav | smoke test | Implemented Core |
| SET-002 | Company defaults editable + fallback chain | CompanySetting, SettingsController@save | CompanySettingsTest | Tested |
| TI-COMP-001 | Competitor master per company | Competitor model + form di tenders.index | TenderIntelligenceTest | Tested |
| TI-PART-001 | Peserta tender rank/bid/winner eksklusif | TenderParticipant + TenderIntelligenceService | TenderIntelligenceTest | Tested |
| TI-STAT-001 | Win rate, lost opportunity, avg vs HPS, top competitor | stats() formula spec-compliant | TenderIntelligenceTest | Tested |
| ADR-031 | Winner flag peserta harus tunggal per tender | update reset winner lain dalam transaksi | TenderIntelligenceTest | Implemented |
| INV-MR-001 | Material request dengan approval pemisah | MaterialRequestService | MaterialRequestTest | Tested |
| ACC-MAT-001 | Jurnal issue material berdimensi proyek | event mapping material_issue | MaterialRequestTest | Tested |
