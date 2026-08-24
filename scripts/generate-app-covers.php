<?php

/**
 * Generator cover App Launcher (ADR-076): aset lokal deterministik, gaya
 * enterprise — gradient gelap halus + aksen per workspace + tekstur garis
 * diagonal tipis. Tidak ada hotlink internet; jalankan ulang via:
 *   php scripts/generate-app-covers.php
 */
$targets = [
    'commercial' => [14, 116, 144],
    'project' => [29, 78, 216],
    'supply-chain' => [180, 83, 9],
    'operations' => [109, 40, 217],
    'finance' => [4, 120, 87],
    'quality-hse' => [190, 18, 60],
    'documents-approval' => [71, 85, 105],
    'reports' => [124, 58, 237],
    'settings' => [51, 65, 85],
];

$width = 1200;
$height = 675;
$outDir = __DIR__.'/../public/images/apps';
if (! is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

foreach ($targets as $key => [$r, $g, $b]) {
    $img = imagecreatetruecolor($width, $height);

    // Gradient horizontal: navy pekat -> aksen workspace (smoothstep premium).
    $from = [11, 18, 32];
    imagesavealpha($img, true);
    for ($x = 0; $x < $width; $x++) {
        $t = $x / ($width - 1);
        $t = $t * $t * (3 - 2 * $t);
        $color = imagecolorallocate(
            $img,
            (int) round($from[0] + ($r - $from[0]) * $t),
            (int) round($from[1] + ($g - $from[1]) * $t),
            (int) round($from[2] + ($b - $from[2]) * $t)
        );
        imageline($img, $x, 0, $x, $height, $color);
    }

    // Glow lembut kanan-atas sebagai fokus visual badge ikon.
    $soft = imagecreatetruecolor($width, $height);
    imagefilledellipse($soft, (int) ($width * 0.82), (int) ($height * 0.22), 520, 420, imagecolorallocatealpha($soft, 255, 255, 255, 108));
    imagecopymerge($img, $soft, 0, 0, 0, 0, $width, $height, 20);
    imagedestroy($soft);

    // Tekstur garis diagonal sangat tipis (ritme enterprise, tidak ramai).
    $stripe = imagecolorallocatealpha($img, 255, 255, 255, 116);
    imagesetthickness($img, 2);
    for ($i = -$height; $i < $width; $i += 46) {
        imageline($img, $i, $height, $i + $height, 0, $stripe);
    }

    // Garis bawah tipis sebagai anchor komposisi.
    imagefilledrectangle($img, 0, $height - 6, $width, $height, imagecolorallocatealpha($img, 255, 255, 255, 92));

    imagewebp($img, "$outDir/$key.webp", 82);
    imagedestroy($img);
    echo "OK $key.webp\n";
}

echo "Selesai.\n";
