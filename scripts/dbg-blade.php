<?php
require 'D:/project laravel/grahapondasi/vendor/autoload.php';
$app = require 'D:/project laravel/grahapondasi/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
// compile semua view dan lapangkan yang error
$views = [
    'finance.index','finance.mappings','finance.periods','finance.accounts',
    'inventory.lot-trace','inventory.material-requests','inventory.opname','inventory.tools',
    'manufacturing.costing','manufacturing.nonconforming','manufacturing.quality',
    'procurement.accounting','taxes.index','organization.roles','organization.index',
];
foreach ($views as $view) {
    try { view($view)->render(); echo "OK $view", PHP_EOL; }
    catch (\Throwable $e) { echo "FAIL $view :: ", substr($e->getMessage(), 0, 120), PHP_EOL; }
}
