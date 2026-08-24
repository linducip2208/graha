<?php

/**
 * One-shot: konversi form grid utama ke x-ui.field / x-ui.form-section.
 * Pola: regex blok <form ...>...</form> per route target.
 */
$replacements = [
    'resources/views/inventory/index.blade.php' => [
        ['/admin/inventory/movements', <<<'HTML'
<form method="post" action="/admin/inventory/movements" class="rounded-[var(--radius-card)] border bg-white p-6 shadow-[var(--shadow-card)]">@csrf
<x-ui.form-section title="Posting Movement" description="Semua pergerakan masuk stock ledger immutable dengan FIFO cost.">
<div class="grid gap-4 sm:grid-cols-2"><x-ui.field label="Item" name="item_id" required><select name="item_id" required class="w-full p-3">@foreach($items as $i)<option value="{{ $i->id }}">{{ $i->sku }} — {{ $i->name }}</option>@endforeach</select></x-ui.field><x-ui.field label="Gudang / Bin" name="warehouse_bin_id" required><select name="warehouse_bin_id" required class="w-full p-3">@foreach($warehouses as $w)@foreach($w->bins as $b)<option value="{{ $b->id }}">{{ $w->code }}/{{ $b->code }}</option>@endforeach @endforeach</select></x-ui.field><x-ui.field label="Jenis movement" name="movement_type"><select name="movement_type" class="w-full p-3"><option value="receipt">Penerimaan</option><option value="issue">Pengeluaran</option><option value="return_in">Pengembalian</option><option value="adjustment_in">Adjustment +</option><option value="adjustment_out">Adjustment -</option></select></x-ui.field><div class="grid grid-cols-2 gap-3"><x-ui.field label="Qty" name="quantity" required><input name="quantity" type="number" step=".0001" placeholder="0.0000" required class="w-full p-3"></x-ui.field><x-ui.field label="Unit cost" name="unit_cost" hint="Wajib untuk receipt"><input name="unit_cost" type="number" step=".0001" placeholder="0" class="w-full p-3"></x-ui.field></div><x-ui.field label="Referensi unik" name="reference_id" hint="Kunci idempotensi — duplikat ditolak" required><input name="reference_id" placeholder="mis. GR-2026-001" required class="w-full p-3"></x-ui.field><x-ui.field label="Alasan" name="reason" hint="Opsional, masuk audit"><input name="reason" placeholder="mis. terima dari PO-12" class="w-full p-3"></x-ui.field></div>
<div class="mt-4"><button class="rounded-xl bg-[var(--brand-primary)] p-3 font-bold text-white">Post movement</button></div>
</x-ui.form-section>
</form>
HTML],
    ],
    'resources/views/operations/index.blade.php' => [
        ['/admin/equipment', <<<'HTML'
<form method="post" action="/admin/equipment" class="rounded-[var(--radius-card)] border bg-white p-6 shadow-[var(--shadow-card)]">@csrf
<x-ui.form-section title="Equipment Register" description="Alat berat masuk daftar kendali dengan hour meter awal dan target BBM.">
<div class="grid gap-4 sm:grid-cols-2"><x-ui.field label="Kode alat" name="code" required><input name="code" placeholder="mis. RIG-01" required class="w-full p-3"></x-ui.field><x-ui.field label="Nama alat" name="name" required><input name="name" placeholder="mis. Drilling Rig 1" required class="w-full p-3"></x-ui.field><x-ui.field label="Kepemilikan" name="ownership"><select name="ownership" class="w-full p-3"><option value="owned">Milik sendiri</option><option value="rented">Sewa</option></select></x-ui.field><x-ui.field label="Kategori" name="category" required><input name="category" placeholder="mis. drilling_rig" required class="w-full p-3"></x-ui.field><x-ui.field label="Hour meter awal" name="current_hour_meter" required><input type="number" step=".01" min="0" name="current_hour_meter" value="0" required class="w-full p-3"></x-ui.field><x-ui.field label="Target liter/jam" name="fuel_target_lph" hint="Anomali = LPH aktual > 120% target"><input type="number" step=".0001" min=".0001" name="fuel_target_lph" placeholder="opsional" class="w-full p-3"></x-ui.field></div>
<div class="mt-4"><button class="rounded-xl bg-[var(--brand-primary)] p-3 font-bold text-white">Tambah equipment</button></div>
</x-ui.form-section>
</form>
HTML],
    ],
];

foreach ($replacements as $path => $targets) {
    $c = file_get_contents($path);
    foreach ($targets as [$action, $new]) {
        $escaped = preg_quote($action, '/');
        $count = 0;
        $c = preg_replace('/<form method="post" action="'.$escaped.'"(?!.*action="'.$escaped.'").*?<\/form>/s', $new, $c, 1, $count);
        echo "$path [$action]: ".($count ? 'OK' : 'NOT FOUND')."\n";
    }
    file_put_contents($path, $c);
}
echo "Selesai.\n";
