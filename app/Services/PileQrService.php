<?php

namespace App\Services;

use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR Digital Pile Passport (ADR-049): QR menuju halaman aplikasi
 * /piles/{public_uuid} — BUKAN langsung ke object storage. Otorisasi tetap
 * berlaku setelah scan (login → redirect kembali → company check).
 */
class PileQrService
{
    /** Render SVG inline (tanpa request eksternal) untuk URL pile publik-safe. */
    public function svgForPileUrl(string $url): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'eccLevel' => 2, // EccLevel::Q — andal untuk label lapangan
            'svgAddXmlHeader' => false,
            'addQuietzone' => true,
        ]);

        return (string) (new QRCode($options))->render($url);
    }
}
