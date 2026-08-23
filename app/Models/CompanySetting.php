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
        'default_retention_percent' => '5',
        'default_ppn_percent' => '11',
        'default_overbreak_tolerance_percent' => '8',
        'require_pile_test_pass' => '0',
        'require_cage_passed' => '0',
        'steel_variance_tolerance_percent' => '5',
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
