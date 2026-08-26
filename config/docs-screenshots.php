<?php

/**
 * Registry screenshot dokumentasi (P11). Kunci stabil memakai referensi demo
 * (project code / pile number / document number) — DILARANG hardcode DB id.
 *
 * Output disimpan ke disk DOCS_DISK (default local) di bawah
 * docs/screenshots/<kategori>/<output>.
 */
return [
    'defaults' => [
        'actor' => 'admin@grahapondasi.test',
        'viewport' => [1440, 1000],
        'full_page' => true,
        'wait_ms' => 900,
    ],

    'shots' => [
        // ===== Dashboard & Navigasi =====
        'dashboard' => ['category' => 'dashboard', 'article' => 'dashboard', 'route' => '/dashboard', 'output' => 'dashboard/dashboard.webp'],
        'apps-launcher' => ['category' => 'dashboard', 'article' => 'app-launcher', 'route' => '/apps', 'output' => 'dashboard/apps.webp'],
        'my-work' => ['category' => 'dashboard', 'article' => 'my-work', 'route' => '/admin/my-work', 'output' => 'dashboard/my-work.webp'],

        // ===== Komersial =====
        'tender-list' => ['category' => 'commercial', 'article' => 'tender-pipeline', 'route' => '/admin/tenders', 'output' => 'commercial/tenders.webp'],
        'contracts' => ['category' => 'commercial', 'article' => 'contract-administration', 'route' => '/admin/contract-admin', 'output' => 'commercial/contracts.webp'],

        // ===== Proyek =====
        'project-list' => ['category' => 'projects', 'article' => 'project-control-center', 'route' => '/admin/projects', 'output' => 'projects/project-list.webp'],
        'project-detail' => ['category' => 'projects', 'article' => 'project-detail', 'fixture' => ['project_code' => 'PRJ-2601'], 'route' => '/admin/projects/{project_id}?tab=overview', 'output' => 'projects/project-detail.webp'],
        'project-piles' => ['category' => 'projects', 'article' => 'pile-register', 'fixture' => ['project_code' => 'PRJ-2601'], 'route' => '/admin/projects/{project_id}?tab=piles', 'output' => 'projects/pile-register.webp'],
        'foundation-groups' => ['category' => 'projects', 'article' => 'foundation-groups', 'fixture' => ['project_code' => 'PRJ-2601'], 'route' => '/admin/projects/{project_id}?tab=piles#grup', 'output' => 'projects/foundation-groups.webp'],

        // ===== Bored Pile / Foundation =====
        'foundation-control' => ['category' => 'bored-pile', 'article' => 'foundation-control-tower', 'fixture' => ['project_code' => 'PRJ-2602'], 'route' => '/admin/projects/{project_id}/foundation-control', 'output' => 'bored-pile/foundation-control.webp'],
        'pile-passport' => ['category' => 'bored-pile', 'article' => 'pile-passport', 'fixture' => ['pile_number' => 'BP-A01'], 'route' => '/admin/bored-piles/{pile_id}/passport', 'output' => 'bored-pile/passport.webp'],
        'pile-genealogy' => ['category' => 'bored-pile', 'article' => 'genealogy-as-built', 'fixture' => ['pile_number' => 'BP-A01'], 'route' => '/admin/bored-piles/{pile_id}/genealogy', 'output' => 'bored-pile/genealogy.webp'],
        'field-ops' => ['category' => 'bored-pile', 'article' => 'field-operations', 'fixture' => ['project_code' => 'PRJ-2601'], 'route' => '/admin/projects/field-ops?project={project_id}', 'output' => 'bored-pile/field-ops.webp'],

        // ===== Supply Chain =====
        'inventory' => ['category' => 'supply-chain', 'article' => 'inventory', 'route' => '/admin/inventory', 'output' => 'supply-chain/inventory.webp'],
        'procurement' => ['category' => 'supply-chain', 'article' => 'procurement-po', 'route' => '/admin/procurement', 'output' => 'supply-chain/procurement.webp'],
        'rfq' => ['category' => 'supply-chain', 'article' => 'rfq', 'route' => '/admin/rfq', 'output' => 'supply-chain/rfq.webp'],

        // ===== Equipment =====
        'equipment-list' => ['category' => 'equipment', 'article' => 'equipment', 'route' => '/admin/operations', 'output' => 'equipment/operations.webp'],

        // ===== Keuangan =====
        'finance-overview' => ['category' => 'finance', 'article' => 'finance-overview', 'route' => '/admin/finance/overview', 'output' => 'finance/overview.webp'],
        'journals' => ['category' => 'finance', 'article' => 'journals', 'route' => '/admin/finance/journals', 'output' => 'finance/journals.webp'],
        'billing' => ['category' => 'finance', 'article' => 'progress-billing', 'route' => '/admin/billing', 'output' => 'finance/billing.webp'],
        'cash-bank' => ['category' => 'finance', 'article' => 'cash-bank', 'route' => '/admin/cash-bank', 'output' => 'finance/cash-bank.webp'],

        // ===== Quality & HSE =====
        'qms' => ['category' => 'qms', 'article' => 'ncr-capa', 'route' => '/admin/qms', 'output' => 'qms/qms.webp'],
        'itps' => ['category' => 'qms', 'article' => 'itp', 'route' => '/admin/itps', 'output' => 'qms/itps.webp'],
        'hse' => ['category' => 'hse', 'article' => 'hse-workspace', 'route' => '/admin/hse', 'output' => 'hse/hse.webp'],

        // ===== Dokumen & Approval =====
        'documents' => ['category' => 'documents', 'article' => 'document-control', 'route' => '/admin/documents', 'output' => 'documents/documents.webp'],
        'approvals' => ['category' => 'documents', 'article' => 'approval-center', 'route' => '/admin/approvals', 'output' => 'documents/approvals.webp'],
        'audit-trail' => ['category' => 'documents', 'article' => 'audit-trail', 'route' => '/admin/audit', 'output' => 'documents/audit.webp'],

        // ===== Laporan & Pengaturan =====
        'report-executive' => ['category' => 'reports', 'article' => 'executive-report', 'route' => '/admin/reports/executive', 'output' => 'reports/executive.webp'],
        'settings' => ['category' => 'settings', 'article' => 'company-settings', 'route' => '/admin/settings', 'output' => 'settings/settings.webp'],
        'experience-studio' => ['category' => 'settings', 'article' => 'experience-studio', 'route' => '/admin/experience', 'output' => 'settings/experience.webp'],

        // ===== Ekspansi cakupan (P24/P23/P18) =====
        'notifications' => ['category' => 'dashboard', 'article' => 'notifications', 'route' => '/admin/notifications', 'output' => 'dashboard/notifications.webp'],
        'material-requests' => ['category' => 'supply-chain', 'article' => 'material-request', 'route' => '/admin/inventory/material-requests', 'output' => 'supply-chain/material-requests.webp'],
        'stock-opname' => ['category' => 'supply-chain', 'article' => 'stock-opname', 'route' => '/admin/inventory/opname', 'output' => 'supply-chain/opname.webp'],
        'lot-trace' => ['category' => 'supply-chain', 'article' => 'lot-traceability', 'route' => '/admin/inventory/lots', 'output' => 'supply-chain/lots.webp'],
        'reorder' => ['category' => 'supply-chain', 'article' => 'reorder', 'route' => '/admin/inventory/reorder', 'output' => 'supply-chain/reorder.webp'],
        'tools' => ['category' => 'equipment', 'article' => 'tools-checkout', 'route' => '/admin/tools', 'output' => 'equipment/tools.webp'],
        'casings-page' => ['category' => 'bored-pile', 'article' => 'casing', 'route' => '/admin/casings', 'output' => 'bored-pile/casings.webp'],
        'cages-page' => ['category' => 'bored-pile', 'article' => 'cage', 'route' => '/admin/manufacturing/cages', 'output' => 'bored-pile/cages.webp'],
        'project-costing' => ['category' => 'finance', 'article' => 'project-costing', 'route' => '/admin/project-costing', 'output' => 'finance/project-costing.webp'],
        'fixed-assets' => ['category' => 'finance', 'article' => 'fixed-assets', 'route' => '/admin/fixed-assets', 'output' => 'finance/fixed-assets.webp'],
        'account-budgets' => ['category' => 'finance', 'article' => 'budget-vs-actual', 'route' => '/admin/account-budgets', 'output' => 'finance/budgets.webp'],
        'recurring-journals' => ['category' => 'finance', 'article' => 'recurring-journals', 'route' => '/admin/recurring-journals', 'output' => 'finance/recurring.webp'],
        'prepaid-expense' => ['category' => 'finance', 'article' => 'prepaid-expense', 'route' => '/admin/prepaid-expenses', 'output' => 'finance/prepaid.webp'],
        'financial-statements' => ['category' => 'reports', 'article' => 'financial-statements', 'route' => '/admin/reports/financial-statements', 'output' => 'reports/statements.webp'],
        'cash-flow-report' => ['category' => 'reports', 'article' => 'cash-flow', 'route' => '/admin/reports/cash-flow', 'output' => 'reports/cashflow.webp'],
        'aging-report' => ['category' => 'reports', 'article' => 'aging', 'route' => '/admin/reports/aging', 'output' => 'reports/aging.webp'],
        'operations-report' => ['category' => 'reports', 'article' => 'operations-report', 'route' => '/admin/reports/operations', 'output' => 'reports/operations.webp'],
        'manufacturing-report' => ['category' => 'reports', 'article' => 'manufacturing-report', 'route' => '/admin/reports/manufacturing', 'output' => 'reports/manufacturing.webp'],
        'calibrations-page' => ['category' => 'qms', 'article' => 'calibration', 'route' => '/admin/calibrations', 'output' => 'qms/calibrations.webp'],
        'complaints-page' => ['category' => 'qms', 'article' => 'customer-complaints', 'route' => '/admin/complaints', 'output' => 'qms/complaints.webp'],
        'hse-metrics' => ['category' => 'hse', 'article' => 'hse-kpi', 'fixture' => [], 'route' => '/admin/hse/metrics', 'output' => 'hse/metrics.webp'],
        'organization' => ['category' => 'settings', 'article' => 'company-setup', 'route' => '/admin/organization', 'output' => 'settings/organization.webp'],
        'roles-permissions' => ['category' => 'settings', 'article' => 'role-permission', 'route' => '/admin/organization/roles', 'output' => 'settings/roles.webp'],
    ],
];
