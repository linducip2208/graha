<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CompanySetting extends Model
{
    public const DEFAULTS = [
        'company_address' => '',
        'company_phone' => '',
        'company_email' => '',
        'company_npwp' => '',
        'default_payment_term_days' => '30',
        'default_vendor_payment_term_days' => '30',
        'default_retention_percent' => '5',
        'default_ppn_percent' => '11',
        'default_overbreak_tolerance_percent' => '8',
        'require_pile_test_pass' => '0',
        'require_cage_passed' => '0',
        'require_itp_hold_points_passed' => '0',
        'require_cleaning_inspection' => '0',
        'require_jsa_active' => '0',
        'sediment_max_mm' => '',
        'evidence_rules_enabled' => '0',
        'min_photo_setting_out' => '1',
        'min_photo_bottom_cleaning' => '1',
        'min_photo_cage' => '1',
        'min_photo_concrete_delivery' => '1',
        'min_photo_slump' => '0',
        'min_photo_completion' => '1',
        'tremie_min_embedment_m' => '2.0',
        'tremie_max_embedment_m' => '',
        'tremie_log_enabled' => '0',
        'slurry_policy_enabled' => '0',
        'slurry_density_min' => '',
        'slurry_density_max' => '',
        'slurry_viscosity_min' => '',
        'slurry_viscosity_max' => '',
        'slurry_ph_min' => '',
        'slurry_ph_max' => '',
        'slurry_sand_content_max' => '',
        'concrete_max_gap_minutes' => '45',
        'survey_tolerance_m' => '0.05',
        'steel_variance_tolerance_percent' => '5',
        'pile_depth_tolerance_percent' => '5',
        'slump_min_cm' => '10',
        'slump_max_cm' => '20',
        'bid_weight_margin' => '40',
        'bid_weight_hps' => '25',
        'bid_weight_competition' => '20',
        'bid_weight_payment' => '15',
        'bid_threshold_recommended' => '70',
        'bid_threshold_no_bid' => '45',
        'project_health_yellow_percent' => '10',
        'project_health_red_percent' => '20',
        'invoice_footer_note' => 'Terima kasih atas kerja sama Anda.',
    ];

    public const LABELS = [
        'company_address' => 'Alamat kantor',
        'company_phone' => 'Telepon',
        'company_email' => 'Email',
        'company_npwp' => 'NPWP',
    ];

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function val(int $companyId, string $key): string
    {
        $row = DB::table('company_settings')->where('company_id', $companyId)->where('key', $key)->value('value');

        return $row ?? self::DEFAULTS[$key] ?? '';
    }

    public static function put(int $companyId, array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            DB::table('company_settings')->updateOrInsert(
                ['company_id' => $companyId, 'key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
