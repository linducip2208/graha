<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->string('dimension_key', 220)->nullable()->after('lot_number');
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('dimension_key', 220)->nullable()->after('lot_number');
            $table->string('payload_fingerprint', 64)->nullable()->after('idempotency_key');
        });
        Schema::table('journals', function (Blueprint $table) {
            $table->string('payload_fingerprint', 64)->nullable()->after('idempotency_key');
        });

        // Normalize and consolidate legacy NULL-bin duplicates before the
        // logical-dimension unique key becomes authoritative. Totals are
        // preserved; the merge record provides an operational audit trail.
        Schema::create('stock_balance_consolidations', function (Blueprint $table) {
            $table->id();
            $table->string('dimension_key', 220);
            $table->unsignedBigInteger('survivor_id');
            $table->json('merged_ids');
            $table->decimal('quantity_before', 20, 4);
            $table->decimal('quantity_after', 20, 4);
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('stock_balances')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $key = implode('|', [$row->company_id, $row->item_id, $row->warehouse_id, $row->warehouse_bin_id ?? '0', $row->lot_number ?? '']);
                DB::table('stock_balances')->where('id', $row->id)->update(['dimension_key' => $key]);
            }
        });

        $groups = DB::table('stock_balances')->select('dimension_key')->groupBy('dimension_key')->havingRaw('COUNT(*) > 1')->pluck('dimension_key');
        foreach ($groups as $key) {
            $rows = DB::table('stock_balances')->where('dimension_key', $key)->orderBy('id')->get();
            $survivor = $rows->first();
            $sum = static fn (string $field): string => $rows->reduce(fn (string $carry, object $row): string => bcadd($carry, (string) ($row->{$field} ?? '0'), 4), '0');
            $quantityBefore = $sum('quantity');
            DB::table('stock_balances')->where('id', $survivor->id)->update([
                'quantity' => $sum('quantity'),
                'reserved_quantity' => $sum('reserved_quantity'),
                'damaged_quantity' => $sum('damaged_quantity'),
                'obsolete_quantity' => $sum('obsolete_quantity'),
                'in_transit_quantity' => $sum('in_transit_quantity'),
            ]);
            DB::table('stock_balance_consolidations')->insert([
                'dimension_key' => $key,
                'survivor_id' => $survivor->id,
                'merged_ids' => json_encode($rows->pluck('id')->all(), JSON_THROW_ON_ERROR),
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityBefore,
            ]);
            DB::table('stock_balances')->whereIn('id', $rows->skip(1)->pluck('id'))->delete();
        }
        Schema::table('stock_balances', fn (Blueprint $table) => $table->unique('dimension_key', 'stock_balance_dimension_key_unique'));
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balance_consolidations');
        Schema::table('journals', fn (Blueprint $table) => $table->dropColumn('payload_fingerprint'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropColumn(['dimension_key', 'payload_fingerprint']));
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropUnique('stock_balance_dimension_key_unique');
            $table->dropColumn('dimension_key');
        });
    }
};
