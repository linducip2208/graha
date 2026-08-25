<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\Company;
use App\Models\FiscalPeriod;
use App\Models\Journal;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\PrepaidExpense;
use App\Models\Role;
use App\Models\User;
use App\Services\PrepaidAmortizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PrepaidAmortizationWave8Test extends TestCase
{
    use RefreshDatabase;

    public function test_amortization_math_idempotent_and_completion(): void
    {
        [$company, $owner] = $this->fixture(withFiscal: true);
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'journal'], ['prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        $expense = Account::create(['company_id' => $company->id, 'code' => 'PREXP', 'name' => 'Sewa', 'type' => 'expense', 'normal_balance' => 'debit']);
        $prepaid = Account::create(['company_id' => $company->id, 'code' => 'PRPRED', 'name' => 'Beban Dibayar Dimuka', 'type' => 'asset', 'normal_balance' => 'debit']);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'prepaid_amortization', 'entry_side' => 'expense_debit', 'account_id' => $expense->id]);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'prepaid_amortization', 'entry_side' => 'prepaid_credit', 'account_id' => $prepaid->id]);

        $service = app(PrepaidAmortizationService::class);

        // Validasi: nilai negatif ditolak.
        try {
            $service->create($company->id, ['name' => 'Negatif', 'total_amount' => '-5', 'period_count' => 3, 'first_period_date' => now()->startOfMonth()->toDateString()], $owner);
            $this->fail('Nilai prepaid negatif harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Total 1000 / 3 bulan mulai 2 bulan lalu: 333.33 + 333.33 + 333.34 (sisa pembulatan di bulan terakhir).
        $first = now()->startOfMonth()->subMonths(2)->toDateString();
        $record = $service->create($company->id, ['name' => 'Sewa Kantor Dimuka', 'vendor_ref' => 'PT Propindo', 'total_amount' => '1000', 'period_count' => 3, 'first_period_date' => $first], $owner);
        $this->assertSame('333.33', $record->monthlyAmount());
        $this->assertSame(3, count($record->duePeriods()), 'Tiga periode (bulan -2, -1, sekarang) jatuh tempo.');

        $posted1 = $service->postAllDue($owner);
        $posted2 = $service->postAllDue($owner);

        $this->assertSame(3, $posted1, 'Seluruh periode due diposting sekali jalan.');
        $this->assertSame(0, $posted2, 'Posting ulang idempotent per periode.');
        $record->refresh();
        $this->assertSame('completed', $record->status, 'Status completed setelah seluruh periode terposting.');

        $amounts = Journal::where('company_id', $company->id)->where('source_type', 'prepaid_amortization')->with('entries')->get()
            ->map(fn ($j) => (float) $j->entries->sum('debit'))->sort()->values()->all();
        $this->assertSame([333.33, 333.33, 333.34], $amounts, 'Bulan terakhir menyerap sisa pembulatan.');

        // Total debit beban = total kredit prepaid = nilai penuh.
        $this->assertSame('1000.00', number_format((float) DB::table('journal_entries')->where('account_id', $expense->id)->sum('debit'), 2, '.', ''));
        $this->assertSame('1000.00', number_format((float) DB::table('journal_entries')->where('account_id', $prepaid->id)->sum('credit'), 2, '.', ''));

        // Guard: posting periode pada record completed ditolak.
        try {
            $service->postPeriod($record, now()->addMonth()->format('Y-m'), $owner);
            $this->fail('Prepaid completed tidak boleh diposting lagi.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_create_requires_mapping_and_pages_store_work(): void
    {
        [$company, $owner] = $this->fixture(withRole: true);
        $this->actingAs($owner)->withSession(['company_id' => $company->id]);
        $this->get('/admin/prepaid-expenses')->assertOk();

        // Tanpa mapping prepaid_amortization: pembuatan ditolak.
        try {
            app(PrepaidAmortizationService::class)->create($company->id, ['name' => 'Tanpa Mapping', 'total_amount' => '500', 'period_count' => 5, 'first_period_date' => now()->startOfMonth()->toDateString()], $owner);
            $this->fail('Create tanpa mapping harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $expense = Account::create(['company_id' => $company->id, 'code' => 'INSEX', 'name' => 'Asuransi', 'type' => 'expense', 'normal_balance' => 'debit']);
        $asset = Account::create(['company_id' => $company->id, 'code' => 'INSAS', 'name' => 'Prepaid Asuransi', 'type' => 'asset', 'normal_balance' => 'debit']);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'prepaid_amortization', 'entry_side' => 'expense_debit', 'account_id' => $expense->id]);
        AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'prepaid_amortization', 'entry_side' => 'prepaid_credit', 'account_id' => $asset->id]);

        $this->post('/admin/prepaid-expenses', ['name' => 'Asuransi Proyek Tahunan', 'vendor_ref' => 'Astra Insurance', 'total_amount' => '12000', 'period_count' => '12', 'first_period_date' => now()->startOfMonth()->toDateString()])->assertRedirect();
        $this->assertDatabaseHas('prepaid_expenses', ['company_id' => $company->id, 'name' => 'Asuransi Proyek Tahunan', 'period_count' => 12, 'status' => 'active']);

        // Isolasi: company lain tidak melihat register ini.
        $other = Company::create(['code' => 'GPZ8'.uniqid()[0], 'name' => 'GP Z']);
        $this->assertSame(0, PrepaidExpense::where('company_id', $other->id)->count());
    }

    private function fixture(string $code = 'GPW8', bool $withFiscal = false, bool $withRole = false): array
    {
        static $n = 0;
        $n++;
        $company = Company::create(['code' => $code.$n.uniqid()[0], 'name' => "GP {$n}"]);
        $owner = User::factory()->create();
        $owner->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        if ($withFiscal) {
            FiscalPeriod::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        }
        if ($withRole) {
            $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'fin-pre'], ['name' => 'Fin Prepaid']);
            foreach (['finance.view', 'finance.manage', 'accounting.post'] as $permCode) {
                $permission = Permission::firstOrCreate(['code' => $permCode], ['name' => $permCode, 'module' => str($permCode)->before('.')]);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
            $membershipId = (int) \DB::table('company_user')->where('company_id', $company->id)->where('user_id', $owner->id)->value('id');
            \DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membershipId, 'role_id' => $role->id]);
        }

        return [$company, $owner];
    }
}
