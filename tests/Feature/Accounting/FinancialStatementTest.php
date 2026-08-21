<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\NumberSequence;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\FinancialStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_balance_profit_and_balance_sheet_are_derived_from_posted_journals(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        FiscalPeriod::create(['company_id' => $company->id, 'name' => 'Agustus', 'starts_at' => '2026-08-01', 'ends_at' => '2026-08-31', 'status' => 'open']);
        FiscalPeriod::create(['company_id' => $company->id, 'name' => 'Juli', 'starts_at' => '2026-07-01', 'ends_at' => '2026-07-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        $cash = Account::create(['company_id' => $company->id, 'code' => '1000', 'name' => 'Kas', 'type' => 'asset', 'normal_balance' => 'debit']);
        $revenue = Account::create(['company_id' => $company->id, 'code' => '4000', 'name' => 'Pendapatan', 'type' => 'revenue', 'normal_balance' => 'credit']);
        $expense = Account::create(['company_id' => $company->id, 'code' => '5000', 'name' => 'Beban', 'type' => 'expense', 'normal_balance' => 'debit']);
        $accounting = app(AccountingService::class);
        $accounting->post($company->id, '2026-07-31', 'test', '0', 'Pendapatan sebelum periode', [['account_id' => $cash->id, 'debit' => '20', 'credit' => '0'], ['account_id' => $revenue->id, 'debit' => '0', 'credit' => '20']], 'fs-opening', $user);
        $accounting->post($company->id, '2026-08-10', 'test', '1', 'Pendapatan', [['account_id' => $cash->id, 'debit' => '100', 'credit' => '0'], ['account_id' => $revenue->id, 'debit' => '0', 'credit' => '100']], 'fs-revenue', $user);
        $accounting->post($company->id, '2026-08-11', 'test', '2', 'Beban', [['account_id' => $expense->id, 'debit' => '40', 'credit' => '0'], ['account_id' => $cash->id, 'debit' => '0', 'credit' => '40']], 'fs-expense', $user);

        $report = app(FinancialStatementService::class)->generate($company->id, '2026-08-01', '2026-08-31');

        $this->assertSame('120.00', $report['total_debit']);
        $this->assertSame('120.00', $report['total_credit']);
        $this->assertSame('100.00', $report['revenue']);
        $this->assertSame('40.00', $report['expense']);
        $this->assertSame('60.00', $report['profit']);
        $this->assertSame('80.00', $report['assets']);
        $this->assertSame('80.00', $report['unclosed_earnings']);
        $this->assertSame('80.00', $report['liabilities_equity_profit']);
    }
}
