<?php

/*
| App Launcher Registry (ADR-076) — metadata PRESENTASI saja.
|
| Label, href, permission, visibility, edition tetap berasal dari
| Navigation::groups() (effective navigation). Registry ini HANYA
| menambah cover + deskripsi + aksen per workspace key.
|
| Fallback cover: company custom -> default di sini -> gradient+icon.
*/

return [
    'covers_base' => 'images/apps',

    'workspaces' => [
        'beranda' => [
            'cover' => 'images/apps/reports.webp',
            'description' => 'Dashboard eksekutif, pekerjaan saya, dan ringkasan aktivitas.',
        ],
        'komersial' => [
            'cover' => 'images/apps/commercial.webp',
            'description' => 'Tender, pelanggan, kontrak dan administrasi perubahan.',
        ],
        'proyek' => [
            'cover' => 'images/apps/project.webp',
            'description' => 'Planning, WBS, field operations, progres dan biaya proyek.',
        ],
        'supply-chain' => [
            'cover' => 'images/apps/supply-chain.webp',
            'description' => 'Inventory, permintaan material, procurement, vendor dan penerimaan.',
        ],
        'operations' => [
            'cover' => 'images/apps/operations.webp',
            'description' => 'Manufaktur, cage & casing, equipment, BBM dan maintenance.',
        ],
        'keuangan' => [
            'cover' => 'images/apps/finance.webp',
            'description' => 'Accounting, billing, cash-bank, pajak, costing dan aset tetap.',
        ],
        'quality-hse' => [
            'cover' => 'images/apps/quality-hse.webp',
            'description' => 'NCR, audit mutu, risiko, incident dan keselamatan kerja.',
        ],
        'documents-approval' => [
            'cover' => 'images/apps/documents-approval.webp',
            'description' => 'Dokumen, approval berjenjang, tanda tangan digital dan audit.',
        ],
        'laporan' => [
            'cover' => 'images/apps/reports.webp',
            'description' => 'Laporan bisnis, keuangan, operasional dan manufaktur.',
        ],
        'pengaturan' => [
            'cover' => 'images/apps/settings.webp',
            'description' => 'Organisasi, role, pengaturan perusahaan dan notifikasi.',
        ],
    ],
];
