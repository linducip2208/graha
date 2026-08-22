<?php

namespace Tests\Feature\Equipment;

use App\Models\Company;
use App\Models\FuelTank;
use App\Models\FuelTankTransaction;
use App\Models\User;
use App\Services\FuelTankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FuelTankTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_issue_balance_and_reconciliation(): void
    {
        [$company, $user, $tank] = $this->fixture();
        $service = app(FuelTankService::class);

        $service->record($tank, ['type' => 'receipt', 'liters' => '5000', 'occurred_at' => now(), 'reference' => 'PO-SOLAR-1', 'idempotency_key' => 'r1', 'project_id' => null, 'equipment_id' => null], $user);
        $dup = $service->record($tank, ['type' => 'receipt', 'liters' => '5000', 'occurred_at' => now(), 'reference' => 'PO-SOLAR-1', 'idempotency_key' => 'r1', 'project_id' => null, 'equipment_id' => null], $user);
        $this->assertSame(1, $tank->transactions()->count());
        $this->assertSame($tank->transactions()->first()->id, $dup->id);

        $service->record($tank, ['type' => 'issue_to_equipment', 'liters' => '320', 'occurred_at' => now(), 'reference' => 'DISP-1', 'idempotency_key' => 'i1', 'project_id' => null, 'equipment_id' => null], $user);
        $this->assertSame('4680.00', number_format((float) $service->balance($tank), 2, '.', ''));

        $result = $service->reconcile($tank->refresh(), '4600.00', $user);
        $this->assertTrue($result['adjusted']);
        $this->assertSame('4600.00', number_format((float) $result['book_after'], 2, '.', ''));

        $balanced = $service->reconcile($tank->refresh(), '4600.00', $user);
        $this->assertFalse($balanced['adjusted']);
        $this->assertSame(3, FuelTankTransaction::where('fuel_tank_id', $tank->id)->count());
    }

    public function test_cross_company_membership_guard(): void
    {
        [$companyA, $userA, $tankA] = $this->fixture('FA');
        [$companyB] = $this->fixture('FB');
        $userB = User::factory()->create();
        $userB->companies()->attach($companyB->id, ['is_default' => true, 'is_active' => true]);

        $this->expectException(ValidationException::class);
        app(FuelTankService::class)->record($tankA, ['type' => 'receipt', 'liters' => '100', 'occurred_at' => now(), 'idempotency_key' => 'x1'], $userB);
    }

    private function fixture(string $code = 'FT'): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $tank = FuelTank::create(['company_id' => $company->id, 'code' => 'TK-'.$code, 'name' => 'Tangki Utama', 'capacity_l' => '8000']);

        return [$company, $user, $tank];
    }
}
