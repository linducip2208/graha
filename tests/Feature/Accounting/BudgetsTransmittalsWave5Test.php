<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountBudget;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentTransmittal;
use App\Models\DocumentVersion;
use App\Models\FiscalPeriod;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\BudgetVsActualService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetsTransmittalsWave5Test extends TestCase
{
    use RefreshDatabase;

    public function test_budget_vs_actual_variance_math(): void
    {
        [$company, $owner] = $this->fixture(withFiscal: true);
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'journal'], ['prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        $expense = Account::create(['company_id' => $company->id, 'code' => 'FUEL', 'name' => 'BBM', 'type' => 'expense', 'normal_balance' => 'debit']);
        $cash = Account::create(['company_id' => $company->id, 'code' => 'CASHB', 'name' => 'Kas', 'type' => 'asset', 'normal_balance' => 'debit']);
        $revenue = Account::create(['company_id' => $company->id, 'code' => 'REVB', 'name' => 'Lain-lain', 'type' => 'revenue', 'normal_balance' => 'credit']);
        $period = FiscalPeriod::where('company_id', $company->id)->first();

        AccountBudget::create(['company_id' => $company->id, 'account_id' => $expense->id, 'fiscal_period_id' => $period->id, 'amount' => '1000', 'created_by' => $owner->id]);
        AccountBudget::create(['company_id' => $company->id, 'account_id' => $revenue->id, 'fiscal_period_id' => $period->id, 'amount' => '5000', 'created_by' => $owner->id]);

        // Aktual expense 1200 (over budget), revenue 4000 (under → over flag untuk revenue).
        app(AccountingService::class)->post($company->id, now()->toDateString(), 'manual', 'T1', 'BBM', [['account_id' => $expense->id, 'debit' => '1200', 'credit' => '0'], ['account_id' => $cash->id, 'debit' => '0', 'credit' => '1200']], 'w5-t1', $owner);
        app(AccountingService::class)->post($company->id, now()->toDateString(), 'manual', 'T2', 'Pendapatan', [['account_id' => $cash->id, 'debit' => '4000', 'credit' => '0'], ['account_id' => $revenue->id, 'debit' => '0', 'credit' => '4000']], 'w5-t2', $owner);

        $report = collect(app(BudgetVsActualService::class)->generate($company->id));
        $rows = $report[0]['rows']->keyBy(fn ($r) => $r['budget']->account_id);

        $this->assertSame('1200.00', $rows[$expense->id]['actual']);
        $this->assertTrue($rows[$expense->id]['over'], 'Expense melebihi budget = OVER.');
        $this->assertSame('-200.00', $rows[$expense->id]['variance']);
        // Revenue: aktual dinormalkan positif; aktual di bawah budget = OVER (target tak tercapai).
        $this->assertSame('4000.00', $rows[$revenue->id]['actual']);
        $this->assertSame('1000.00', $rows[$revenue->id]['variance']);
        $this->assertTrue($rows[$revenue->id]['over'], 'Revenue di bawah target = OVER.');
    }

    public function test_transmittal_flow_with_versions_and_isolation(): void
    {
        [$company, $owner] = $this->fixture();
        [$other] = $this->fixture('GPZ');
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-1', 'name' => 'P']);
        $document = Document::create(['company_id' => $company->id, 'document_type' => 'drawing', 'number' => 'DOC-1', 'title' => 'Shop Drawing Pile Cap', 'workflow_status' => 'approved', 'signature_status' => 'unsigned', 'owner_id' => $owner->id]);
        $version = DocumentVersion::create(['document_id' => $document->id, 'version' => 1, 'disk' => 'local', 'path' => 'docs/d1.pdf', 'sha256' => str_repeat('a', 64), 'size_bytes' => 1024, 'mime_type' => 'application/pdf', 'created_by' => $owner->id]);

        $transmittal = DocumentTransmittal::create(['company_id' => $company->id, 'number' => 'TRM-0001', 'recipient' => 'Owner Konsultan', 'transmit_date' => today()->toDateString(), 'method' => 'email', 'status' => 'sent', 'created_by' => $owner->id]);
        $transmittal->items()->create(['document_version_id' => $version->id]);

        $this->assertSame(1, $transmittal->items()->count());
        $transmittal->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
        $this->assertSame('acknowledged', $transmittal->refresh()->status);
        // Isolasi: company lain tidak melihat.
        $this->assertSame(0, DocumentTransmittal::where('company_id', $other->id)->count());
    }

    public function test_pages_render_for_permitted_user(): void
    {
        [$company, $owner] = $this->fixture(withFiscal: true, withRole: true);
        $this->actingAs($owner)->withSession(['company_id' => $company->id]);
        $this->get('/admin/account-budgets')->assertOk();
        $this->get('/admin/documents/transmittals')->assertOk();

        $account = Account::create(['company_id' => $company->id, 'code' => 'UTL', 'name' => 'Utilitas', 'type' => 'expense', 'normal_balance' => 'debit']);
        $periodId = (int) FiscalPeriod::where('company_id', $company->id)->value('id');
        $this->post('/admin/account-budgets', ['account_id' => $account->id, 'fiscal_period_id' => $periodId, 'amount' => '750'])->assertRedirect();
        $this->assertDatabaseHas('account_budgets', ['company_id' => $company->id, 'account_id' => $account->id, 'amount' => '750']);

        // Update nilai pada kombinasi sama.
        $this->post('/admin/account-budgets', ['account_id' => $account->id, 'fiscal_period_id' => $periodId, 'amount' => '900'])->assertRedirect();
        $this->assertSame(1, AccountBudget::where('company_id', $company->id)->count(), 'UpdateOrCreate tidak menduplikasi baris.');
    }

    /** @return array [company, owner] */
    private function fixture(string $prefix = 'GPW5', bool $withFiscal = false, bool $withRole = false): array
    {
        static $n = 0;
        $n++;
        $company = Company::create(['code' => $prefix.$n.uniqid()[0], 'name' => "GP {$n}"]);
        $owner = User::factory()->create();
        $owner->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        if ($withFiscal) {
            FiscalPeriod::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        }
        if ($withRole) {
            $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'fin-doc'], ['name' => 'Fin Doc']);
            foreach (['finance.view', 'finance.manage', 'accounting.post', 'document.view', 'document.manage'] as $permCode) {
                $permission = Permission::firstOrCreate(['code' => $permCode], ['name' => $permCode, 'module' => str($permCode)->before('.')]);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
            $membershipId = (int) \DB::table('company_user')->where('company_id', $company->id)->where('user_id', $owner->id)->value('id');
            \DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membershipId, 'role_id' => $role->id]);
        }

        return [$company, $owner];
    }
}
