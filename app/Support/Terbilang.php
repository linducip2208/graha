<?php

namespace App\Support;

class Terbilang
{
    private const SATUAN = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    public static function rupiah(string $amount): string
    {
        $words = self::convert((int) floor((float) $amount));
        $sen = bcmul(bcsub($amount, (string) floor((float) $amount), 2), '100', 0);

        return trim(ucfirst($words).' rupiah'.(bccomp($sen, '0', 0) === 1 ? ' '.self::convert((int) $sen).' sen' : ''));
    }

    private static function convert(int $number): string
    {
        if ($number < 12) {
            return self::SATUAN[$number];
        }
        if ($number < 20) {
            return self::convert($number - 10).' belas';
        }
        if ($number < 100) {
            return self::convert(intdiv($number, 10)).' puluh '.self::convert($number % 10);
        }
        if ($number < 200) {
            return 'seratus '.self::convert($number - 100);
        }
        if ($number < 1000) {
            return self::convert(intdiv($number, 100)).' ratus '.self::convert($number % 100);
        }
        if ($number < 2000) {
            return 'seribu '.self::convert($number - 1000);
        }
        if ($number < 1000000) {
            return self::convert(intdiv($number, 1000)).' ribu '.self::convert($number % 1000);
        }
        if ($number < 1000000000) {
            return self::convert(intdiv($number, 1000000)).' juta '.self::convert($number % 1000000);
        }
        if ($number < 1000000000000) {
            return self::convert(intdiv($number, 1000000000)).' miliar '.self::convert($number % 1000000000);
        }

        return self::convert(intdiv($number, 1000000000000)).' triliun '.self::convert($number % 1000000000000);
    }
}
