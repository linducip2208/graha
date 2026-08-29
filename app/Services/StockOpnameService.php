<?php

namespace App\Services;

use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockOpnameService
{
    public function __construct(private InventoryService $inventory, private AuditTrail $audit) {}

    public function create(int $companyId, array $data, array $lines, User $actor): StockCount
    {
        return DB::transaction(function () use ($companyId, $data, $lines, $actor) {
            throw_if(StockCount::where('company_id', $companyId)->where('number', $data['number'])->exists(), ValidationException::withMessages(['number' => 'Nomor opname sudah dipakai.']));
            throw_if($lines === [], ValidationException::withMessages(['lines' => 'Minimal satu baris hitung.']));
            $count = StockCount::create([...$data, 'company_id' => $companyId, 'status' => 'draft', 'counted_by' => $actor->id]);
            foreach ($lines as $line) {
                throw_if(bccomp((string) $line['counted_quantity'], '0', 4) === -1, ValidationException::withMessages(['lines' => 'Hasil hitung tidak boleh negatif.']));
                $balance = StockBalance::where('company_id', $companyId)
                    ->where('item_id', $line['item_id'])
                    ->where('warehouse_id', $data['warehouse_id'])
                    ->when(array_key_exists('warehouse_bin_id', $line), fn ($q) => $line['warehouse_bin_id'] === null ? $q->whereNull('warehouse_bin_id') : $q->where('warehouse_bin_id', $line['warehouse_bin_id']))
                    ->when(array_key_exists('lot_number', $line), fn ($q) => $q->where('lot_number', (string) ($line['lot_number'] ?? '')))
                    ->first();
                if (! $balance) {
                    throw ValidationException::withMessages(['lines' => "Saldo sistem tidak ditemukan untuk item #{$line['item_id']} di gudang tersebut. Gunakan adjustment biasa untuk item baru."]);
                }
                StockCountLine::create([
                    'stock_count_id' => $count->id,
                    'item_id' => $line['item_id'],
                    'warehouse_bin_id' => $balance->warehouse_bin_id,
                    'lot_number' => $balance->lot_number ?? '',
                    'system_quantity' => (string) $balance->quantity,
                    'counted_quantity' => $line['counted_quantity'],
                ]);
            }
            $this->audit->record($companyId, $actor->id, 'inventory.opname_created', $count);

            return $count->load('lines');
        }, 3);
    }

    public function approve(StockCount $count, User $actor): StockCount
    {
        return DB::transaction(function () use ($count, $actor) {
            $count = StockCount::lockForUpdate()->findOrFail($count->id);
            if ($count->status === 'approved') {
                return $count;
            }
            throw_unless($count->status === 'draft', ValidationException::withMessages(['status' => 'Opname sudah final.']));
            throw_if((int) $count->counted_by === (int) $actor->id, ValidationException::withMessages(['approver' => 'Penghitung tidak boleh menyetujui sendiri.']));
            foreach ($count->lines as $line) {
                $live = StockBalance::where('company_id', $count->company_id)->where('item_id', $line->item_id)->where('warehouse_id', $count->warehouse_id)
                    ->when($line->warehouse_bin_id === null, fn ($q) => $q->whereNull('warehouse_bin_id'), fn ($q) => $q->where('warehouse_bin_id', $line->warehouse_bin_id))
                    ->where('lot_number', $line->lot_number ?? '')->lockForUpdate()->firstOrFail();
                throw_if(bccomp((string) $live->quantity, (string) $line->system_quantity, 4) !== 0, ValidationException::withMessages(['status' => 'Stok berubah setelah opname dibuat. Hitung ulang sebelum menyetujui.']));
                $variance = $line->variance();
                if (bccomp($variance, '0', 4) === 0) {
                    continue;
                }
                $type = bccomp($variance, '0', 4) === 1 ? 'adjustment_in' : 'adjustment_out';
                $quantity = bccomp($variance, '0', 4) === 1 ? $variance : bcmul($variance, '-1', 4);
                $this->inventory->post([
                    'company_id' => $count->company_id,
                    'item_id' => $line->item_id,
                    'warehouse_id' => $count->warehouse_id,
                    'warehouse_bin_id' => $line->warehouse_bin_id,
                    'lot_number' => $line->lot_number,
                ], $type, $quantity, "opname:{$count->id}:{$line->id}", $actor, ['type' => 'stock_opname', 'id' => $count->id]);
            }
            $count->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit->record($count->company_id, $actor->id, 'inventory.opname_approved', $count);

            return $count->refresh();
        }, 3);
    }
}
