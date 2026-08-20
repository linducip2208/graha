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
