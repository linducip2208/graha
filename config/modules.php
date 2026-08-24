<?php

return [
    'visible' => array_values(array_filter(array_map('trim', explode(',', env('VISIBLE_MODULES', 'manufacturing,accounting'))))),

    /*
    | Workspace UX: Beranda · Komersial · Proyek · Supply Chain ·
    | Workshop & Equipment · Keuangan · Quality & HSE · Dokumen & Approval ·
    | Laporan · Pengaturan  (maksimal 10 workspace).
    */
    'nav' => [
        ['label' => '🏠 Beranda', 'items' => [
            ['label' => 'Executive Dashboard', 'href' => '/dashboard', 'icon' => 'dashboard'],
            ['label' => 'My Work', 'href' => '/admin/my-work', 'icon' => 'check'],
            ['label' => 'Semua Aplikasi', 'href' => '/apps', 'icon' => 'grid'],
        ]],
        ['label' => '💼 Komersial', 'items' => [
            ['label' => 'Tender & Pelanggan', 'href' => '/admin/tenders', 'icon' => 'flag', 'permission' => 'tender.view'],
            ['label' => 'Administrasi Kontrak', 'href' => '/admin/contracts', 'icon' => 'document', 'permission' => 'contract.view'],
        ]],
        ['label' => '🏗️ Proyek', 'items' => [
            ['label' => 'Proyek, Gantt & Kurva-S', 'href' => '/admin/projects', 'icon' => 'cube', 'permission' => 'project.view'],
            ['label' => 'Field Operations', 'href' => '/admin/projects/field-ops', 'icon' => 'wrench', 'permission' => 'project.view', 'children' => [
                ['label' => 'Drilling Record & Bore Log', 'href' => '/admin/projects/field-ops#drilling'],
                ['label' => 'Delivery Beton (Slump)', 'href' => '/admin/projects/field-ops#concrete'],
                ['label' => 'Pile Testing', 'href' => '/admin/projects/field-ops#testing'],
            ]],
        ]],
        ['label' => '📦 Supply Chain', 'items' => [
            ['label' => 'Inventory & Gudang', 'href' => '/admin/inventory', 'icon' => 'archive', 'permission' => 'inventory.view', 'children' => [
                ['label' => 'Stok Kritis', 'href' => '/admin/inventory#stok-kritis'],
                ['label' => 'Stock Opname', 'href' => '/admin/inventory/opname'],
                ['label' => 'Rekomendasi Reorder', 'href' => '/admin/inventory/reorder'],
                ['label' => 'Permintaan Material Proyek', 'href' => '/admin/inventory/material-requests'],
                ['label' => 'Tools Check-out', 'href' => '/admin/tools'],
            ]],
            ['label' => 'Procurement', 'href' => '/admin/procurement', 'icon' => 'cart', 'permission' => 'procurement.view', 'children' => [
                ['label' => 'Vendor, PO & Receipt', 'href' => '/admin/procurement'],
                ['label' => 'RFQ & Perbandingan Harga', 'href' => '/admin/procurement/rfq'],
            ]],
        ]],
        ['label' => '🏭 Workshop & Equipment', 'items' => [
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
        ['label' => '💰 Keuangan', 'items' => [
            ['label' => 'Ikhtisar Keuangan', 'href' => '/admin/finance/overview', 'icon' => 'pie', 'permission' => 'finance.view'],
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
            ['label' => 'Project Costing & EAC', 'href' => '/admin/project-costing', 'icon' => 'chart', 'permission' => 'finance.view'],
            ['label' => 'Fixed Asset & Depresiasi', 'href' => '/admin/fixed-assets', 'icon' => 'briefcase', 'permission' => 'finance.view'],
            ['label' => 'Procurement Posting', 'href' => '/admin/procurement-accounting', 'icon' => 'calculator', 'permission' => 'accounting.post'],
        ]],
        ['label' => '✅ Quality & HSE', 'items' => [
            ['label' => 'Risiko, NCR & Audit Mutu', 'href' => '/admin/qms', 'icon' => 'shield', 'permission' => 'qms.view'],
            ['label' => 'HSE, JSA & Incident', 'href' => '/admin/hse', 'icon' => 'triangle-alert', 'permission' => 'hse.view'],
        ]],
        ['label' => '📄 Dokumen & Approval', 'items' => [
            ['label' => 'Document Control', 'href' => '/admin/documents', 'icon' => 'document', 'permission' => 'document.view'],
            ['label' => 'Approval Center', 'href' => '/admin/approvals', 'icon' => 'check', 'permission' => 'approval.view'],
            ['label' => 'Digital Signing', 'href' => '/admin/signatures', 'icon' => 'pen', 'permission' => 'signature.view'],
            ['label' => 'Audit Trail', 'href' => '/admin/audit', 'icon' => 'search', 'permission' => 'audit.view'],
        ]],
        ['label' => '📊 Laporan', 'items' => [
            ['label' => 'Bisnis & Tender', 'href' => '/admin/reports/executive', 'icon' => 'chart', 'permission' => 'report.view'],
            ['label' => 'Keuangan', 'href' => '/admin/reports/finance', 'icon' => 'calculator', 'permission' => 'report.view'],
            ['label' => 'Operasional', 'href' => '/admin/reports/operations', 'icon' => 'dashboard', 'permission' => 'report.view'],
            ['label' => 'Manufaktur', 'href' => '/admin/reports/manufacturing', 'icon' => 'cog', 'permission' => 'report.view'],
            ['label' => 'AR/AP Aging', 'href' => '/admin/reports/aging', 'icon' => 'clock', 'permission' => 'report.view'],
        ]],
        ['label' => '⚙️ Pengaturan', 'items' => [
            ['label' => 'Perusahaan & Role', 'href' => '/admin/organization', 'icon' => 'building', 'permission' => 'organization.view', 'children' => [
                ['label' => 'Perusahaan & Cabang', 'href' => '/admin/organization'],
                ['label' => 'Role & Permission', 'href' => '/admin/organization/roles'],
            ]],
            ['label' => 'Pengaturan Perusahaan', 'href' => '/admin/settings', 'icon' => 'cog'],
            ['label' => 'Notifikasi', 'href' => '/admin/notifications', 'icon' => 'bell'],
        ]],
    ],
];
