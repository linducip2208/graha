<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Kalkulator PPh 21 Pasal 17 progresif tahunan (ADR-070).
 * Alur: bruto setahun − biaya jabatan (5% maks Rp6jt/thn) − PTKP
 * (54jt + status + tanggungan) = PKP → tarif 5/15/25/30%.
 * Tanpa NPWP: PPh disuratkan +20%. Untuk estimasi/preview — final tetap
 * divalidasi oleh perpajakan (bukan pengganti e-Bupot).
 */
class Pph21Service
{
    private const PTKP_STATUS = [
        'TK0' => 4500000, 'TK1' => 4500000, 'TK2' => 4500000, 'TK3' => 4500000,
        'K0' => 4500000, 'K1' => 5400000, 'K2' => 6300000, 'K3' => 7200000,
    ];

    public function calculate(string $monthlyGross, string $ptkpStatus, bool $hasNpwp): array
    {
        $status = strtoupper($ptkpStatus);
        if (! isset(self::PTKP_STATUS[$status])) {
            throw new InvalidArgumentException("Status PTKP tidak valid: {$ptkpStatus} (gunakan TK0..TK3 / K0..K3).");
        }
        if (bccomp($monthlyGross, '0', 2) <= 0) {
            throw new InvalidArgumentException('Gaji bulanan harus positif.');
        }

        $annual = bcmul($monthlyGross, '12', 2);
        $biayaJabatan = $this->min(bcmul($annual, '0.05', 2), '6000000');
        $ptkp = bcadd('54000000', (string) self::PTKP_STATUS[$status], 2);
        $pkp = bcsub(bcsub($annual, $biayaJabatan, 2), $ptkp, 2);
        $tax = bccomp($pkp, '0', 2) === 1 ? $this->brackets($pkp) : '0';
        if (! $hasNpwp) {
            $tax = bcadd($tax, bcmul($tax, '0.20', 2), 2);
        }

        return ['annual_gross' => $annual, 'biaya_jabatan' => $biayaJabatan, 'ptkp' => $ptkp, 'pkp' => max('0', $pkp), 'annual_tax' => $tax, 'monthly_tax' => bcdiv($tax, '12', 2), 'npwp_surcharge' => ! $hasNpwp];
    }

    /** Lapisan PP 36/2008 (pre-TER): 5% ≤50jt; 15% 50–250jt; 25% 250–500jt; 30% >500jt. */
    private function brackets(string $pkp): string
    {
        $total = '0';
        $remaining = $pkp;
        foreach ([['50000000', '0.05'], ['200000000', '0.15'], ['250000000', '0.25'], [null, '0.30']] as [$width, $rate]) {
            if (bccomp($remaining, '0', 2) !== 1) {
                break;
            }
            $base = $width === null ? $remaining : $this->min($remaining, $width);
            $total = bcadd($total, bcmul($base, $rate, 2), 2);
            $remaining = bcsub($remaining, $base, 2);
        }

        return $total;
    }

    private function min(string $a, string $b): string
    {
        return bccomp($a, $b, 2) === -1 ? $a : $b;
    }
}
