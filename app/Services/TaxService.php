<?php

namespace App\Services;

use App\Models\TaxRate;
use Illuminate\Validation\ValidationException;

class TaxService
{
    public function compute(string $base, ?TaxRate $rate): string
    {
        if ($rate === null || bccomp($base, '0', 2) === 0) {
            return '0.00';
        }
        throw_if(bccomp($base, '0', 2) < 0, ValidationException::withMessages(['base' => 'Dasar pajak tidak boleh negatif.']));

        return bcdiv(bcmul($base, (string) $rate->rate_percent, 4), '100', 2);
    }

    public function resolve(int $companyId, ?int $taxRateId, string $kind): ?TaxRate
    {
        if ($taxRateId === null) {
            return null;
        }
        $rate = TaxRate::where('company_id', $companyId)->where('kind', $kind)->where('is_active', true)->find($taxRateId);
        throw_unless($rate, ValidationException::withMessages(['tax_rate' => "Tarif pajak ($kind) tidak ditemukan atau tidak aktif."]));

        return $rate;
    }
}
