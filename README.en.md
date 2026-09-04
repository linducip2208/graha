# Graha Pondasi ERP

Graha Pondasi ERP is an integrated ERP for foundation and bored-pile contractors. It follows work from tender and contract administration through field execution, manufacturing, procurement, inventory, Indonesian accounting, QMS/HSE, document control, approvals, and digital signing in one auditable data trail.

**Languages:** [English](README.en.md) · [Bahasa Indonesia](README.md) · [العربية](README.ar.md)

## Technology

- Laravel 13, PHP 8.3, MySQL 8.4
- AdminLTE 4.9.1, Bootstrap 5.3, vanilla JavaScript, Vite
- Blade and Tailwind CSS v4 for existing domain screens
- PHPUnit, Laravel Pint, modular monolith architecture
- Strict multi-company isolation and role-based membership access

## Complete feature map

| Area | Features |
|---|---|
| Executive dashboard | Permission-aware KPI cards, revenue/VAT charts, AR/AP ageing, role-specific workspace views, app launcher, quick create, global search, dark mode |
| Organization and access | Companies, branches, departments, memberships, roles, permissions, company switching, scoped authorization |
| Marketing and tenders | Customers, payment terms, tenders, estimates, outcomes, award conversion, project handover |
| Projects and bored piles | Projects, zones, pile points, nine-state lifecycle, Gantt, S-curve, pile passport, genealogy, as-built records, acceptance gates |
| Field operations | Drilling records, normalized bore logs, cage installation, concrete delivery with slump and accept/reject controls, PIT/PDA/CSL/SLT/DLT testing, private photo evidence |
| Supply chain | Items, units of measure, warehouses, bins, immutable inventory ledger, stock alerts, stock opname, lot traceability, material requests, tool checkout and condition photos |
| Procurement | Vendors, RFQ, purchase orders, revision snapshots, three-way matching, goods receipt, GRNI posting, budget checks |
| Engineering and workshop | BOM, routing, work centers, WIP reconciliation, QC disposition, equipment hour meter, fuel tanks and reconciliation, maintenance work orders, reinforcement cages, casing rental costs |
| Finance and accounting | Chart of accounts, balanced idempotent journals, configurable accounting mappings, fiscal periods and closing gates, progress billing, retention and advances, output VAT, invoice PDFs, cash/bank reconciliation, input VAT, withholding tax, project EAC costing, fixed assets, depreciation, realized FX |
| Governance | Versioned document control, SHA-256 integrity, isolated downloads, automatic document registry, billing/PO/MWO/NCR/CAPA registration |
| Approvals and signing | Sequential, any/all, quorum workflows, SLA, delegation, rejection reasons, audit trail, provider-agnostic encrypted signing integration |
| Quality, HSE and ISO | Risks/opportunities, NCR/CAPA independence guard, internal quality audits, evidence expiry, JSA, work permits, incidents, safety metrics |
| Reporting | Business, financial, operational, project costing, utilization, tax, inventory, procurement, and audit views with export-ready data |
| Administration | Notifications, email hooks, audit hash-chain viewer, backups, storage profiles, settings, API v1, security headers |
| Public experience | Indonesian marketing front page, public docs, real feature screenshots, responsive layouts, reduced-motion support, RTL-ready document shell |

## AdminLTE 4.9.1 integration

The authenticated shell uses the official `colorlibhq/adminlte-laravel` package and AdminLTE 4.9.1 assets. `resources/css/adminlte.css` imports Bootstrap, Bootstrap Icons, and AdminLTE. `resources/js/adminlte.js` loads Bootstrap, OverlayScrollbars, AdminLTE, and the application JavaScript. The Blade shell exposes AdminLTE landmarks (`app-wrapper`, `app-sidebar`, `app-header`, and `app-main`) while preserving Graha's permission-driven navigation.

```bash
composer install --no-interaction
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm ci
npm run build
php artisan serve
```

## Demo accounts

All demo accounts use password `password`.

| Role | Email | Scope |
|---|---|---|
| Super Admin | admin@grahapondasi.test | All modules and audit trail |
| Finance Manager | finance@grahapondasi.test | Billing, tax, journals, reports |
| Project Manager | pm@grahapondasi.test | Projects, field operations, equipment, HSE |
| Procurement Officer | procurement@grahapondasi.test | Vendors, purchase orders, warehouse |
| Operations Director | direktur@grahapondasi.test | Approval center and tenders |

## Business flow

`Tender → Estimate → Won → Award → Signing → Handover → Active project → RFQ/PO → Goods receipt → Inventory → Cage/field execution → Testing gate → Progress billing → Receipt → Journal → Costing → Closing`

## Queues, schedules, storage, and security

Run `php artisan queue:work --tries=3` and `php artisan schedule:work` in production. Scheduled jobs cover database backups, QMS evidence expiry, approval SLA, critical-stock alerts, and NCR/CAPA deadlines. Private files live under `storage/app/private`; downloads are authorization-controlled. Provider credentials are user-configured, encrypted at rest, never hard-coded, logged, or returned in API responses. Company isolation, CSRF, rate limits, webhook replay protection, and append-only audit hashing are enabled.

## Verification and deployment

```bash
composer test
vendor/bin/pint
npm run build
node scripts/screenshot.cjs
```

See [DEPLOYMENT.md](DEPLOYMENT.md), `deploy/nginx.conf`, and `deploy/supervisor.conf` for production setup. See `docs/` for operational guides, UAT scenarios, architecture decisions, and API documentation.

## Known limitations

Business Process Mapping Level 0 and unrealized FX revaluation are outside the current baseline. HR/payroll is intentionally out of scope. Some chart surfaces still require a refresh after changing dark mode.
