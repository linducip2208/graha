# Production Readiness Report

## 1. Current commit

Production-gate baseline is commit `1ab35a4`; subsequent hardening must be included before release tagging.

## 2. Test results

Final automated suite: 356 tests / 1,713 assertions PASS. Pint, Vite production build, Blade view cache, route discovery, docs build, controller import detector, and docs audit also PASS.

Local gate run on 27 August 2026:

- `inventory:verify`: PASS, no anomaly detected.
- `foundation:verify`: PASS, no anomaly detected.
- `migrate --pretend` and MySQL migration execution: PASS; local migrations are up to date. An overlong MySQL index name found during rehearsal was corrected before release.
- Local `backup:database` and `backup:verify`: PASS with private gzip and SHA-256 verification.
- Scheduler event execution: PASS; heartbeat is current on the local environment.
- `production:check`: FAIL with four blockers, including a known demo password. The local environment is not a production candidate.
- `accounting:verify`, `inventory:verify`, and `foundation:verify`: PASS on the local dataset.
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

- Complete severity/status register: `KNOWN_ISSUES.md`.
- Per-gate evidence and remaining conditions: `PRODUCTION_READINESS_MATRIX.md`.
- Vendor-specific caliper integration BLOCKED pending real vendor specification; generic CSV remains supported.
- Unrealized FX revaluation BLOCKED pending accounting policy; realized FX remains supported.
- Offline capability remains PARTIAL; direct upload retry/idempotent finalize is not equivalent to full offline-first.
- External tax/e-Bupot behavior depends on provider/regulatory integration outside the core.

## 14. Open blockers

1. Local environment is `local`, uses HTTP, and has demo seeding enabled.
2. A local user account still uses the known demo password; production bootstrap now rejects missing/weak explicit credentials.
3. Mail test has not run and no active company storage profile is configured locally.
4. DR staging rehearsal has not been performed.
5. Critical UAT scenarios are not signed off.
6. Realistic-volume performance baseline has not been performed.
7. Target infrastructure queue/cron/HTTPS/mail configuration is not verified.
8. Real-company opening balances and subledger reconciliation are not signed off.

## 15. Go-live recommendation

**NOT READY**

The codebase has strong controls, but mandatory external operational evidence is absent. Do not create `v1.0.0` until blockers above are closed and this report is refreshed.
