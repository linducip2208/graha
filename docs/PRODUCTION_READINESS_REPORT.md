# Production Readiness Report

## 1. Current commit

Baseline audited from `164b6dd`; report must be refreshed to the final commit before release tagging.

## 2. Test results

Final automated suite: 352 tests / 1,699 assertions PASS. Pint, Vite production build, Blade view cache, 348-route discovery, docs build, and docs audit also PASS.

Local gate run on 27 August 2026:

- `inventory:verify`: PASS, no anomaly detected.
- `foundation:verify`: PASS, no anomaly detected.
- `migrate --pretend`: PASS; three additive migrations are pending on the local database.
- `production:check`: FAIL with seven blockers. The local environment is not a production candidate.
- Production-gate feature tests: 3 tests / 7 assertions PASS.

## 3-9. Integrity summary

- Security: company and platform permission separation exists; final persona/IDOR UAT remains required.
- Database: migrations are predominantly additive with FK/unique/decimal controls; realistic existing-data migration rehearsal remains external.
- Storage: private authorized serving, historical locator, S3-compatible runtime, fallback, checksum, retention, and tests exist.
- Backup/DR: create/gzip/checksum/verify exists; staging restore rehearsal is NOT RUN.
- Finance: balanced journal guard, idempotency, transactions, and closed-period guard exist; go-live balances require reconciliation/sign-off.
- Foundation: state services, readiness/acceptance gates and immutable evidence registry exist; staging integrity command must pass.
- Procurement/inventory: immutable stock ledger, idempotency and negative policy exist; inventory verifier must pass on production candidate data.

## 10. Performance

Automated functional tests are not a 10,000-pile/10,000-document performance benchmark. Realistic volume measurement is NOT RUN.

## 11. UAT

Critical scenarios in `UAT_SCENARIOS.md`: NOT RUN.

## 12. Deployment

Supervisor/cron templates and runbooks exist. Actual worker, cron, HTTPS/DNS, mail, backup schedule and heartbeat must be verified on target infrastructure.

## 13. Known limitations

- Vendor-specific caliper integration BLOCKED pending real vendor specification; generic CSV remains supported.
- Unrealized FX revaluation BLOCKED pending accounting policy; realized FX remains supported.
- Offline capability remains PARTIAL; direct upload retry/idempotent finalize is not equivalent to full offline-first.
- External tax/e-Bupot behavior depends on provider/regulatory integration outside the core.

## 14. Open blockers

1. Local environment is `local`, uses HTTP, and has demo seeding enabled.
2. Three migrations are pending on the audited local database.
3. Scheduler heartbeat, fresh backup, and verified backup are absent locally.
4. Mail test has not run and no active company storage profile is configured locally.
5. DR staging rehearsal has not been performed.
6. Critical UAT scenarios are not signed off.
7. Realistic-volume performance baseline has not been performed.
8. Target infrastructure queue/cron/HTTPS/mail configuration is not verified.
9. Real-company opening balances and subledger reconciliation are not signed off.

## 15. Go-live recommendation

**NOT READY**

The codebase has strong controls, but mandatory external operational evidence is absent. Do not create `v1.0.0` until blockers above are closed and this report is refreshed.
