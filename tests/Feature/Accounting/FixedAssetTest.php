<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\NumberSequence;
use App\Models\User;
use App\Services\FixedAssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_straight_line_depreciation_is_balanced_and_idempotent(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $period = FiscalPeriod::create(['company_id' => $company->id, 'name' => 'Aug', 'starts_at' => '2026-08-01', 'ends_at' => '2026-08-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        $category = FixedAssetCategory::create(['company_id' => $company->id, 'code' => 'EQ', 'name' => 'Equipment', 'default_useful_life_months' => 12]);
        $asset = FixedAsset::create(['company_id' => $company->id, 'fixed_asset_category_id' => $category->id, 'code' => 'FA-1', 'name' => 'Rig', 'acquisition_date' => '2026-08-01', 'depreciation_start_date' => '2026-08-01', 'acquisition_cost' => '1250.00', 'residual_value' => '50.00', 'useful_life_months' => 12, 'created_by' => $user->id]);
        $expense = Account::create(['company_id' => $company->id, 'code' => 'DEP', 'name' => 'Dep expense', 'type' => 'expense', 'normal_balance' => 'debit']);
        $accumulated = Account::create(['company_id' => $company->id, 'code' => 'ACC', 'name' => 'Accum dep', 'type' => 'asset', 'normal_balance' => 'credit']);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'asset_depreciation', 'entry_side' => 'expense_debit', 'account_id' => $expense->id]);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'asset_depreciation', 'entry_side' => 'accumulated_credit', 'account_id' => $accumulated->id]);

        $service = app(FixedAssetService::class);
        $depreciation = $service->depreciate($asset, $period, '2026-08-31', 'fa-1-202608', $user);
        $duplicate = $service->depreciate($asset, $period, '2026-08-31', 'fa-1-202608', $user);
        $journal = $depreciation->journal_id;

        $this->assertSame('100.00', $depreciation->amount);
        $this->assertSame($depreciation->id, $duplicate->id);
        $this->assertDatabaseHas('journal_entries', ['journal_id' => $journal, 'debit' => '100.00', 'credit' => '0.00']);
        $this->assertDatabaseHas('journal_entries', ['journal_id' => $journal, 'debit' => '0.00', 'credit' => '100.00']);
    }
}
