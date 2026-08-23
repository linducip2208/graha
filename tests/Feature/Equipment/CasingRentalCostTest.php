<?php

namespace Tests\Feature\Equipment;

use App\Models\Account;
use App\Models\AccountingMapping;
use App\Models\BoredPile;
use App\Models\CasingUnit;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\Journal;
use App\Models\NumberSequence;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\User;
use App\Services\CasingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CasingRentalCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_with_cost_posts_balanced_rental_journal(): void
    {
        // ADR-046: biaya sewa casing otomatis ke GL saat pergerakan berbiaya.
        [$company, $user, $casing, $pile, $service] = $this->fixture();
        $journalsBefore = Journal::where('company_id', $company->id)->count();

        $unit = $service->move($casing, 'installed', $pile->id, null, '500000', Carbon::parse('2026-08-10'), $user);

        $this->assertSame('500000.00', (string) $unit->rental_cost_total);
        $journal = Journal::where('company_id', $company->id)->where('source_type', 'casing_rental_cost')->firstOrFail();
        $totals = DB::table('journal_entries')->where('journal_id', $journal->id)->selectRaw('SUM(debit) d, SUM(credit) c')->first();
        $this->assertSame(500000.0, (float) $totals->d);
        $this->assertSame((float) $totals->d, (float) $totals->c);

        // Pergerakan tanpa biaya tidak membuat jurnal baru.
        $service->move($unit->refresh(), 'extracted', null, 'Kembali ke gudang', '0', Carbon::parse('2026-08-15'), $user);
        $this->assertSame($journalsBefore + 1, Journal::where('company_id', $company->id)->count());
    }

    public function test_missing_mapping_blocks_priced_move(): void
    {
        [$company, $user, $casing, $pile, $service] = $this->fixture(withoutMapping: true);

        try {
            $service->move($casing, 'installed', $pile->id, null, '250000', Carbon::parse('2026-08-10'), $user);
            $this->fail('Biaya sewa tanpa mapping akun harus ditolak, bukan diam-diam dilewatkan.');
        } catch (ValidationException) {
            $this->assertNull(Journal::where('company_id', $company->id)->where('source_type', 'casing_rental_cost')->first());
        }
    }

    private function fixture(bool $withoutMapping = false): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        FiscalPeriod::create(['company_id' => $company->id, 'name' => '2026', 'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'status' => 'open']);
        NumberSequence::create(['company_id' => $company->id, 'document_type' => 'journal', 'prefix' => 'JV', 'padding' => 4, 'last_reset_year' => 2026]);

        if (! $withoutMapping) {
            $expense = Account::create(['company_id' => $company->id, 'code' => 'BSEWA', 'name' => 'Biaya Sewa Peralatan', 'type' => 'expense', 'normal_balance' => 'debit']);
            $payable = Account::create(['company_id' => $company->id, 'code' => 'UTSEWA', 'name' => 'Utang Sewa', 'type' => 'liability', 'normal_balance' => 'credit']);
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'casing_rental_cost', 'entry_side' => 'expense_debit', 'account_id' => $expense->id]);
            AccountingMapping::create(['company_id' => $company->id, 'event_type' => 'casing_rental_cost', 'entry_side' => 'payable_credit', 'account_id' => $payable->id]);
        }

        // Titik pile untuk pergerakan instalasi.
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-CS', 'name' => 'Klien Casing']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-CS', 'name' => 'Proyek Casing', 'status' => 'in_progress']);
        $zone = ProjectZone::create(['project_id' => $project->id, 'code' => 'Z1', 'name' => 'Zona 1']);
        $pile = BoredPile::create(['project_id' => $project->id, 'project_zone_id' => $zone->id, 'pile_number' => 'BP-CS-1', 'diameter_mm' => '800', 'planned_depth_m' => '20', 'status' => 'drilling', 'created_by' => $user->id]);

        $casing = CasingUnit::create(['company_id' => $company->id, 'code' => 'CS-RENT-1', 'diameter_mm' => '800', 'length_m' => '6', 'ownership' => 'rented', 'status' => 'in_stock', 'created_by' => $user->id]);
        $service = app(CasingService::class);

        return [$company, $user, $casing, $pile, $service];
    }
}
