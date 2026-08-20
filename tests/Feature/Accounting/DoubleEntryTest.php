<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\NumberSequence;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DoubleEntryTest extends TestCase
{
    use RefreshDatabase;

    private function base(): array
    {
        $c = Company::create(['code' => 'GP', 'name' => 'GP']);
        $u = User::factory()->create();
        FiscalPeriod::create(['company_id' => $c->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        NumberSequence::create(['company_id' => $c->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        $inventory = Account::create(['company_id' => $c->id, 'code' => 'INV', 'name' => 'Inventory', 'type' => 'asset', 'normal_balance' => 'debit']);
        $grni = Account::create(['company_id' => $c->id, 'code' => 'GRNI', 'name' => 'GRNI', 'type' => 'liability', 'normal_balance' => 'credit']);

        return [$c, $u, $inventory, $grni];
    }

    public function test_balanced_journal_posts_once_and_is_immutable(): void
    {
        [$c,$u,$inventory,$grni] = $this->base();
        $service = app(AccountingService::class);
        $lines = [['account_id' => $inventory->id, 'debit' => '1000.00', 'credit' => '0'], ['account_id' => $grni->id, 'debit' => '0', 'credit' => '1000.00']];
        $first = $service->post($c->id, '2026-08-21', 'goods_receipt', 'GR-1', 'Receipt', $lines, 'gr-1', $u);
        $second = $service->post($c->id, '2026-08-21', 'goods_receipt', 'GR-1', 'Receipt', $lines, 'gr-1', $u);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('posted', $first->status);
        $totalDebit = $first->entries->reduce(fn (string $carry, $entry) => bcadd($carry, $entry->debit, 2), '0');
        $this->assertSame('1000.00', $totalDebit);
        $this->expectException(\LogicException::class);
        $first->entries->first()->update(['debit' => '2']);
    }

    public function test_unbalanced_journal_and_closed_period_are_rejected(): void
    {
        [$c,$u,$inventory,$grni] = $this->base();
        $service = app(AccountingService::class);
        try {
            $service->post($c->id, '2026-08-21', 'manual', '1', 'Bad', [['account_id' => $inventory->id, 'debit' => '5', 'credit' => '0'], ['account_id' => $grni->id, 'debit' => '0', 'credit' => '4']], 'bad', $u);
            $this->fail();
        } catch (ValidationException) {
            $this->assertDatabaseCount('journals', 0);
        }FiscalPeriod::query()->update(['status' => 'closed']);
        $this->expectException(ValidationException::class);
        $service->post($c->id, '2026-08-21', 'manual', '2', 'Closed', [['account_id' => $inventory->id, 'debit' => '5', 'credit' => '0'], ['account_id' => $grni->id, 'debit' => '0', 'credit' => '5']], 'closed', $u);
    }
}
