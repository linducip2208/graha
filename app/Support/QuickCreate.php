<?php

namespace App\Support;

use App\Models\User;

class QuickCreate
{
    public static function items(User $user, ?int $companyId): array
    {
        if ($companyId === null) {
            return [];
        }
        $can = fn (string $permission): bool => $user->hasPermission($permission, (int) $companyId);

        return collect([
            ['label' => 'Tender Baru', 'href' => '/admin/tenders#create-tender', 'icon' => 'flag', 'permission' => 'tender.manage'],
            ['label' => 'Zona Proyek', 'href' => '/admin/projects#create-zone', 'icon' => 'cube', 'permission' => 'project.manage'],
            ['label' => 'Titik Bored Pile', 'href' => '/admin/projects#create-pile', 'icon' => 'cube', 'permission' => 'project.manage'],
            ['label' => 'Vendor Baru', 'href' => '/admin/procurement#create-vendor', 'icon' => 'cart', 'permission' => 'procurement.manage'],
            ['label' => 'RFQ Baru', 'href' => '/admin/procurement/rfq#create-rfq', 'icon' => 'swap', 'permission' => 'procurement.manage'],
            ['label' => 'Perubahan Kontrak', 'href' => '/admin/contracts#create-change', 'icon' => 'document', 'permission' => 'contract.manage'],
            ['label' => 'Dokumen Baru', 'href' => '/admin/documents#upload', 'icon' => 'document', 'permission' => 'document.manage'],
            ['label' => 'NCR Baru', 'href' => '/admin/qms#create-ncr', 'icon' => 'shield', 'permission' => 'qms.manage'],
            ['label' => 'Laporan Incident HSE', 'href' => '/admin/hse#create-incident', 'icon' => 'triangle-alert', 'permission' => 'hse.manage'],
        ])->filter(fn (array $item) => $can($item['permission']))->map(fn (array $item) => collect($item)->except('permission')->all())->values()->all();
    }
}
