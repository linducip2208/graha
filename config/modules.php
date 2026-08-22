<?php

return [
    'visible' => array_values(array_filter(array_map('trim', explode(',', env('VISIBLE_MODULES', 'manufacturing,accounting'))))),

    'nav' => [
        ['label' => 'Dashboard', 'items' => [
            ['label' => 'Executive Dashboard', 'href' => '/dashboard', 'icon' => 'dashboard'],
        ]],
        ['label' => 'Organisasi', 'items' => [
            ['label' => 'Perusahaan & Role', 'href' => '/admin/organization', 'icon' => 'building', 'permission' => 'organization.view', 'children' => [
                ['label' => 'Perusahaan & Cabang', 'href' => '/admin/organization'],
                ['label' => 'Role & Permission', 'href' => '/admin/organization/roles'],
            ]],
        ]],
        ['label' => 'Marketing & Tender', 'items' => [
            ['label' => 'Pelanggan, Tender & Kompetitor', 'href' => '/admin/tenders', 'icon' => 'flag', 'permission' => 'tender.view'],
        ]],
        ['label' => 'Project & Bored Pile', 'items' => [
            ['label' => 'Proyek, Gantt & Kurva-S', 'href' => '/admin/projects', 'icon' => 'cube', 'permission' => 'project.view'],
            ['label' => 'Field Operations', 'href' => '/admin/projects/field-ops', 'icon' => 'wrench', 'permission' => 'project.view', 'children' => [
                ['label' => 'Drilling Record & Bore Log', 'href' => '/admin/projects/field-ops#drilling'],
                ['label' => 'Delivery Beton (Slump)', 'href' => '/admin/projects/field-ops#concrete'],
                ['label' => 'Pile Testing', 'href' => '/admin/projects/field-ops#testing'],
            ]],
        ]],
        ['label' => 'Supply Chain', 'items' => [
            ['label' => 'Inventory & Gudang', 'href' => '/admin/inventory', 'icon' => 'archive', 'permission' => 'inventory.view', 'children' => [
                ['label' => 'Stok Kritis', 'href' => '/admin/inventory#stok-kritis'],
                ['label' => 'Stock Opname', 'href' => '/admin/inventory/opname'],
                ['label' => 'Permintaan Material Proyek', 'href' => '/admin/inventory/material-requests'],
                ['label' => 'Tools Check-out', 'href' => '/admin/tools'],
            ]],
        ]],
        ['label' => 'Procurement', 'items' => [
            ['label' => 'Vendor, PO & Receipt', 'href' => '/admin/procurement', 'icon' => 'cart', 'permission' => 'procurement.view'],
            ['label' => 'RFQ & Perbandingan Harga', 'href' => '/admin/procurement/rfq', 'icon' => 'swap', 'permission' => 'procurement.view'],
        ]],
        ['label' => 'Engineering & Workshop', 'items' => [
            ['label' => 'Manufacturing Control', 'href' => '/admin/manufacturing', 'icon' => 'cog', 'permission' => 'manufacturing.view', 'children' => [
                ['label' => 'Routing & Biaya Produksi', 'href' => '/admin/manufacturing/costing'],
                ['label' => 'Quality Control Produksi', 'href' => '/admin/manufacturing/quality'],
                ['label' => 'Output Ditolak & Disposition', 'href' => '/admin/manufacturing/nonconforming'],
                ['label' => 'Reinforcement Cage', 'href' => '/admin/manufacturing/cages'],
                ['label' => 'Casing Pile', 'href' => '/admin/casings'],
            ]],
            ['label' => 'Equipment & Tangki BBM', 'href' => '/admin/fuel-tanks', 'icon' => 'wrench', 'permission' => 'equipment.view', 'children' => [
                ['label' => 'Produksi & Equipment', 'href' => '/admin/operations'],
                ['label' => 'Tangki BBM & Rekonsiliasi', 'href' => '/admin/fuel-tanks'],
            ]],
        ]],
        ['label' => 'Finance & Accounting', 'items' => [
            ['label' => 'General Ledger & Periode', 'href' => '/admin/finance', 'icon' => 'banknote', 'permission' => 'finance.view', 'children' => [
                ['label' => 'Chart of Accounts', 'href' => '/admin/finance/accounts'],
                ['label' => 'Periode Fiskal', 'href' => '/admin/finance/periods'],
                ['label' => 'Jurnal', 'href' => '/admin/finance/journals'],
                ['label' => 'Accounting Mapping', 'href' => '/admin/finance/accounting-mappings'],
                ['label' => 'Trial Balance & Laporan Keuangan', 'href' => '/admin/reports/financial-statements'],
            ]],
            ['label' => 'Penagihan & Pajak', 'href' => '/admin/billing', 'icon' => 'receipt', 'permission' => 'finance.view', 'children' => [
                ['label' => 'Progress Billing & Retensi', 'href' => '/admin/billing'],
                ['label' => 'Pajak & Bukti Potong', 'href' => '/admin/taxes'],
            ]],
            ['label' => 'Kas, Bank & Rekonsiliasi', 'href' => '/admin/cash-bank', 'icon' => 'wallet', 'permission' => 'finance.view'],
            ['label' => 'Project Costing & EAC', 'href' => '/admin/project-costing', 'icon' => 'pie', 'permission' => 'finance.view'],
            ['label' => 'Fixed Asset & Depresiasi', 'href' => '/admin/fixed-assets', 'icon' => 'briefcase', 'permission' => 'finance.view'],
            ['label' => 'Procurement Posting', 'href' => '/admin/procurement-accounting', 'icon' => 'calculator', 'permission' => 'accounting.post'],
        ]],
        ['label' => 'Governance', 'items' => [
            ['label' => 'Document Control', 'href' => '/admin/documents', 'icon' => 'document', 'permission' => 'document.view'],
        ]],
        ['label' => 'Approval & Signing', 'items' => [
            ['label' => 'Inbox & Workflow', 'href' => '/admin/approvals', 'icon' => 'check', 'permission' => 'approval.view'],
            ['label' => 'Digital Signing', 'href' => '/admin/signatures', 'icon' => 'pen', 'permission' => 'signature.view'],
        ]],
        ['label' => 'Quality, HSE & ISO', 'items' => [
            ['label' => 'Risiko, NCR & Audit Mutu', 'href' => '/admin/qms', 'icon' => 'shield', 'permission' => 'qms.view'],
            ['label' => 'HSE, JSA & Incident', 'href' => '/admin/hse', 'icon' => 'triangle-alert', 'permission' => 'hse.view'],
        ]],
        ['label' => 'Administrasi', 'items' => [
            ['label' => 'Notifikasi', 'href' => '/admin/notifications', 'icon' => 'bell'],
            ['label' => 'Audit Trail', 'href' => '/admin/audit', 'icon' => 'search', 'permission' => 'audit.view'],
            ['label' => 'Pengaturan Perusahaan', 'href' => '/admin/settings', 'icon' => 'cog'],
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
