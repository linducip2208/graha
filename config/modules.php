<?php

return [
    'visible' => array_values(array_filter(array_map('trim', explode(',', env('VISIBLE_MODULES', 'manufacturing,accounting'))))),

    'nav' => [
        ['key' => 'workspace', 'label' => 'Workspace', 'items' => [
            ['label' => 'Dashboard Eksekutif', 'href' => '/dashboard', 'icon' => 'dashboard', 'exact' => true],
            ['label' => 'Tender & Pelanggan', 'href' => '/admin/tenders', 'icon' => 'flag', 'permission' => 'tender.view'],
            ['label' => 'Proyek & Bored Pile', 'href' => '/admin/projects', 'icon' => 'cube', 'permission' => 'project.view'],
        ]],
        ['key' => 'operations', 'label' => 'Operasional', 'items' => [
            ['label' => 'Inventory & Gudang', 'href' => '/admin/inventory', 'icon' => 'archive', 'permission' => 'inventory.view'],
            ['label' => 'Procurement', 'href' => '/admin/procurement', 'icon' => 'cart', 'permission' => 'procurement.view'],
            ['label' => 'Produksi & Equipment', 'href' => '/admin/operations', 'icon' => 'wrench', 'permission' => 'manufacturing.view'],
            ['label' => 'Manufacturing', 'href' => '/admin/manufacturing', 'icon' => 'cog', 'permission' => 'manufacturing.view', 'children' => [
                ['label' => 'Ringkasan Produksi', 'href' => '/admin/manufacturing', 'exact' => true, 'permission' => 'manufacturing.view'],
                ['label' => 'Routing & Biaya Produksi', 'href' => '/admin/manufacturing/costing'],
                ['label' => 'Quality Control Produksi', 'href' => '/admin/manufacturing/quality'],
                ['label' => 'Output Ditolak & Disposition', 'href' => '/admin/manufacturing/nonconforming'],
            ]],
        ]],
        ['key' => 'finance', 'label' => 'Keuangan', 'items' => [
            ['label' => 'Accounting & General Ledger', 'href' => '/admin/finance', 'icon' => 'banknote', 'permission' => 'finance.view', 'children' => [
                ['label' => 'Ringkasan Accounting', 'href' => '/admin/finance', 'exact' => true, 'permission' => 'finance.view'],
                ['label' => 'Chart of Accounts', 'href' => '/admin/finance/accounts', 'permission' => 'finance.view'],
                ['label' => 'Periode Fiskal', 'href' => '/admin/finance/periods', 'permission' => 'finance.view'],
                ['label' => 'Jurnal', 'href' => '/admin/finance/journals', 'permission' => 'finance.view'],
                ['label' => 'Mapping Accounting', 'href' => '/admin/finance/accounting-mappings', 'permission' => 'finance.manage'],
            ]],
            ['label' => 'Billing & Kas/Bank', 'href' => '/admin/billing', 'icon' => 'wallet', 'permission' => 'finance.view', 'children' => [
                ['label' => 'Progress Billing & Retensi', 'href' => '/admin/billing', 'exact' => true, 'permission' => 'finance.view'],
                ['label' => 'Kas, Bank & Rekonsiliasi', 'href' => '/admin/cash-bank', 'permission' => 'finance.view'],
                ['label' => 'Pajak & Bukti Potong', 'href' => '/admin/taxes', 'permission' => 'finance.view'],
            ]],
            ['label' => 'Costing & Aset', 'href' => '/admin/project-costing', 'icon' => 'pie', 'permission' => 'finance.view', 'children' => [
                ['label' => 'Project Costing & EAC', 'href' => '/admin/project-costing', 'exact' => true, 'permission' => 'finance.view'],
                ['label' => 'Fixed Asset & Depresiasi', 'href' => '/admin/fixed-assets', 'permission' => 'finance.view'],
            ]],
            ['label' => 'Procurement Posting', 'href' => '/admin/procurement-accounting', 'icon' => 'calculator', 'permission' => 'accounting.post'],
        ]],
        ['key' => 'governance', 'label' => 'Mutu & Tata Kelola', 'items' => [
            ['label' => 'Document Control', 'href' => '/admin/documents', 'icon' => 'document', 'permission' => 'document.view'],
            ['label' => 'Approval & Workflow', 'href' => '/admin/approvals', 'icon' => 'check', 'permission' => 'approval.view'],
            ['label' => 'Digital Signing', 'href' => '/admin/signatures', 'icon' => 'pen', 'permission' => 'signature.view'],
            ['label' => 'QMS, Risiko & NCR', 'href' => '/admin/qms', 'icon' => 'shield', 'permission' => 'qms.view'],
            ['label' => 'HSE, JSA & Incident', 'href' => '/admin/hse', 'icon' => 'triangle-alert', 'permission' => 'hse.view'],
        ]],
        ['key' => 'reports', 'label' => 'Analitik', 'items' => [
            ['label' => 'Pusat Laporan', 'href' => '/admin/reports/executive', 'icon' => 'chart', 'permission' => 'report.view', 'children' => [
                ['label' => 'Bisnis & Tender', 'href' => '/admin/reports/executive', 'exact' => true, 'permission' => 'report.view'],
                ['label' => 'Keuangan', 'href' => '/admin/reports/finance', 'permission' => 'report.view'],
                ['label' => 'Laporan Keuangan', 'href' => '/admin/reports/financial-statements', 'permission' => 'report.view'],
                ['label' => 'AR/AP Aging', 'href' => '/admin/reports/aging', 'permission' => 'report.view'],
                ['label' => 'Operasional', 'href' => '/admin/reports/operations', 'permission' => 'report.view'],
                ['label' => 'Manufaktur', 'href' => '/admin/reports/manufacturing', 'permission' => 'report.view'],
            ]],
        ]],
        ['key' => 'system', 'label' => 'Sistem', 'items' => [
            ['label' => 'Perusahaan & Cabang', 'href' => '/admin/organization', 'icon' => 'building', 'permission' => 'organization.view'],
            ['label' => 'Notifikasi', 'href' => '/admin/notifications', 'icon' => 'bell'],
            ['label' => 'Audit Trail', 'href' => '/admin/audit', 'icon' => 'search', 'permission' => 'audit.view'],
            ['label' => 'Pengaturan', 'href' => '/admin/settings', 'icon' => 'cog'],
        ]],
    ],
];
