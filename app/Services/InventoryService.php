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
}
