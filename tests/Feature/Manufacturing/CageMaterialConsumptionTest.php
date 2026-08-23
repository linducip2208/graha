<?php

namespace Tests\Feature\Manufacturing;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\Item;
use App\Models\NumberSequence;
use App\Models\ReinforcementCage;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Services\InventoryService;
use App\Services\ReinforcementCageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CageMaterialConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_consumption_posts_stock_issue_and_balanced_journal(): void
    {
        [$company, $user, $cage, $bin, $item] = $this->fixture();
        // Stok awal 1000 kg @15.000 agar unit cost FIFO terisi otomatis.
        app(InventoryService::class)->post(
            ['company_id' => $company->id, 'item_id' => $item->id, 'warehouse_id' => $bin->warehouse_id, 'warehouse_bin_id' => $bin->id],
            'receipt', '1000', 'seed-steel-in', $user, ['type' => 'opening', 'id' => '0'], '15000'
        );

        $movement = app(ReinforcementCageService::class)->consumeMaterial($cage, [
            'item_id' => $item->id, 'warehouse_id' => $bin->warehouse_id, 'warehouse_bin_id' => $bin->id,
            'quantity_kg' => '250', 'idempotency_key' => 'key-1',
        ], $user);

        // Ledger: 250 kg keluar, referensi ke cage.
        $this->assertSame(750.0, (float) DB::table('stock_balances')->where('item_id', $item->id)->value('quantity'));
        $this->assertSame('reinforcement_cage', $movement->reference_type);

        // Jurnal biaya material seimbang: 250 × 15.000 = 3.750.000.
        $totals = DB::table('journal_entries')->whereIn('journal_id', DB::table('journals')->where('source_id', (string) $movement->id)->select('id'))
            ->selectRaw('SUM(debit) d, SUM(credit) c')->first();
        $this->assertSame(3750000.0, (float) $totals->d);
        $this->assertSame((float) $totals->d, (float) $totals->c);

        // Idempoten: kunci sama mengembalikan movement yang sama tanpa stok baru.
        $again = app(ReinforcementCageService::class)->consumeMaterial($cage, [
            'item_id' => $item->id, 'warehouse_id' => $bin->warehouse_id, 'warehouse_bin_id' => $bin->id,
            'quantity_kg' => '250', 'idempotency_key' => 'key-1',
        ], $user);
        $this->assertSame($movement->id, $again->id);
        $this->assertSame(750.0, (float) DB::table('stock_balances')->where('item_id', $item->id)->value('quantity'));
    }

    public function test_rejects_zero_value_and_delivered_cage(): void
    {
        [$company, $user, $cage, $bin, $item] = $this->fixture();
        $service = app(ReinforcementCageService::class);
        $payload = fn () => ['item_id' => $item->id, 'warehouse_id' => $bin->warehouse_id, 'warehouse_bin_id' => $bin->id, 'quantity_kg' => '10', 'idempotency_key' => 'k-zero'];

        try {
            $service->consumeMaterial($cage, $payload(), $user);
            $this->fail('Tanpa stok dan unit cost, nilai jurnal nihil harus ditolak.');
        } catch (ValidationException) {
            $this->assertSame('0', (string) DB::table('stock_balances')->where('item_id', $item->id)->sum('quantity'));
        }

        $cage->update(['delivered_at' => now()]);
        try {
            $service->consumeMaterial($cage, [...$payload(), 'unit_cost' => '15000'], $user);
            $this->fail('Cage terkirim tidak boleh lagi dibebankan material.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        FiscalPeriod::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);

        $unit = Unit::create(['company_id' => $company->id, 'code' => 'KG', 'name' => 'Kilogram']);
        $item = Item::create(['company_id' => $company->id, 'unit_id' => $unit->id, 'sku' => 'BAJA-D16', 'name' => 'Besi Tulangan D16', 'category' => 'steel']);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'WH-UTAMA', 'name' => 'Gudang Utama']);
        $bin = WarehouseBin::create(['warehouse_id' => $warehouse->id, 'code' => 'B1', 'name' => 'Bin 1']);

        $expense = Account::create(['company_id' => $company->id, 'code' => 'BBAJA', 'name' => 'Biaya Material Baja', 'type' => 'expense', 'normal_balance' => 'debit']);
        $inventory = Account::create(['company_id' => $company->id, 'code' => 'PERSIB', 'name' => 'Persediaan Baja', 'type' => 'asset', 'normal_balance' => 'debit']);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'material_issue', 'entry_side' => 'debit', 'account_id' => $expense->id]);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'material_issue', 'entry_side' => 'credit', 'account_id' => $inventory->id]);

        $cage = ReinforcementCage::create(['company_id' => $company->id, 'number' => 'CAGE-MAT-1', 'diameter_mm' => '800', 'total_length_m' => '12', 'created_by' => $user->id]);

        return [$company, $user, $cage, $bin, $item];
    }
}
