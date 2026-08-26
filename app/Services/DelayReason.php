<?php

namespace App\Services;

/**
 * Registry alasan delay terstruktur (ADR-076) — satu sumber kebenaran untuk
 * downtime equipment dan constraint lapangan. TIDAK membuat sistem downtime
 * baru; kolom delay_reason pada tabel existing menunjuk ke registry ini.
 */
class DelayReason
{
    public const TYPES = [
        'rig_breakdown',
        'waiting_concrete',
        'waiting_cage',
        'waiting_inspector',
        'client_hold',
        'weather',
        'access',
        'soil_condition',
        'slurry',
        'drawing',
        'material',
        'manpower',
        'permit',
        'safety',
        'other',
    ];

    public const LABELS = [
        'rig_breakdown' => 'Rig breakdown',
        'waiting_concrete' => 'Menunggu beton',
        'waiting_cage' => 'Menunggu cage',
        'waiting_inspector' => 'Menunggu inspektur',
        'client_hold' => 'Hold klien',
        'weather' => 'Cuaca',
        'access' => 'Akses lokasi',
        'soil_condition' => 'Kondisi tanah',
        'slurry' => 'Slurry',
        'drawing' => 'Gambar kerja',
        'material' => 'Material',
        'manpower' => 'Tenaga kerja',
        'permit' => 'Perizinan',
        'safety' => 'Keamanan/K3',
        'other' => 'Lainnya',
    ];

    public static function isValid(string $type): bool
    {
        return in_array($type, self::TYPES, true);
    }

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? str($type)->replace('_', ' ')->title();
    }
}
