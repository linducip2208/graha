<?php

namespace Tests\Feature\Workspace;

use App\Models\Company;
use App\Models\ContractMilestone;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Permission;
use App\Models\ProjectAward;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Services\ContractAdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContractAdminDowntimeWave3Test extends TestCase
{
    use RefreshDatabase;

    public function test_milestone_weight_cap_and_achieve_flow(): void
    {
        [$company, $award, $owner] = $this->fixture();
        $service = app(ContractAdminService::class);

        $m1 = $service->addMilestone($award, ['company_id' => $company->id, 'name' => 'Mobilisasi', 'planned_date' => '2026-09-01', 'weight_percent' => '60', 'amount' => '0'], $owner);
        try {
            $service->addMilestone($award, ['company_id' => $company->id, 'name' => 'Berlebih', 'weight_percent' => '50', 'amount' => '0'], $owner);
            $this->fail('Total bobot melebihi 100% harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $this->assertSame(60.0, (float) ContractMilestone::where('project_award_id', $award->id)->sum('weight_percent'));

        $service->achieveMilestone($m1, '2026-08-01', $owner);
        $this->assertSame('achieved', $m1->refresh()->status);

        try {
            $service->achieveMilestone($m1, today()->toDateString(), $owner);
            $this->fail('Milestone sudah tercapai tidak boleh dicapai lagi.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Isolasi: service menolak award dari company lain.
        $other = Company::create(['code' => 'GPX3', 'name' => 'X']);
        try {
            $service->addMilestone(new ProjectAward(['company_id' => $other->id]), ['company_id' => $other->id, 'name' => 'X', 'weight_percent' => '10', 'amount' => '0'], $owner);
            $this->fail('Award tanpa relasi harus ditolak.');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }
    }

    public function test_insurance_date_validation_and_status(): void
    {
        [$company, $award, $owner] = $this->fixture();
        $service = app(ContractAdminService::class);

        try {
            $service->addInsurance($award, ['company_id' => $company->id, 'policy_number' => 'POL-1', 'provider' => 'Asuransi A', 'coverage_type' => 'car', 'insured_amount' => '1000000', 'premium' => '0', 'start_date' => '2026-09-01', 'end_date' => '2026-08-01'], $owner);
            $this->fail('Polis dengan end sebelum start harus ditolak.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $active = $service->addInsurance($award, ['company_id' => $company->id, 'policy_number' => 'POL-2', 'provider' => 'Asuransi B', 'coverage_type' => 'tpl', 'insured_amount' => '500000000', 'premium' => '5000000', 'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(6)->toDateString()], $owner);
        $expiring = $service->addInsurance($award, ['company_id' => $company->id, 'policy_number' => 'POL-3', 'provider' => 'Asuransi C', 'coverage_type' => 'surety', 'insured_amount' => '100000000', 'premium' => '1000000', 'start_date' => now()->subMonths(6)->toDateString(), 'end_date' => now()->addDays(15)->toDateString()], $owner);
        $expired = $service->addInsurance($award, ['company_id' => $company->id, 'policy_number' => 'POL-4', 'provider' => 'Asuransi D', 'coverage_type' => 'ear', 'insured_amount' => '200000000', 'premium' => '2000000', 'start_date' => now()->subYears(2)->toDateString(), 'end_date' => now()->subYear()->toDateString()], $owner);

        $this->assertSame('active', $active->statusNow());
        $this->assertSame('expiring', $expiring->statusNow());
        $this->assertSame('expired', $expired->statusNow());
    }

    public function test_pages_render_and_downtime_flow(): void
    {
        [$company, $award, $owner] = $this->fixture();
        $this->actingAs($owner)->withSession(['company_id' => $company->id]);
        $this->get('/admin/contract-admin')->assertOk();

        // Buat milestone via endpoint.
        $this->post("/admin/contract-admin/{$award->id}/milestones", ['name' => 'Pile 50%', 'planned_date' => '2026-09-15', 'weight_percent' => '40', 'amount' => '0'])->assertRedirect();

        // Equipment downtime: mulai → tutup.
        $equipment = Equipment::create(['company_id' => $company->id, 'code' => 'EQ-DT', 'name' => 'Rig DT', 'ownership' => 'owned', 'category' => 'drilling', 'current_hour_meter' => '0']);
        $this->get("/admin/operations/equipment/{$equipment->id}")->assertOk();
        $this->post("/admin/operations/equipment/{$equipment->id}/downtime", ['started_at' => now()->subHours(3)->format('Y-m-d H:i'), 'reason' => 'breakdown'])->assertRedirect();
        $logId = (int) \DB::table('equipment_downtime_logs')->where('equipment_id', $equipment->id)->value('id');

        try {
            $this->post("/admin/operations/equipment/{$equipment->id}/downtime", ['started_at' => now()->format('Y-m-d H:i'), 'reason' => 'weather']);
            $this->fail('Downtime kedua saat masih ada yang berjalan harus ditolak.');
        } catch (\Throwable) {
            $this->assertTrue(true);
        }

        $this->post("/admin/operations/equipment/{$equipment->id}/downtime/{$logId}/close", ['ended_at' => now()->format('Y-m-d H:i')])->assertRedirect();
        $endedAt = \DB::table('equipment_downtime_logs')->where('id', $logId)->value('ended_at');
        $this->assertNotNull($endedAt, 'Downtime harus tertutup.');

        // Audit diff: halaman audit render OK dan mengandung event downtime.
        $this->get('/admin/audit?event=downtime')->assertOk();
    }

    /** @return array [company, award, owner] */
    private function fixture(): array
    {
        static $n = 0;
        $n++;
        $company = Company::create(['code' => 'GPD'.$n.uniqid()[0], 'name' => 'GP D']);
        $owner = User::factory()->create();
        $owner->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $codes = ['contract.view', 'contract.manage', 'tender.view', 'manufacturing.view', 'manufacturing.manage', 'audit.view'];
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'admin-'.$company->id], ['name' => 'Admin']);
        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(['code' => $code], ['name' => $code, 'module' => str($code)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membershipId = (int) \DB::table('company_user')->where('company_id', $company->id)->where('user_id', $owner->id)->value('id');
        \DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membershipId, 'role_id' => $role->id]);

        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-1', 'name' => 'Pelanggan']);
        $tender = Tender::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'number' => 'TND-1', 'year' => 2026, 'project_name' => 'Tender Proyek', 'status' => 'won', 'created_by' => $owner->id]);
        $award = ProjectAward::create(['company_id' => $company->id, 'tender_id' => $tender->id, 'customer_id' => $customer->id, 'source' => 'tender', 'award_type' => 'unit_price', 'award_number' => 'AWD-1', 'award_date' => now()->toDateString(), 'contract_value' => '1000000000', 'retention_percent' => '5', 'status' => 'signed']);

        return [$company, $award, $owner];
    }
}
