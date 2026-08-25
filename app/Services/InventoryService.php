<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private AuditTrail $audit) {}

    public function post(array $dimension, string $type, string $quantity, string $idempotencyKey, User $actor, array $reference, string $unitCost = '0'): StockMovement
    {
        return DB::transaction(function () use ($dimension, $type, $quantity, $idempotencyKey, $actor, $reference, $unitCost) {
            if ($existing = StockMovement::where('company_id', $dimension['company_id'])->where('idempotency_key', $idempotencyKey)->first()) {
                return $existing;
            }
            throw_if(bccomp($quantity, '0', 4) <= 0, ValidationException::withMessages(['quantity' => 'Kuantitas harus lebih dari nol.']));
            $item = Item::where('company_id', $dimension['company_id'])->findOrFail($dimension['item_id']);
            $keys = ['company_id' => $dimension['company_id'], 'item_id' => $item->id, 'warehouse_id' => $dimension['warehouse_id'], 'warehouse_bin_id' => $dimension['warehouse_bin_id'], 'lot_number' => $dimension['lot_number'] ?? ''];
            StockBalance::firstOrCreate($keys, ['quantity' => '0', 'reserved_quantity' => '0']);
            $balance = StockBalance::where($keys)->lockForUpdate()->firstOrFail();
            if (! in_array($type, ['receipt', 'return_in', 'adjustment_in'], true) && bccomp($unitCost, '0', 4) === 0) {
                $unitCost = (string) (StockMovement::where($keys)->whereIn('movement_type', ['receipt', 'return_in', 'adjustment_in'])->where('unit_cost', '>', 0)->latest('id')->value('unit_cost') ?? '0');
            }
            $signed = in_array($type, ['receipt', 'return_in', 'adjustment_in'], true) ? $quantity : bcsub('0', $quantity, 4);
            $after = bcadd((string) $balance->quantity, $signed, 4);
            throw_if(bccomp($after, '0', 4) < 0 && ! $item->allow_negative, ValidationException::withMessages(['stock' => 'Stok tidak mencukupi dan negative stock dilarang.']));
            $balance->update(['quantity' => $after]);

            return StockMovement::create([...$keys, 'transaction_id' => (string) Str::uuid(), 'movement_type' => $type, 'quantity' => $signed, 'balance_after' => $after, 'unit_cost' => $unitCost, 'reference_type' => $reference['type'], 'reference_id' => (string) $reference['id'], 'idempotency_key' => $idempotencyKey, 'reason' => $reference['reason'] ?? null, 'project_id' => $dimension['project_id'] ?? null, 'bored_pile_id' => $dimension['bored_pile_id'] ?? null, 'posted_by' => $actor->id, 'posted_at' => now()]);
        }, 3);
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
