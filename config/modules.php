<?php

return [
    'visible' => array_values(array_filter(array_map('trim', explode(',', env('VISIBLE_MODULES', 'manufacturing,accounting'))))),

    'nav' => [
        ['label' => 'Dashboard', 'items' => [
            ['label' => 'Executive Dashboard', 'href' => '/dashboard', 'icon' => 'dashboard'],
        ]],
        ['label' => 'Organisasi', 'items' => [
            ['label' => 'Perusahaan & Cabang', 'href' => '/admin/organization', 'icon' => 'building', 'permission' => 'organization.view'],
        ]],
        ['label' => 'Marketing & Tender', 'permission' => 'tender.view', 'items' => [
            ['label' => 'Pelanggan & Tender', 'href' => '/admin/tenders', 'icon' => 'flag'],
        ]],
        ['label' => 'Project & Bored Pile', 'permission' => 'project.view', 'items' => [
            ['label' => 'Proyek & Titik Bored Pile', 'href' => '/admin/projects', 'icon' => 'cube'],
        ]],
        ['label' => 'Supply Chain', 'permission' => 'inventory.view', 'items' => [
            ['label' => 'Inventory & Gudang', 'href' => '/admin/inventory', 'icon' => 'archive'],
            ['label' => 'Stok Kritis', 'href' => '/admin/inventory#stok-kritis', 'icon' => 'alert'],
        ]],
        ['label' => 'Procurement', 'permission' => 'procurement.view', 'items' => [
            ['label' => 'Vendor, PO & Receipt', 'href' => '/admin/procurement', 'icon' => 'cart'],
        ]],
        ['label' => 'Engineering & Workshop', 'permission' => 'manufacturing.view', 'items' => [
            ['label' => 'Manufacturing Control', 'href' => '/admin/manufacturing', 'icon' => 'cog', 'children' => [
                ['label' => 'Routing & Biaya Produksi', 'href' => '/admin/manufacturing/costing'],
                ['label' => 'Quality Control Produksi', 'href' => '/admin/manufacturing/quality'],
                ['label' => 'Output Ditolak & Disposition', 'href' => '/admin/manufacturing/nonconforming'],
            ]],
            ['label' => 'Produksi & Equipment', 'href' => '/admin/operations', 'icon' => 'wrench'],
        ]],
        ['label' => 'Finance & Accounting', 'permission' => 'finance.view', 'items' => [
            ['label' => 'COA & General Ledger', 'href' => '/admin/finance', 'icon' => 'banknote', 'children' => [
                ['label' => 'Chart of Accounts', 'href' => '/admin/finance/accounts'],
                ['label' => 'Periode Fiskal', 'href' => '/admin/finance/periods'],
                ['label' => 'Jurnal', 'href' => '/admin/finance/journals'],
                ['label' => 'Trial Balance & Laporan Keuangan', 'href' => '/admin/reports/financial-statements'],
            ]],
            ['label' => 'Accounting Mapping', 'href' => '/admin/finance/accounting-mappings', 'icon' => 'swap', 'permission' => 'finance.manage'],
            ['label' => 'Progress Billing & Retensi', 'href' => '/admin/billing', 'icon' => 'receipt'],
            ['label' => 'Kas, Bank & Rekonsiliasi', 'href' => '/admin/cash-bank', 'icon' => 'wallet'],
            ['label' => 'Pajak & Bukti Potong', 'href' => '/admin/taxes', 'icon' => 'percent'],
            ['label' => 'Project Costing & EAC', 'href' => '/admin/project-costing', 'icon' => 'pie'],
            ['label' => 'Fixed Asset & Depresiasi', 'href' => '/admin/fixed-assets', 'icon' => 'briefcase'],
            ['label' => 'Procurement Posting', 'href' => '/admin/procurement-accounting', 'icon' => 'calculator', 'permission' => 'accounting.post'],
        ]],
        ['label' => 'Governance', 'permission' => 'document.view', 'items' => [
            ['label' => 'Document Control', 'href' => '/admin/documents', 'icon' => 'document'],
        ]],
        ['label' => 'Approval & Signing', 'permission' => 'approval.view', 'signature_permission' => 'signature.view', 'items' => [
            ['label' => 'Inbox & Workflow', 'href' => '/admin/approvals', 'icon' => 'check'],
            ['label' => 'Digital Signing', 'href' => '/admin/signatures', 'icon' => 'pen', 'permission' => 'signature.view'],
        ]],
        ['label' => 'Quality, HSE & ISO', 'permission' => 'qms.view', 'hse_permission' => 'hse.view', 'items' => [
            ['label' => 'Risiko, NCR & Audit Mutu', 'href' => '/admin/qms', 'icon' => 'shield'],
            ['label' => 'HSE, JSA & Incident', 'href' => '/admin/hse', 'icon' => 'triangle-alert', 'permission' => 'hse.view'],
        ]],
        ['label' => 'Pengaturan', 'items' => [
            ['label' => 'Pusat Konfigurasi', 'href' => '/admin/settings', 'icon' => 'cog'],
        ]],
        ['label' => 'Administrasi', 'items' => [
            ['label' => 'Notifikasi', 'href' => '/admin/notifications', 'icon' => 'bell'],
            ['label' => 'Audit Trail', 'href' => '/admin/audit', 'icon' => 'search', 'permission' => 'audit.view'],
        ]],
        ['label' => 'Laporan', 'items' => [
            ['label' => 'Bisnis & Tender', 'href' => '/admin/reports/executive', 'icon' => 'chart', 'permission' => 'report.view'],
            ['label' => 'Keuangan', 'href' => '/admin/reports/finance', 'icon' => 'calculator', 'permission' => 'report.view'],
            ['label' => 'Operasional', 'href' => '/admin/reports/operations', 'icon' => 'dashboard', 'permission' => 'report.view'],
            ['label' => 'Manufaktur', 'href' => '/admin/reports/manufacturing', 'icon' => 'cog', 'permission' => 'report.view'],
            ['label' => 'AR/AP Aging', 'href' => '/admin/reports/aging', 'icon' => 'clock', 'permission' => 'report.view'],
        ]],
    ],
];
