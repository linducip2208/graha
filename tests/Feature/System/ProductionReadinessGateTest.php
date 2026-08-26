<?php

namespace Tests\Feature\System;

use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_verifier_is_read_only_and_passes_an_empty_dataset(): void
    {
        $this->artisan('inventory:verify')
            ->expectsOutputToContain('PASS - tidak ada anomali inventory.')
            ->assertExitCode(0);
    }

    public function test_foundation_verifier_is_read_only_and_passes_an_empty_dataset(): void
    {
        $this->artisan('foundation:verify')
            ->expectsOutputToContain('PASS - tidak ada anomali foundation.')
            ->assertExitCode(0);
    }

    public function test_accounting_verifier_is_read_only_and_passes_an_empty_dataset(): void
    {
        $this->artisan('accounting:verify')
            ->expectsOutputToContain('PASS - tidak ada anomali accounting.')
            ->assertExitCode(0);
    }

    public function test_accounting_verifier_reports_an_unbalanced_posted_journal_without_mutating_it(): void
    {
        $company = Company::create(['code' => 'VERIFY', 'name' => 'Verifier']);
        $user = User::factory()->create();
        $period = FiscalPeriod::create(['company_id' => $company->id, 'name' => 'August', 'starts_at' => '2026-08-01', 'ends_at' => '2026-08-31', 'status' => 'open']);
        $account = Account::create(['company_id' => $company->id, 'code' => '1000', 'name' => 'Cash', 'type' => 'asset', 'normal_balance' => 'debit']);
        $journal = Journal::create([
            'company_id' => $company->id,
            'fiscal_period_id' => $period->id,
            'number' => 'JV-VERIFY',
            'journal_date' => '2026-08-10',
            'source_type' => 'test',
            'source_id' => '1',
            'description' => 'Invalid fixture',
            'status' => 'posted',
            'idempotency_key' => 'verify-unbalanced',
            'created_by' => $user->id,
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);
        $journal->entries()->create(['account_id' => $account->id, 'debit' => 100, 'credit' => 0]);

        $this->artisan('accounting:verify')
            ->expectsOutputToContain('UNBALANCED_OR_EMPTY')
            ->assertExitCode(1);
        $this->assertDatabaseHas('journals', ['id' => $journal->id, 'status' => 'posted']);
    }

    public function test_production_check_fails_closed_in_test_configuration_without_leaking_secrets(): void
    {
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        config()->set('app.seed_demo_data', true);

        $this->artisan('production:check')
            ->expectsOutputToContain('NOT READY')
            ->doesntExpectOutputToContain((string) config('app.key'))
            ->assertExitCode(1);
    }
}
