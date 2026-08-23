<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fondasi multi-currency: konversi nilai asing ke IDR memakai kurs efektif
 * terakhir sebelum tanggal transaksi. Selisih kurs REALIZED dihitung di sini
 * dan diposting oleh CashBankService saat pelunasan kas dokumen asing
 * (ADR-040). Unrealized revaluation tidak dilakukan.
 */
class FxService
{
    public const BASE = 'IDR';

    public function rate(int $companyId, string $currency, string $date): string
    {
        $currency = strtoupper($currency);
        if ($currency === self::BASE) {
            return '1';
        }
        throw_unless(in_array($currency, ['USD', 'SGD', 'EUR', 'JPY', 'CNY', 'AUD'], true), ValidationException::withMessages(['currency' => "Mata uang {$currency} belum didukung."]));

        $rate = DB::table('fx_rates')
            ->where('company_id', $companyId)
            ->where('currency', $currency)
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->value('rate_to_idr');

        throw_unless($rate, ValidationException::withMessages(['currency' => "Kurs {$currency} per {$date} belum tersedia. Input kurs terlebih dahulu."]));

        return (string) $rate;
    }

    /** Simpan/ubah kurs; menimpa kurs pada tanggal yang sama. */
    public function putRate(int $companyId, string $currency, string $date, string $rate, ?string $source = null): void
    {
        throw_if(bccomp($rate, '0', 6) !== 1, ValidationException::withMessages(['rate' => 'Kurs harus positif.']));
        DB::table('fx_rates')->updateOrInsert(
            ['company_id' => $companyId, 'currency' => strtoupper($currency), 'effective_date' => $date],
            ['rate_to_idr' => $rate, 'source' => $source, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /** Nilai dalam IDR untuk agregasi laporan lintas mata uang. */
    public function toIdr(string $amount, string $currency, int $companyId, string $date): string
    {
        return bcmul($amount, $this->rate($companyId, $currency, $date), 2);
    }

    public function companyUsesForeignCurrency(Company $company): bool
    {
        return DB::table('fx_rates')->where('company_id', $company->id)->exists();
    }

    /**
     * Kebijakan ADR-040: hanya REALIZED difference — selisih antara nilai kas
     * penyelesaian (kurs hari settlement) dan nilai setara pada kurs saat
     * dokumen diposting. Dipakai CashBankService untuk posting jurnal
     * FX gain/loss via accounting mapping configurable.
     */
    public function realizedDifference(string $settledAmountForeign, string $rateAtDocument, string $rateAtSettlement): string
    {
        $atDocument = bcmul($settledAmountForeign, $rateAtDocument, 2);
        $atSettlement = bcmul($settledAmountForeign, $rateAtSettlement, 2);

        return bcsub($atSettlement, $atDocument, 2);
    }
}
