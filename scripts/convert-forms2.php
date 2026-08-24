<?php

/**
 * One-shot batch 2: konversi form procurement (vendor + draft PO) dan RFQ
 * ke x-ui.field / x-ui.form-section.
 */
$jobs = [
    ['resources/views/procurement/index.blade.php', '/admin/procurement/vendors', <<<'HTML'
<form method="post" action="/admin/procurement/vendors" class="rounded-[var(--radius-card)] border bg-white p-6 shadow-[var(--shadow-card)]">@csrf
<x-ui.form-section title="Vendor Baru" description="Vendor langsung berstatus approved dan siap dipakai PO.">
<div class="grid gap-4 sm:grid-cols-2"><x-ui.field label="Kode vendor" name="code" required><input name="code" placeholder="mis. VND-001" required class="w-full p-3"></x-ui.field><x-ui.field label="Nama vendor" name="name" required><input name="name" placeholder="mis. PT Besi Baja Nusantara" required class="w-full p-3"></x-ui.field><x-ui.field label="NPWP" name="tax_number" hint="Untuk bukti potong PPh"><input name="tax_number" placeholder="00.000.000.0-000.000" class="w-full p-3"></x-ui.field><x-ui.field label="Email" name="email" hint="Tujuan kirim PO/dokumen"><input type="email" name="email" placeholder="vendor@perusahaan.co.id" class="w-full p-3"></x-ui.field></div>
<div class="mt-4"><button class="rounded-xl bg-slate-800 p-3 font-bold text-white">Tambah vendor</button></div>
</x-ui.form-section>
</form>
HTML],
];

foreach ($jobs as [$path, $action, $new]) {
    $c = file_get_contents($path);
    $escaped = preg_quote($action, '/');
    $count = 0;
    $c = preg_replace('/<form method="post" action="'.$escaped.'"(?!.*action="'.$escaped.'").*?<\/form>/s', $new, $c, 1, $count);
    file_put_contents($path, $c);
    echo "$path [$action]: ".($count ? 'OK' : 'NOT FOUND')."\n";
}

// Draft PO: form dengan banyak select — tangani terpisah karena panjang.
$c = file_get_contents('resources/views/procurement/index.blade.php');
$s = strpos($c, 'action="/admin/procurement/orders"');
if ($s !== false) {
    $e = strpos($c, '</form>', $s);
    $block = substr($c, $s - strlen('<form method="post" '), $e + 8 - ($s - strlen('<form method="post" ')));
    echo 'PO block length: '.strlen($block)."\n";
    echo substr($block, 0, 600)."\n";
}
