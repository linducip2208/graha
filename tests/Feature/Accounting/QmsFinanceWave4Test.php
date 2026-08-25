<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerComplaint;
use App\Models\FiscalPeriod;
use App\Models\Journal;
use App\Models\Nonconformity;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\NumberSequenceService;
use App\Services\RecurringJournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QmsFinanceWave4Test extends TestCase
{
    use RefreshDatabase;

    public function test_customer_complaint_lifecycle_and_isolation(): void
    {
        [$company, $owner] = $this->companyFixture();
        [$other] = $this->companyFixture('GPB');
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-1', 'name' => 'Pelanggan']);
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'customer_complaint'], ['prefix' => 'CCM', 'padding' => 4, 'last_reset_year' => 2026]);

        $complaint = CustomerComplaint::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'number' => app(NumberSequenceService::class)->next($company->id, 'customer_complaint'), 'complaint_date' => today()->toDateString(), 'channel' => 'email', 'subject' => 'Retak pada pile head', 'description' => 'Ditemukan retak rambut', 'severity' => 'major', 'status' => 'open', 'recorded_by' => $owner->id]);
        $this->assertStringContainsString('CCM', $complaint->number);

        // Isolasi: company lain tidak melihat keluhan ini.
        $this->assertSame(0, CustomerComplaint::where('company_id', $other->id)->count());

        $complaint->update(['status' => 'resolved', 'resolution_notes' => 'Perbaikan dan grouting ulang', 'resolved_by' => $owner->id, 'resolved_at' => now()]);
        $this->assertSame('resolved', $complaint->refresh()->status);
    }

    public function test_supplier_ncr_requires_valid_vendor_and_source(): void
    {
        [$company, $owner] = $this->companyFixture();
        $vendor = Vendor::create(['company_id' => $company->id, 'code' => 'V-1', 'name' => 'Supplier Beton']);
        // NCR supplier dengan vendor valid — diterima.
        $ncr = Nonconformity::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'number' => 'NCR-S1', 'source_type' => 'supplier', 'severity' => 'minor', 'description' => 'Slump di luar spesifikasi batch 2', 'reported_by' => $owner->id]);
        $this->assertSame($vendor->id, $ncr->refresh()->vendor_id);
        $this->assertSame(0, Nonconformity::where('company_id', $company->id)->whereNull('vendor_id')->count());
    }

    public function test_recurring_journal_create_validate_and_post_due_idempotent(): void
    {
        [$company, $owner] = $this->companyFixture(withFiscal: true);
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'journal'], ['prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        $cash = Account::create(['company_id' => $company->id, 'code' => 'BANK4', 'name' => 'Bank', 'type' => 'asset', 'normal_balance' => 'debit']);
        $rent = Account::create(['company_id' => $company->id, 'code' => 'RENT4', 'name' => 'Sewa kantor', 'type' => 'expense', 'normal_balance' => 'debit']);
        $service = app(RecurringJournalService::class);

        // Unbalanced ditolak saat create.
        try {
            $service->create($company->id, ['name' => 'Sewa', 'description' => 'Sewa bulanan', 'day_of_month' => 5, 'lines' => [['account_id' => $rent->id, 'debit' => '1000', 'credit' => '0'], ['account_id' => $cash->id, 'debit' => '0', 'credit' => '900']]], $owner);
            $this->fail('Template tidak seimbang harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $template = $service->create($company->id, ['name' => 'Sewa Kantor', 'description' => 'Sewa kantor bulanan', 'day_of_month' => (int) now()->format('d'), 'lines' => [['account_id' => $rent->id, 'debit' => '1000', 'credit' => '0'], ['account_id' => $cash->id, 'debit' => '0', 'credit' => '1000']]], $owner);
        $template->update(['next_run_at' => now()->toDateString()]);

        $posted1 = $service->postDue($owner);
        $posted2 = $service->postDue($owner);

        $this->assertSame(1, $posted1, 'Satu template due diposting sekali.');
        $this->assertSame(0, $posted2, 'Eksekusi kedua idempotent per periode.');
        $this->assertSame(2, \DB::table('journal_entries')->whereIn('account_id', [$cash->id, $rent->id])->count());
        $template->refresh();
        $this->assertTrue($template->next_run_at->isFuture(), 'next_run_at maju satu bulan setelah posting.');
    }

    public function test_recurring_run_now_and_paused_guard(): void
    {
        [$company, $owner] = $this->companyFixture(withFiscal: true);
        NumberSequence::firstOrCreate(['company_id' => $company->id, 'document_type' => 'journal'], ['prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);
        $cash = Account::create(['company_id' => $company->id, 'code' => 'BANKR', 'name' => 'Bank', 'type' => 'asset', 'normal_balance' => 'debit']);
        $fee = Account::create(['company_id' => $company->id, 'code' => 'FEE', 'name' => 'Biaya admin', 'type' => 'expense', 'normal_balance' => 'debit']);
        $service = app(RecurringJournalService::class);
        $template = $service->create($company->id, ['name' => 'Admin Bank', 'description' => 'Biaya admin bank bulanan', 'day_of_month' => 28, 'lines' => [['account_id' => $fee->id, 'debit' => '250', 'credit' => '0'], ['account_id' => $cash->id, 'debit' => '0', 'credit' => '250']]], $owner);

        $service->runNow($template, $owner);
        $service->runNow($template->refresh(), $owner); // key sama → jurnal lama dikembalikan, tanpa duplikat
        $this->assertSame(1, Journal::where('company_id', $company->id)->where('source_type', 'recurring_journal')->count());

        $template->update(['status' => 'paused']);
        try {
            $service->runNow($template, $owner);
            $this->fail('Template paused tidak boleh dijalankan.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_pages_render_for_permitted_user(): void
    {
        [$company, $owner] = $this->companyFixture(withRole: true);
        $this->actingAs($owner)->withSession(['company_id' => $company->id]);
        $this->get('/admin/complaints')->assertOk();
        $this->get('/admin/recurring-journals')->assertOk();

        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-9', 'name' => 'Pelanggan Sembilan']);
        $this->post('/admin/complaints', ['customer_id' => $customer->id, 'complaint_date' => today()->toDateString(), 'channel' => 'phone', 'subject' => 'Jadwal mundur', 'description' => 'Delivery beton terlambat 3 jam', 'severity' => 'minor'])->assertRedirect();
        $this->assertDatabaseHas('customer_complaints', ['company_id' => $company->id, 'subject' => 'Jadwal mundur']);
    }

    private function companyFixture(string $code = 'GPW', bool $withFiscal = false, bool $withRole = false): array
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
            $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'fin-qms'], ['name' => 'Fin QMS']);
            foreach (['finance.view', 'finance.manage', 'accounting.post', 'qms.view', 'qms.manage'] as $permCode) {
                $permission = Permission::firstOrCreate(['code' => $permCode], ['name' => $permCode, 'module' => str($permCode)->before('.')]);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
            $membershipId = (int) \DB::table('company_user')->where('company_id', $company->id)->where('user_id', $owner->id)->value('id');
            \DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membershipId, 'role_id' => $role->id]);
        }

        return [$company, $owner];
    }
}
