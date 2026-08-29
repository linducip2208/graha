<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Project;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private AuditTrail $audit) {}

    public function post(array $dimension, string $type, string $quantity, string $idempotencyKey, User $actor, array $reference, string $unitCost = '0'): StockMovement
    {
        return DB::transaction(function () use ($dimension, $type, $quantity, $idempotencyKey, $actor, $reference, $unitCost) {
            throw_if(bccomp($quantity, '0', 4) <= 0, ValidationException::withMessages(['quantity' => 'Kuantitas harus lebih dari nol.']));
            $item = Item::where('company_id', $dimension['company_id'])->findOrFail($dimension['item_id']);
            $warehouse = Warehouse::where('company_id', $item->company_id)->findOrFail($dimension['warehouse_id']);
            $bin = ! empty($dimension['warehouse_bin_id'])
                ? WarehouseBin::where('warehouse_id', $warehouse->id)->findOrFail($dimension['warehouse_bin_id'])
                : null;
            if (! empty($dimension['project_id'])) {
                Project::where('company_id', $item->company_id)->findOrFail($dimension['project_id']);
            }
            $lot = (string) ($dimension['lot_number'] ?? '');
            $keys = ['company_id' => $dimension['company_id'], 'item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'warehouse_bin_id' => $bin?->id, 'lot_number' => $lot];
            $fingerprint = $this->fingerprint($keys, $type, $quantity, $unitCost, $reference);
            if ($existing = StockMovement::where('company_id', $dimension['company_id'])->where('idempotency_key', $idempotencyKey)->first()) {
                $existingFingerprint = $existing->payload_fingerprint ?: $this->fingerprint(
                    ['company_id' => $existing->company_id, 'item_id' => $existing->item_id, 'warehouse_id' => $existing->warehouse_id, 'warehouse_bin_id' => $existing->warehouse_bin_id, 'lot_number' => $existing->lot_number],
                    $existing->movement_type, ltrim((string) $existing->quantity, '-'), (string) $existing->unit_cost,
                    ['type' => $existing->reference_type, 'id' => $existing->reference_id]
                );
                throw_if(! hash_equals($existingFingerprint, $fingerprint), ValidationException::withMessages(['idempotency_key' => 'Kunci idempotensi sudah dipakai untuk payload transaksi berbeda.']));

                return $existing;
            }
            $dimensionKey = implode('|', [$keys['company_id'], $keys['item_id'], $keys['warehouse_id'], $keys['warehouse_bin_id'] ?? '0', $lot]);
            StockBalance::firstOrCreate($keys, ['quantity' => '0', 'reserved_quantity' => '0']);
            $balance = StockBalance::where($keys)->lockForUpdate()->firstOrFail();
            $incoming = in_array($type, ['receipt', 'return_in', 'adjustment_in'], true);
            $fifo = [];
            if (! $incoming && bccomp($unitCost, '0', 4) === 0) {
                $layers = DB::table('inventory_cost_layers')->where($keys)->where('remaining_quantity', '>', 0)->lockForUpdate()->orderBy('id')->get();
                $remaining = $quantity;
                $amount = '0';
                foreach ($layers as $layer) {
                    if (bccomp($remaining, '0', 4) <= 0) {
                        break;
                    }
                    $taken = bccomp((string) $layer->remaining_quantity, $remaining, 4) >= 0 ? $remaining : (string) $layer->remaining_quantity;
                    $fifo[] = [$layer, $taken];
                    $amount = bcadd($amount, bcmul($taken, (string) $layer->unit_cost, 4), 4);
                    $remaining = bcsub($remaining, $taken, 4);
                }
                if (bccomp($remaining, '0', 4) === 0) {
                    $unitCost = bcdiv($amount, $quantity, 4);
                }
            }
            // Legacy movements may predate FIFO layers. Preserve their
            // costing while making the condition visible to reconciliation.
            if (! $incoming && bccomp($unitCost, '0', 4) === 0) {
                $unitCost = (string) (StockMovement::where($keys)->whereIn('movement_type', ['receipt', 'return_in', 'adjustment_in'])->where('unit_cost', '>', 0)->latest('id')->value('unit_cost') ?? '0');
            }
            $signed = in_array($type, ['receipt', 'return_in', 'adjustment_in'], true) ? $quantity : bcsub('0', $quantity, 4);
            $after = bcadd((string) $balance->quantity, $signed, 4);
            throw_if(bccomp($after, '0', 4) < 0 && ! $item->allow_negative, ValidationException::withMessages(['stock' => 'Stok tidak mencukupi dan negative stock dilarang.']));
            $balance->update(['quantity' => $after]);

            $movement = StockMovement::create([...$keys, 'dimension_key' => $dimensionKey, 'payload_fingerprint' => $fingerprint, 'transaction_id' => (string) Str::uuid(), 'movement_type' => $type, 'quantity' => $signed, 'balance_after' => $after, 'unit_cost' => $unitCost, 'reference_type' => $reference['type'], 'reference_id' => (string) $reference['id'], 'idempotency_key' => $idempotencyKey, 'reason' => $reference['reason'] ?? null, 'project_id' => $dimension['project_id'] ?? null, 'bored_pile_id' => $dimension['bored_pile_id'] ?? null, 'posted_by' => $actor->id, 'posted_at' => now()]);
            if ($incoming) {
                DB::table('inventory_cost_layers')->insert(['company_id' => $keys['company_id'], 'item_id' => $keys['item_id'], 'warehouse_id' => $keys['warehouse_id'], 'warehouse_bin_id' => $keys['warehouse_bin_id'], 'lot_number' => $lot, 'source_movement_id' => $movement->id, 'original_quantity' => $quantity, 'remaining_quantity' => $quantity, 'unit_cost' => $unitCost, 'created_at' => now(), 'updated_at' => now()]);
            } else {
                foreach ($fifo as [$layer, $taken]) {
                    DB::table('inventory_cost_layers')->where('id', $layer->id)->update(['remaining_quantity' => bcsub((string) $layer->remaining_quantity, $taken, 4), 'updated_at' => now()]);
                    DB::table('inventory_cost_allocations')->insert(['stock_movement_id' => $movement->id, 'inventory_cost_layer_id' => $layer->id, 'quantity' => $taken, 'unit_cost' => $layer->unit_cost, 'amount' => bcmul($taken, (string) $layer->unit_cost, 4), 'created_at' => now(), 'updated_at' => now()]);
                }
            }

            return $movement;
        }, 3);
    }

    private function fingerprint(array $keys, string $type, string $quantity, string $unitCost, array $reference): string
    {
        return hash('sha256', json_encode([
            'keys' => $keys, 'type' => $type,
            'quantity' => bcadd((string) $quantity, '0', 4),
            'unit_cost' => bcadd((string) $unitCost, '0', 4),
            'reference' => ['type' => $reference['type'] ?? null, 'id' => (string) ($reference['id'] ?? ''), 'reason' => $reference['reason'] ?? null],
        ], JSON_THROW_ON_ERROR));
    }

    public function transfer(array $from, array $to, string $quantity, string $key, User $actor): array
    {
        return DB::transaction(function () use ($from, $to, $quantity, $key, $actor) {
            $reference = (string) Str::uuid();
            $out = $this->post($from, 'transfer_out', $quantity, $key.':out', $actor, ['type' => 'warehouse_transfer', 'id' => $reference]);
            $in = $this->post($to, 'receipt', $quantity, $key.':in', $actor, ['type' => 'warehouse_transfer', 'id' => $reference], (string) $out->unit_cost);

            return [$out, $in];
        }, 3);
    }

    /** Pindahkan qty available ke bucket kondisi (damaged/obsolete); tidak mengubah total fisik. */
    public function flagCondition(StockBalance $balance, string $bucket, string $quantity, User $actor): StockBalance
    {
        return DB::transaction(function () use ($balance, $bucket, $quantity, $actor) {
            throw_unless(in_array($bucket, ['damaged', 'obsolete'], true), ValidationException::withMessages(['bucket' => 'Bucket kondisi harus damaged atau obsolete.']));
            throw_if(bccomp($quantity, '0', 4) <= 0, ValidationException::withMessages(['quantity' => 'Kuantitas harus lebih dari nol.']));
            $balance = StockBalance::lockForUpdate()->findOrFail($balance->id);
            $available = bcsub(bcsub((string) $balance->quantity, (string) $balance->reserved_quantity, 4), bcadd((string) $balance->damaged_quantity, (string) $balance->obsolete_quantity, 4), 4);
            throw_if(bccomp($quantity, $available, 4) === 1, ValidationException::withMessages(['quantity' => "Qty melebihi stok available ({$available})."]));
            $balance->update([$bucket.'_quantity' => bcadd((string) $balance->{$bucket.'_quantity'}, $quantity, 4)]);
            $this->audit->record((int) $balance->company_id, $actor->id, 'inventory.condition_flagged', $balance);

            return $balance->refresh();
        }, 3);
    }

    /** Kembalikan qty dari bucket kondisi ke available (perbaikan / salah tandai). */
    public function restoreCondition(StockBalance $balance, string $bucket, string $quantity, User $actor): StockBalance
    {
        return DB::transaction(function () use ($balance, $bucket, $quantity, $actor) {
            throw_unless(in_array($bucket, ['damaged', 'obsolete'], true), ValidationException::withMessages(['bucket' => 'Bucket kondisi harus damaged atau obsolete.']));
            throw_if(bccomp($quantity, '0', 4) <= 0, ValidationException::withMessages(['quantity' => 'Kuantitas harus lebih dari nol.']));
            $balance = StockBalance::lockForUpdate()->findOrFail($balance->id);
            throw_if(bccomp($quantity, (string) $balance->{$bucket.'_quantity'}, 4) === 1, ValidationException::withMessages(['quantity' => "Qty melebihi isi bucket {$bucket} ({$balance->{$bucket.'_quantity'}})."]));
            $balance->update([$bucket.'_quantity' => bcsub((string) $balance->{$bucket.'_quantity'}, $quantity, 4)]);
            $this->audit->record((int) $balance->company_id, $actor->id, 'inventory.condition_restored', $balance);

            return $balance->refresh();
        }, 3);
    }

    /** Sesuaikan qty in-transit (delta positif/negatif), hasil tidak boleh negatif. */
    public function adjustInTransit(StockBalance $balance, string $delta, User $actor): StockBalance
    {
        return DB::transaction(function () use ($balance, $delta, $actor) {
            $balance = StockBalance::lockForUpdate()->findOrFail($balance->id);
            $after = bcadd((string) $balance->in_transit_quantity, $delta, 4);
            throw_if(bccomp($after, '0', 4) < 0, ValidationException::withMessages(['delta' => 'In-transit tidak boleh negatif.']));
            $balance->update(['in_transit_quantity' => $after]);
            $this->audit->record((int) $balance->company_id, $actor->id, 'inventory.in_transit_adjusted', $balance);

            return $balance->refresh();
        }, 3);
    }
}
