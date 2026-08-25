<?php

return [
    'visible' => array_values(array_filter(array_map('trim', explode(',', env('VISIBLE_MODULES', 'manufacturing,accounting'))))),

    /*
    | Workspace UX: Beranda · Komersial · Proyek · Supply Chain ·
    | Workshop & Equipment · Keuangan · Quality & HSE · Dokumen & Approval ·
    | Laporan · Pengaturan  (maksimal 10 workspace).
    */
    'nav' => [
        ['key' => 'beranda', 'label' => '🏠 Beranda', 'items' => [
            ['label' => 'Executive Dashboard', 'href' => '/dashboard', 'icon' => 'dashboard'],
            ['label' => 'My Work', 'href' => '/admin/my-work', 'icon' => 'check'],
            ['label' => 'Semua Aplikasi', 'href' => '/apps', 'icon' => 'grid'],
        ]],
        ['key' => 'komersial', 'label' => '💼 Komersial', 'items' => [
            ['label' => 'Tender & Pelanggan', 'href' => '/admin/tenders', 'icon' => 'flag', 'permission' => 'tender.view'],
            ['label' => 'Administrasi Kontrak', 'href' => '/admin/contracts', 'icon' => 'document', 'permission' => 'contract.view', 'children' => [
                ['label' => 'Milestone & Asuransi', 'href' => '/admin/contract-admin'],
            ]],
        ]],
        ['key' => 'proyek', 'label' => '🏗️ Proyek', 'items' => [
            ['label' => 'Daftar Proyek & Gantt', 'href' => '/admin/projects', 'icon' => 'cube', 'permission' => 'project.view'],
            ['label' => 'Field Operations', 'href' => '/admin/projects/field-ops', 'icon' => 'wrench', 'permission' => 'project.view', 'children' => [
                ['label' => 'Drilling Record & Bore Log', 'href' => '/admin/projects/field-ops#drilling'],
                ['label' => 'Delivery Beton (Slump)', 'href' => '/admin/projects/field-ops#concrete'],
                ['label' => 'Pile Testing', 'href' => '/admin/projects/field-ops#testing'],
            ]],
            ['label' => 'Foundation Control', 'href' => '/admin/projects#foundation', 'icon' => 'shield', 'permission' => 'project.view'],
        ]],
        ['key' => 'supply-chain', 'label' => '📦 Supply Chain', 'items' => [
            ['label' => 'Inventory', 'href' => '/admin/inventory', 'icon' => 'archive', 'permission' => 'inventory.view', 'children' => [
                ['label' => 'Stok Kritis', 'href' => '/admin/inventory#stok-kritis'],
                ['label' => 'Rekomendasi Reorder', 'href' => '/admin/inventory/reorder'],
            ]],
            ['label' => 'Material Request', 'href' => '/admin/inventory/material-requests', 'icon' => 'swap', 'permission' => 'inventory.view'],
            ['label' => 'Stock Opname', 'href' => '/admin/inventory/opname', 'icon' => 'check', 'permission' => 'inventory.view'],
            ['label' => 'Lot Traceability', 'href' => '/admin/inventory/lots', 'icon' => 'search', 'permission' => 'inventory.view'],
            ['label' => 'Tools Check-out', 'href' => '/admin/tools', 'icon' => 'wrench', 'permission' => 'inventory.view'],
            ['label' => 'Procurement', 'href' => '/admin/procurement', 'icon' => 'cart', 'permission' => 'procurement.view'],
            ['label' => 'RFQ & Perbandingan Harga', 'href' => '/admin/procurement/rfq', 'icon' => 'calculator', 'permission' => 'procurement.view'],
        ]],
        ['key' => 'operations', 'label' => '🏭 Workshop & Equipment', 'items' => [
            ['label' => 'Manufacturing Control', 'href' => '/admin/manufacturing', 'icon' => 'cog', 'permission' => 'manufacturing.view', 'children' => [
                ['label' => 'Routing & Biaya Produksi', 'href' => '/admin/manufacturing/costing'],
                ['label' => 'Quality Control Produksi', 'href' => '/admin/manufacturing/quality'],
                ['label' => 'Output Ditolak & Disposition', 'href' => '/admin/manufacturing/nonconforming'],
            ]],
            ['label' => 'Reinforcement Cage', 'href' => '/admin/manufacturing/cages', 'icon' => 'grid', 'permission' => 'manufacturing.view'],
            ['label' => 'Casing Pile', 'href' => '/admin/casings', 'icon' => 'archive', 'permission' => 'equipment.view'],
            ['label' => 'Equipment & Produksi', 'href' => '/admin/operations', 'icon' => 'wrench', 'permission' => 'manufacturing.view'],
            ['label' => 'Register Kalibrasi', 'href' => '/admin/calibrations', 'icon' => 'scale', 'permission' => 'manufacturing.view'],
            ['label' => 'Tangki BBM', 'href' => '/admin/fuel-tanks', 'icon' => 'percent', 'permission' => 'equipment.view'],
        ]],
        ['key' => 'keuangan', 'label' => '💰 Keuangan', 'items' => [
            ['label' => 'Ikhtisar Keuangan', 'href' => '/admin/finance/overview', 'icon' => 'pie', 'permission' => 'finance.view'],
            ['label' => 'General Ledger & Periode', 'href' => '/admin/finance', 'icon' => 'banknote', 'permission' => 'finance.view', 'children' => [
                ['label' => 'Chart of Accounts', 'href' => '/admin/finance/accounts'],
                ['label' => 'Periode Fiskal', 'href' => '/admin/finance/periods'],
                ['label' => 'Jurnal', 'href' => '/admin/finance/journals'],
                ['label' => 'Jurnal Berulang', 'href' => '/admin/recurring-journals'],
                ['label' => 'Accounting Mapping', 'href' => '/admin/finance/accounting-mappings'],
                ['label' => 'Trial Balance & Laporan Keuangan', 'href' => '/admin/reports/financial-statements'],
                ['label' => 'Arus Kas (Metode Langsung)', 'href' => '/admin/reports/cash-flow'],
            ]],
            ['label' => 'Penagihan & Pajak', 'href' => '/admin/billing', 'icon' => 'receipt', 'permission' => 'finance.view', 'children' => [
                ['label' => 'Progress Billing & Retensi', 'href' => '/admin/billing'],
                ['label' => 'Pajak & Bukti Potong', 'href' => '/admin/taxes'],
            ]],
            ['label' => 'Kas, Bank & Rekonsiliasi', 'href' => '/admin/cash-bank', 'icon' => 'wallet', 'permission' => 'finance.view'],
            ['label' => 'Project Costing & EAC', 'href' => '/admin/project-costing', 'icon' => 'chart', 'permission' => 'finance.view'],
            ['label' => 'Fixed Asset & Depresiasi', 'href' => '/admin/fixed-assets', 'icon' => 'briefcase', 'permission' => 'finance.view'],
            ['label' => 'Procurement Posting', 'href' => '/admin/procurement-accounting', 'icon' => 'calculator', 'permission' => 'accounting.post'],
        ]],
        ['key' => 'quality-hse', 'label' => '✅ Quality & HSE', 'items' => [
            ['label' => 'Risiko, NCR & Audit Mutu', 'href' => '/admin/qms', 'icon' => 'shield', 'permission' => 'qms.view'],
            ['label' => 'Keluhan Pelanggan', 'href' => '/admin/complaints', 'icon' => 'bell', 'permission' => 'qms.view'],
            ['label' => 'Inspection & Test Plan', 'href' => '/admin/itps', 'icon' => 'clipboard-check', 'permission' => 'qms.view'],
            ['label' => 'HSE, JSA & Incident', 'href' => '/admin/hse', 'icon' => 'triangle-alert', 'permission' => 'hse.view'],
            ['label' => 'KPI Keselamatan (FR/SR)', 'href' => '/admin/hse/metrics', 'icon' => 'activity', 'permission' => 'hse.view'],
        ]],
        ['key' => 'documents-approval', 'label' => '📄 Dokumen & Approval', 'items' => [
            ['label' => 'Document Control', 'href' => '/admin/documents', 'icon' => 'document', 'permission' => 'document.view'],
            ['label' => 'Approval Center', 'href' => '/admin/approvals', 'icon' => 'check', 'permission' => 'approval.view'],
            ['label' => 'Digital Signing', 'href' => '/admin/signatures', 'icon' => 'pen', 'permission' => 'signature.view'],
            ['label' => 'Audit Trail', 'href' => '/admin/audit', 'icon' => 'search', 'permission' => 'audit.view'],
        ]],
        ['key' => 'laporan', 'label' => '📊 Laporan', 'items' => [
            ['label' => 'Bisnis & Tender', 'href' => '/admin/reports/executive', 'icon' => 'chart', 'permission' => 'report.view'],
            ['label' => 'Keuangan', 'href' => '/admin/reports/finance', 'icon' => 'calculator', 'permission' => 'report.view'],
            ['label' => 'Operasional', 'href' => '/admin/reports/operations', 'icon' => 'dashboard', 'permission' => 'report.view'],
            ['label' => 'Manufaktur', 'href' => '/admin/reports/manufacturing', 'icon' => 'cog', 'permission' => 'report.view'],
            ['label' => 'AR/AP Aging', 'href' => '/admin/reports/aging', 'icon' => 'clock', 'permission' => 'report.view'],
        ]],
        ['key' => 'pengaturan', 'label' => '⚙️ Pengaturan', 'items' => [
            ['label' => 'Perusahaan & Cabang', 'href' => '/admin/organization', 'icon' => 'building', 'permission' => 'organization.view'],
            ['label' => 'Role & Permission', 'href' => '/admin/organization/roles', 'icon' => 'user', 'permission' => 'organization.view'],
            ['label' => 'Pengaturan Perusahaan', 'href' => '/admin/settings', 'icon' => 'cog'],
            ['label' => 'Notifikasi', 'href' => '/admin/notifications', 'icon' => 'bell'],
        ]],
    ],
];
