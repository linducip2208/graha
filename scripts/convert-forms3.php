<?php

/** One-shot batch 3: Draft PO form -> x-ui.field/form-section (byte-exact replace). */
$path = 'resources/views/procurement/index.blade.php';
$c = file_get_contents($path);
$s = strpos($c, '<form method="post" action="/admin/procurement/orders"');
if ($s === false) {
    exit("PO form tidak ketemu\n");
}
$e = strpos($c, '</form>', $s) + 8;

$new = <<<'HTML'
<form method="post" action="/admin/procurement/orders" class="rounded-[var(--radius-card)] border bg-white p-6 shadow-[var(--shadow-card)]">@csrf
<x-ui.form-section title="Draft Purchase Order" description="PO draft -> submit approval -> activate sebelum bisa diterima.">
<div class="grid gap-4 sm:grid-cols-2"><x-ui.field label="Nomor PO" name="number" hint="Unik per perusahaan" required><input name="number" placeholder="mis. PO-2026-001" required class="w-full p-3"></x-ui.field><x-ui.field label="Tanggal PO" name="order_date" required><input type="date" name="order_date" value="{{ today()->toDateString() }}" required class="w-full p-3"></x-ui.field><x-ui.field label="Vendor" name="vendor_id" hint="Hanya vendor approved" required><select name="vendor_id" required class="w-full p-3"><option value="">Pilih vendor</option>@foreach($vendors as $vendor)<option value="{{ $vendor->id }}">{{ $vendor->code }} · {{ $vendor->name }}</option>@endforeach</select></x-ui.field><x-ui.field label="Item" name="item_id" required><select name="item_id" required class="w-full p-3"><option value="">Pilih item</option>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->sku }} · {{ $item->name }}</option>@endforeach</select></x-ui.field><div class="grid grid-cols-3 gap-3"><x-ui.field label="Qty" name="quantity" required><input type="number" step=".0001" name="quantity" required placeholder="0.0000" class="w-full p-3"></x-ui.field><x-ui.field label="Harga" name="unit_price" required><input type="number" step=".01" name="unit_price" required placeholder="0" class="w-full p-3"></x-ui.field><x-ui.field label="Kurs" name="currency" required><input name="currency" value="IDR" required class="w-full p-3"></x-ui.field></div></div>
<div class="mt-4"><button class="rounded-xl bg-[var(--brand-primary)] p-3 font-bold text-white">Buat draft PO</button></div>
</x-ui.form-section>
</form>
HTML;

$c = substr($c, 0, $s).$new.substr($c, $e);
file_put_contents($path, $c);
echo "PO form converted\n";
