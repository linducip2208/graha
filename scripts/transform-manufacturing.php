<?php

// Surgical transform: manufacturing/index — 3 create form -> drawer + tombol header.
$path = 'D:/project laravel/grahapondasi/resources/views/manufacturing/index.blade.php';
$raw = file_get_contents($path);
$gridStart = strpos($raw, '<div class="mt-7 grid gap-5 lg:grid-cols-3">');
if ($gridStart === false) {
    fwrite(STDERR, "GRID NOT FOUND\n");
    exit(1);
}
$k = $gridStart;
$ends = [];
while (($k = strpos($raw, '</form>', $k + 1)) !== false && count($ends) < 3) {
    $ends[] = $k;
}
$gridEnd = strpos($raw, '</div>', $ends[2]) + 6;
$gridBlock = substr($raw, $gridStart, $gridEnd - $gridStart);
preg_match_all('/<form method="post"[\s\S]*?<\/form>/', $gridBlock, $m);
if (count($m[0]) !== 3) {
    fwrite(STDERR, 'FORM COUNT: '.count($m[0])."\n");
    exit(1);
}
[$bomForm, $orderForm, $mapForm] = $m[0];
$clean = fn (string $f) => preg_replace('/^<form method="post"([^>]*) class="[^"]*"/', '<form method="post"$1 class="grid gap-4"', $f);
$bomForm = $clean($bomForm);
$orderForm = $clean($orderForm);
$mapForm = $clean($mapForm);
$raw = substr($raw, 0, $gridStart).substr($raw, $gridEnd);

$perm = "\$permOk = auth()->user()->hasPermission('manufacturing.manage',app(\\App\\Support\\Tenancy\\CurrentCompany::class)->id());";
$btns = <<<BLADE
@if(auth()->user()->hasPermission('manufacturing.manage',app(\App\Support\Tenancy\CurrentCompany::class)->id()))<div class="mt-5 flex flex-wrap gap-2 no-print"><button type="button" class="inline-flex min-h-[42px] items-center gap-2 rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-open="bom-create-drawer"><x-ui.icon name="plus" class="h-4 w-4" />BOM</button><button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="order-create-drawer"><x-ui.icon name="plus" class="h-4 w-4" />Production Order</button><button type="button" class="inline-flex min-h-[42px] items-center gap-2 rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-open="mapping-drawer"><x-ui.icon name="calculator" class="h-4 w-4" />Mapping Jurnal</button></div>@endif
BLADE;
$anchor = "@if(session('status'))";
$raw = substr_replace($raw, $btns."\n", strpos($raw, $anchor), 0);

$drawers = <<<BLADE

@if(auth()->user()->hasPermission('manufacturing.manage',app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<x-ui.drawer id="bom-create-drawer" title="Buat BOM" description="Buat header BOM terlebih dahulu, lalu tambahkan material pada daftar BOM di bawah.">
{$bomForm}
</x-ui.drawer>
<x-ui.drawer id="order-create-drawer" title="Buat Perintah Produksi">
{$orderForm}
</x-ui.drawer>
<x-ui.drawer id="mapping-drawer" title="Akun Jurnal Produksi">
{$mapForm}
</x-ui.drawer>
@endif
BLADE;
$tail = '</div></x-layouts.app>';
$ti = strrpos($raw, $tail);
$raw = substr($raw, 0, $ti).$drawsersPlaceholder = $drawers."\n".substr($raw, $ti);
file_put_contents($path, $raw);
echo "OK manufacturing transformed\n";
