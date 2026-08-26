# Production Readiness Matrix

Status: `VERIFIED` berarti dibuktikan pada code/local automated gate; `PARTIAL` berarti kontrol ada tetapi cakupan belum lengkap; `BLOCKED` membutuhkan staging/operator/business sign-off. Bukti lokal bukan bukti target production.

| Gate | Status | Evidence / remaining condition |
|---|---|---|
| PR-01 Zero critical blocker | BLOCKED | Open release blockers tercatat di `KNOWN_ISSUES.md`. |
| PR-02 Database safety | PARTIAL | `migrate --pretend` dan migration MySQL lokal sukses setelah nama index diperpendek; no float/double migration ditemukan; realistic-data rehearsal belum dilakukan. |
| PR-03 Transaction integrity | VERIFIED | 134 transaction usages; accounting, inventory, approval, document, pile, procurement flows memiliki service transaction tests. |
| PR-04 Idempotency | PARTIAL | Unique company idempotency keys dan retry tests luas; scheduled digest/import confirmation belum tersedia penuh. |
| PR-05 Accounting integrity | VERIFIED | Balanced journal guard, immutable posted behavior, correction flows, period lock tests, dan read-only `accounting:verify` PASS lokal. |
| PR-06 Inventory integrity | VERIFIED | Immutable movement ledger, negative policy, lot trace, dan `inventory:verify` PASS lokal. |
| PR-07 Foundation integrity | VERIFIED | State/gate/isolation tests dan `foundation:verify` PASS lokal. |
| PR-08 Authorization personas | PARTIAL | Automated permission tests luas; full persona UAT target belum ditandatangani. |
| PR-09 Company isolation | PARTIAL | Storage/document/foundation/accounting tests PASS; complete route-by-route IDOR UAT remains required. |
| PR-10 Project data scope | PARTIAL | Company isolation exists; selected-project scope coverage belum dibuktikan untuk seluruh module. |
| PR-11 File security | VERIFIED | Private authorized routes, company checks, locator fallback, invalid/expired access tests exist. |
| PR-12 Upload security | PARTIAL | Central byte/MIME/size validation exists; malformed-PDF deep parsing is not guaranteed. |
| PR-13 Storage failure | VERIFIED | Generic S3 failures, presign fallback, temporary URL fallback, and finalize consistency tested. |
| PR-14 Backup validation | PARTIAL | Local private gzip/SHA-256 create and verify PASS; target scheduled backup/failure alert evidence remains required. |
| PR-15 DR rehearsal | BLOCKED | `DR_REHEARSAL.md` is NOT RUN. |
| PR-16 Queue production | BLOCKED | Supervisor template exists; target worker/restart/log evidence absent. |
| PR-17 Scheduler production | PARTIAL | Scheduler event and local heartbeat PASS; target cron and ongoing heartbeat evidence remain required. |
| PR-18 Logging | PARTIAL | Debug gate and sanitization controls exist; target rotation/config needs operator verification. |
| PR-19 Error handling | PARTIAL | Controlled errors exist in critical services; complete UX/manual fault injection is pending. |
| PR-20 Monitoring | PARTIAL | System Health implemented; target mail/backup/scheduler/object profile evidence absent. |
| PR-21 Performance baseline | BLOCKED | Realistic-volume benchmark NOT RUN. |
| PR-22 Pagination | PARTIAL | Operational pages use bounded queries/pagination broadly; manual query review remains for every list. |
| PR-23 Index audit | PARTIAL | Common FK/status/idempotency indexes exist; production slow-query evidence is absent. |
| PR-24 Concurrency | PARTIAL | Locking and unique keys cover major flows; multi-process staging race test is pending. |
| PR-25 Money/rounding | PARTIAL | Money columns use decimal and BC math in posting; a single documented rounding-policy service is not universal. |
| PR-26 Timezone | PARTIAL | Consistent timestamps and Laravel timezone exist; multi-timezone business UAT pending. |
| PR-27 Configuration validation | VERIFIED | `production:check` validates core config and operational markers. |
| PR-28 Production check | VERIFIED | Read-only PASS/WARNING/FAIL command exits non-zero on FAIL and sanitizes probe errors. |
| PR-29 Installation checklist | PARTIAL | Go-live checklist exists; setup-wizard integration not verified. |
| PR-30 HTTPS/cookies | PARTIAL | Secure/encrypted cookie gate exists; target proxy/HSTS/HTTPS verification pending. |
| PR-31 Rate limiting | PARTIAL | Auth/API limiters exist; endpoint-by-endpoint target tuning pending. |
| PR-32 Login security | PARTIAL | Session regeneration/logout/throttle and safe production bootstrap added; optional MFA not complete. |
| PR-33 Business auditability | PARTIAL | Audit trail/hash chain and high-risk events exist; full action coverage audit remains. |
| PR-34 Print/PDF validation | BLOCKED | Real branded output visual QA on target data NOT RUN. |
| PR-35 Empty state | PARTIAL | Automated page tests cover many empty states; full manual page matrix pending. |
| PR-36 Validation UX | PARTIAL | Laravel validation preserves input; full form-level browser review pending. |
| PR-37 Destructive actions | PARTIAL | Permission/confirmation/audit applied to critical actions; full UI review pending. |
| PR-38 Status transition | PARTIAL | Central transitions exist for key modules; all status endpoints need final audit. |
| PR-39 Demo vs production | VERIFIED | Production demo reset denied; automatic demo seeding denied; production baseline requires explicit strong admin credential. |
| PR-40 White-label safety | VERIFIED | Experience settings affect presentation and retain company/storage authorization tests. |
| PR-41 Browser QA | BLOCKED | Chrome/Edge/mobile critical workflow record absent. |
| PR-42 Responsive QA | BLOCKED | Assets/screenshots exist; required 375/768/1024/1440 sign-off absent. |
| PR-43 Accessibility | PARTIAL | Semantic components/focus styles exist; keyboard/modal/contrast audit pending. |
| PR-44 UAT scenarios | VERIFIED | Eight scenarios documented in `UAT_SCENARIOS.md`. |
| PR-45 UAT pass | BLOCKED | All scenarios currently NOT RUN. |
| PR-46 Data migration | PARTIAL | Import patterns exist, but real-data reconciliation evidence absent. |
| PR-47 Go-live reconciliation | BLOCKED | Requires company Finance/Accounting sign-off. |
| PR-48 User-role UAT | BLOCKED | Requires real persona staging UAT. |
| PR-49 Operation manual | PARTIAL | Documentation Center has 52 articles; final operator coverage review pending. |
| PR-50 Admin runbook | VERIFIED | `ADMIN_RUNBOOK.md` created. |
| PR-51 Go-live runbook | VERIFIED | `GO_LIVE_CHECKLIST.md` created. |
| PR-52 Rollback plan | VERIFIED | Rollback decision process documented; destructive schema rollback prohibited without rehearsal. |
| PR-53 Release version | BLOCKED | `v1.0.0` intentionally not created before acceptance. |
| PR-54 Known limitations | VERIFIED | `KNOWN_ISSUES.md` explicitly records blockers and limitations. |
| PR-55 Final decision | VERIFIED | `PRODUCTION_READINESS_REPORT.md` verdict is NOT READY. |
