<?php

namespace App\Console\Commands;

use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Console\Command;

class VerifyInventoryIntegrity extends Command
{
    protected $signature = 'inventory:verify {--company= : Batasi company ID}';

    protected $description = 'Read-only integrity check saldo, ledger, negative policy, dan condition buckets';

    public function handle(): int
    {
        $anomalies = [];
        StockBalance::with('item')->when($this->option('company'), fn ($q, $id) => $q->where('company_id', $id))->chunkById(500, function ($balances) use (&$anomalies) {
            foreach ($balances as $balance) {
                $movements = StockMovement::where('company_id', $balance->company_id)->where('item_id', $balance->item_id)->where('warehouse_id', $balance->warehouse_id)
                    ->where('warehouse_bin_id', $balance->warehouse_bin_id)->where('lot_number', $balance->lot_number)
                    ->orderBy('id')->get(['quantity', 'balance_after']);
                $ledger = $movements->reduce(fn ($sum, $row) => bcadd($sum, (string) $row->quantity, 4), '0');
                if (bccomp($ledger, (string) $balance->quantity, 4) !== 0) {
                    $anomalies[] = [$balance->id, 'BALANCE_MISMATCH', $ledger, $balance->quantity];
                }
                if (! $balance->item?->allow_negative && bccomp((string) $balance->quantity, '0', 4) < 0) {
                    $anomalies[] = [$balance->id, 'NEGATIVE_NOT_ALLOWED', '-', $balance->quantity];
                }
                $condition = bcadd(bcadd((string) $balance->reserved_quantity, (string) $balance->damaged_quantity, 4), (string) $balance->obsolete_quantity, 4);
                if (bccomp($condition, (string) $balance->quantity, 4) > 0) {
                    $anomalies[] = [$balance->id, 'BUCKET_EXCEEDS_PHYSICAL', $condition, $balance->quantity];
                }
                if ($movements->isNotEmpty() && bccomp((string) $movements->last()->balance_after, (string) $balance->quantity, 4) !== 0) {
                    $anomalies[] = [$balance->id, 'LAST_LEDGER_BALANCE_MISMATCH', $movements->last()->balance_after, $balance->quantity];
                }
            }
        });
        $this->table(['Balance ID', 'Anomaly', 'Ledger/Condition', 'Stored'], $anomalies);
        $this->line($anomalies === [] ? 'PASS - tidak ada anomali inventory.' : 'FAIL - '.count($anomalies).' anomali ditemukan; tidak ada auto-fix.');

        return $anomalies === [] ? self::SUCCESS : self::FAILURE;
    }
}
