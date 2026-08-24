<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\CompanySetting;
use App\Models\StoredFile;

/**
 * Aturan evidence minimum per company (ADR-052). Default OFF — backward
 * compatible; project existing tidak berubah sampai fitur diaktifkan.
 */
class EvidenceRequirementService
{
    public const RULES = [
        'setting_out' => 'min_photo_setting_out',
        'bottom_cleaning' => 'min_photo_bottom_cleaning',
        'cage' => 'min_photo_cage',
        'concrete' => 'min_photo_concrete_delivery',
        'slump' => 'min_photo_slump',
        'completion' => 'min_photo_completion',
    ];

    public function enabled(int $companyId): bool
    {
        return CompanySetting::val($companyId, 'evidence_rules_enabled') === '1';
    }

    /** @return array<string, array{required: int, actual: int}> kategori yang kurang. */
    public function missing(BoredPile $pile): array
    {
        if (! $this->enabled($pile->project->company_id)) {
            return [];
        }
        $counts = StoredFile::where('bored_pile_id', $pile->id)
            ->where('category', 'photo')
            ->whereNull('original_file_id')
            ->get()
            ->groupBy('sub_category')
            ->map(fn ($group) => $group->count());

        $missing = [];
        foreach (self::RULES as $category => $settingKey) {
            $required = max(0, (int) CompanySetting::val($pile->project->company_id, $settingKey));
            if ($required === 0) {
                continue;
            }
            $actual = (int) ($counts[$category] ?? 0);
            if ($actual < $required) {
                $missing[$category] = ['required' => $required, 'actual' => $actual];
            }
        }

        return $missing;
    }
}
