<?php

/**
 * One-shot: tambahkan 'key' stabil ke setiap navigation group pada
 * config/modules.php berdasarkan urutan (sekali jalan, hasil tertulis eksplisit).
 */
$path = __DIR__.'/../config/modules.php';
$keys = ['beranda', 'komersial', 'proyek', 'supply-chain', 'operations', 'keuangan', 'quality-hse', 'documents-approval', 'laporan', 'pengaturan'];
$src = file_get_contents($path);
if ($src === false) {
    exit("Gagal baca $path\n");
}
$count = 0;
$src = preg_replace_callback("/\['label' => ([^,]+), 'items' => \[/", function ($m) use ($keys, &$count) {
    $key = $keys[$count] ?? null;
    $count++;

    return $key !== null ? "['key' => '{$key}', 'label' => {$m[1]}, 'items' => [" : $m[0];
}, $src);
file_put_contents($path, $src);
echo "Grup diberi key: {$count}\n";
