<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\FiscalPeriod as FiscalPeriodAlias;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\NumberSequence;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\CashFlowStatementService;
use App\Services\FixedAssetService;
use App\Services\ReceivableAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinanceBacklogWave1Test extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_asset_disposal_posts_balanced_journal_with_loss(): void
    {
        [$company, $user, $asset, $accounts] = $this->disposalFixture();
        $service = app(FixedAssetService::class);
        $periodJul = FiscalPeriod::create(['company_id' => $company->id, 'name' => 'Jul', 'starts_at' => '2026-07-01', 'ends_at' => '2026-07-31', 'status' => 'open']);
        $service->depreciate($asset, $periodJul, '2026-07-31', 'fa-jul', $user);

        $disposed = $service->dispose($asset, '2026-08-10', '900.00', 'disp-1', $user);
        $retry = $service->dispose($asset->refresh(), '2026-08-10', '900.00', 'disp-1', $user);

        $this->assertSame($disposed->id, $retry->id, 'Dispose ulang dengan key sama bersifat idempotent.');
        $this->assertSame('disposed', $disposed->status);
        $this->assertSame('900.00', (string) $disposed->disposal_proceeds);
        $journal = $disposed->disposalJournal;
        $this->assertSame('posted', $journal->status);
        $this->assertSame((string) $journal->entries()->sum('debit'), (string) $journal->entries()->sum('credit'), 'Jurnal disposal harus seimbang.');
        // Akumulasi 100, cost 1250, proceeds 900 → loss = 1250-100-900 = 250.
        $lossEntry = $journal->entries()->where('account_id', $accounts['loss']->id)->first();
        $this->assertNotNull($lossEntry, 'Rugi disposal harus diposting ke akun loss.');
        $this->assertSame('250.00', (string) $lossEntry->debit);

        try {
            $service->dispose($asset->refresh(), '2026-08-11', '900.00', 'disp-2', $user);
            $this->fail('Aset yang sudah dilepas tidak boleh dilepas lagi.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_disposal_requires_complete_mappings(): void
    {
        [$company, $user, $asset] = $this->disposalFixture(null);
        $service = app(FixedAssetService::class);

        try {
            $service->dispose($asset, '2026-08-10', '500.00', 'disp-x', $user);
            $this->fail('Tanpa mapping asset_disposal harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'asset_disposal', 'entry_side' => 'accumulated_debit', 'account_id' => Account::where('company_id', $company->id)->where('code', 'ACC')->first()->id]);
        try {
            $service->dispose($asset, '2026-08-10', '500.00', 'disp-y', $user);
            $this->fail('Mapping parsial harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $this->assertSame('active', $asset->refresh()->status, 'Gagal mapping tidak boleh mengubah status aset.');
    }

    public function test_ar_credit_note_reduces_outstanding_is_capped_and_idempotent(): void
    {
        [$company, $requester, $approver, $billing] = $this->arFixture();
        unset($company, $approver);
        $service = app(ReceivableAdjustmentService::class);
        $this->assertSame('1000.00', $service->outstanding($billing));

        $note = $service->creditNote($billing, '150.00', 'Koreksi volume', '2026-08-15', 'cn-1', $requester);
        $dup = $service->creditNote($billing, '150.00', 'Koreksi volume', '2026-08-15', 'cn-1', $requester);
        $this->assertSame($note->id, $dup->id, 'Credit note idempotent per key.');
        $this->assertSame('850.00', $service->outstanding($billing));
        $journal = $note->journal;
        $this->assertSame((string) $journal->entries()->sum('debit'), (string) $journal->entries()->sum('credit'));

        try {
            $service->creditNote($billing->refresh(), '9999.00', 'Kelebihan', '2026-08-16', 'cn-2', $requester);
            $this->fail('Credit note melebihi sisa piutang harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_write_off_requires_independent_approval_then_posts_expense(): void
    {
        [, $requester, $approver, $billing] = $this->arFixture();
        $service = app(ReceivableAdjustmentService::class);

        $writeOff = $service->requestWriteOff($billing, '300.00', 'Piutang gugur', '2026-08-15', 'wo-1', $requester);
        $this->assertSame('pending_approval', $writeOff->status);
        $this->assertSame('1000.00', $service->outstanding($billing), 'Pending write-off belum mengurangi outstanding.');

        try {
            $service->approveWriteOff($writeOff, '2026-08-16', $requester);
            $this->fail('Self-approval harus ditolak.');
        } catch (ValidationException) {
            $this->assertSame('pending_approval', $writeOff->refresh()->status);
        }

        $approved = $service->approveWriteOff($writeOff, '2026-08-16', $approver);
        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->final_journal_id);
        $this->assertSame((string) $approved->finalJournal->entries()->sum('debit'), (string) $approved->finalJournal->entries()->sum('credit'));
        $this->assertSame('700.00', $service->outstanding($billing));

        try {
            $service->approveWriteOff($approved, '2026-08-17', $approver);
            $this->fail('Write-off sudah diputuskan tidak bisa diputuskan lagi.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $rejected = $service->requestWriteOff($billing->refresh(), '50.00', 'Sisa kecil', '2026-08-18', 'wo-2', $requester);
        try {
            $service->rejectWriteOff($rejected, 'Alasan kosong', $requester);
            $this->fail('Self-reject harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $service->rejectWriteOff($rejected, 'Belum cukup bukti', $approver);
        $this->assertSame('rejected', $rejected->refresh()->status);
        $this->assertSame('700.00', $service->outstanding($billing));
    }

    public function test_cash_flow_statement_classifies_direct_flows(): void
    {
        [$company, $user, , $cashAccount] = $this->cashFlowFixture();
        $ar = Account::where('company_id', $company->id)->where('code', 'AR')->first();
        $expense = Account::where('company_id', $company->id)->where('code', 'EXP')->first();
        // Penerimaan pelanggan 600: Dr Bank / Cr AR → operating inflow (default operating).
        app(AccountingService::class)->post($company->id, '2026-08-05', 'customer_receipt', 'RC-1', 'Terima DP', [
            ['account_id' => $cashAccount->id, 'debit' => '600.00', 'credit' => '0'],
            ['account_id' => $ar->id, 'debit' => '0', 'credit' => '600.00'],
        ], 'cf-j1', $user);
        // Satu jurnal dua kategori lawan (FA ditandai investing): dibagi proporsional (150 investing, 50 operating).
        app(AccountingService::class)->post($company->id, '2026-08-06', 'manual', 'JV-MIX', 'Beli aset + beban', [
            ['account_id' => $this->fixedAccountId($company->id), 'debit' => '150.00', 'credit' => '0'],
            ['account_id' => $expense->id, 'debit' => '50.00', 'credit' => '0'],
            ['account_id' => $cashAccount->id, 'debit' => '0', 'credit' => '200.00'],
        ], 'cf-j2', $user);
        // Transfer antar kas: net nol, tidak masuk klasifikasi mana pun.
        $cash2 = Account::create(['company_id' => $company->id, 'code' => 'PETTY', 'name' => 'Petty cash', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash' => true]);
        app(AccountingService::class)->post($company->id, '2026-08-07', 'transfer', 'TR-1', 'Setor petty', [
            ['account_id' => $cashAccount->id, 'debit' => '0', 'credit' => '100.00'],
            ['account_id' => $cash2->id, 'debit' => '100.00', 'credit' => '0'],
        ], 'cf-j3', $user);

        $report = app(CashFlowStatementService::class)->generate($company->id, '2026-08-01', '2026-08-31');

        $this->assertSame('600.00', $report['buckets']['operating_inflow']);
        $this->assertSame('50.00', $report['buckets']['operating_outflow']);
        $this->assertSame('150.00', $report['buckets']['investing_outflow'], 'Akun bertanda investing masuk investing outflow.');
        $this->assertSame('0.00', $report['buckets']['financing_inflow'], 'Transfer antar kas bukan arus kas.');
        $netChange = bcadd(bcadd($report['operating_net'], $report['investing_net'], 2), $report['financing_net'], 2);
        $this->assertSame('400.00', $netChange);
        $this->assertSame('400.00', $report['closing_cash'], 'Kas akhir = saldo riil akun kas (BANK 300 + PETTY 100).');
    }

    public function test_cash_flow_requires_cash_account_configured(): void
    {
        $company = Company::create(['code' => 'GX', 'name' => 'GP X']);
        try {
            app(CashFlowStatementService::class)->generate($company->id, '2026-08-01', '2026-08-31');
            $this->fail('Tanpa akun kas harus gagal jelas.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function arFixture(): array
    {
        $company = Company::create(['code' => 'GPA', 'name' => 'GP AR']);
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        FiscalPeriodAlias::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-1', 'name' => 'Pelanggan']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-1', 'name' => 'Proyek', 'contract_value' => '1000', 'status' => 'active']);
        $billing = ProgressBilling::create(['company_id' => $company->id, 'project_id' => $project->id, 'number' => 'PB-001', 'billing_date' => '2026-08-01', 'progress_percent' => '100', 'gross_amount' => '1000', 'retention_percent' => '0', 'retention_amount' => '0', 'advance_recovery' => '0', 'net_receivable' => '1000', 'status' => 'posted', 'created_by' => $requester->id, 'idempotency_key' => 'pb-1']);
        $revenue = Account::create(['company_id' => $company->id, 'code' => 'REV', 'name' => 'Revenue', 'type' => 'revenue', 'normal_balance' => 'credit']);
        $ar = Account::create(['company_id' => $company->id, 'code' => 'AR', 'name' => 'Piutang', 'type' => 'asset', 'normal_balance' => 'debit']);
        $badDebt = Account::create(['company_id' => $company->id, 'code' => 'BAD', 'name' => 'Beban piutang', 'type' => 'expense', 'normal_balance' => 'debit']);
        foreach ([['ar_credit_note', 'revenue_debit', $revenue], ['ar_credit_note', 'ar_credit', $ar], ['ar_write_off', 'expense_debit', $badDebt], ['ar_write_off', 'ar_credit', $ar]] as [$event, $side, $account]) {
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => $event, 'entry_side' => $side, 'account_id' => $account->id]);
        }

        return [$company, $requester, $approver, $billing];
    }

    /** @return array [company, user, asset, accounts|null] */
    private function disposalFixture(?string $withMappings = 'full'): array
    {
        $company = Company::create(['code' => 'GPD', 'name' => 'GP Disposal']);
        $user = User::factory()->create();
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        FiscalPeriodAlias::create(['company_id' => $company->id, 'name' => 'Aug', 'starts_at' => '2026-08-01', 'ends_at' => '2026-08-31', 'status' => 'open']);
        $category = FixedAssetCategory::create(['company_id' => $company->id, 'code' => 'EQ', 'name' => 'Equipment', 'default_useful_life_months' => 12]);
        $asset = FixedAsset::create(['company_id' => $company->id, 'fixed_asset_category_id' => $category->id, 'code' => 'FA-D1', 'name' => 'Rig Lama', 'acquisition_date' => '2026-07-01', 'depreciation_start_date' => '2026-07-01', 'acquisition_cost' => '1250.00', 'residual_value' => '50.00', 'useful_life_months' => 12, 'created_by' => $user->id]);
        $expense = Account::create(['company_id' => $company->id, 'code' => 'DEP', 'name' => 'Dep expense', 'type' => 'expense', 'normal_balance' => 'debit']);
        $accumulated = Account::create(['company_id' => $company->id, 'code' => 'ACC', 'name' => 'Accum dep', 'type' => 'asset', 'normal_balance' => 'credit']);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'asset_depreciation', 'entry_side' => 'expense_debit', 'account_id' => $expense->id]);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'asset_depreciation', 'entry_side' => 'accumulated_credit', 'account_id' => $accumulated->id]);

        if ($withMappings !== 'full') {
            return [$company, $user, $asset, null];
        }
        $cost = Account::create(['company_id' => $company->id, 'code' => 'FACOST', 'name' => 'Fixed asset cost', 'type' => 'asset', 'normal_balance' => 'debit']);
        $proceeds = Account::create(['company_id' => $company->id, 'code' => 'CASHD', 'name' => 'Kas', 'type' => 'asset', 'normal_balance' => 'debit']);
        $gain = Account::create(['company_id' => $company->id, 'code' => 'GAIN', 'name' => 'Laba disposal', 'type' => 'revenue', 'normal_balance' => 'credit']);
        $loss = Account::create(['company_id' => $company->id, 'code' => 'LOSSD', 'name' => 'Rugi disposal', 'type' => 'expense', 'normal_balance' => 'debit']);
        foreach ([['asset_disposal', 'accumulated_debit', $accumulated], ['asset_disposal', 'asset_cost_credit', $cost], ['asset_disposal', 'proceeds_debit', $proceeds], ['asset_disposal', 'gain_credit', $gain], ['asset_disposal', 'loss_debit', $loss]] as [$event, $side, $account]) {
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => $event, 'entry_side' => $side, 'account_id' => $account->id]);
        }

        return [$company, $user, $asset, ['cost' => $cost, 'proceeds' => $proceeds, 'gain' => $gain, 'loss' => $loss]];
    }

    private function cashFlowFixture(): array
    {
        $company = Company::create(['code' => 'GPCF', 'name' => 'GP CashFlow']);
        $user = User::factory()->create();
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        FiscalPeriodAlias::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-1', 'name' => 'P']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-1', 'name' => 'Proyek', 'contract_value' => '1000', 'status' => 'active']);
        $billing = ProgressBilling::create(['company_id' => $company->id, 'project_id' => $project->id, 'number' => 'PB-CF', 'billing_date' => '2026-08-01', 'progress_percent' => '100', 'gross_amount' => '1000', 'net_receivable' => '1000', 'status' => 'posted', 'created_by' => $user->id, 'idempotency_key' => 'pb-cf']);
        Account::create(['company_id' => $company->id, 'code' => 'REV', 'name' => 'Revenue', 'type' => 'revenue', 'normal_balance' => 'credit']);
        Account::create(['company_id' => $company->id, 'code' => 'AR', 'name' => 'Piutang', 'type' => 'asset', 'normal_balance' => 'debit']);
        $cash = Account::create(['company_id' => $company->id, 'code' => 'BANK', 'name' => 'Bank', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash' => true]);
        Account::create(['company_id' => $company->id, 'code' => 'FA', 'name' => 'Aset tetap', 'type' => 'asset', 'normal_balance' => 'debit', 'cash_flow_category' => 'investing']);
        Account::create(['company_id' => $company->id, 'code' => 'EXP', 'name' => 'Beban umum', 'type' => 'expense', 'normal_balance' => 'debit']);

        return [$company, $user, $billing, $cash];
    }

    private function fixedAccountId(int $companyId): int
    {
        return (int) Account::where('company_id', $companyId)->where('code', 'FA')->value('id');
    }
}
