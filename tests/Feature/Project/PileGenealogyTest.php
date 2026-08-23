<?php

namespace Tests\Feature\Project;

use App\Models\BoredPile;
use App\Models\Company;
use App\Models\ConcreteDelivery;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\Role;
use App\Models\User;
use App\Services\BoredPileGenealogyService;
use App\Services\FieldOpsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PileGenealogyTest extends TestCase
{
    use RefreshDatabase;

    public function test_genealogy_assembles_lifecycle_and_flags_anomalies(): void
    {
        [$company, $user, $pile] = $this->fixture();
        $service = app(BoredPileGenealogyService::class);

        // Depth mismatch: aktual 24 m vs rencana 20 m = 20% > toleransi default 5%.
        $pile->update(['actual_depth_m' => '24']);
        // Overconsumption: delivery draft 40 m3 vs teoretis 38 → approve memicu recalculate.
        $delivery = ConcreteDelivery::create([
            'company_id' => $company->id, 'project_id' => $pile->project_id, 'bored_pile_id' => $pile->id,
            'delivery_order_number' => 'DO-G-1', 'truck_number' => 'B-1GEN', 'grade' => "fc'25",
            'ordered_volume_m3' => '30', 'delivered_volume_m3' => '40', 'accepted_volume_m3' => '40',
            'rejected_volume_m3' => '0', 'slump_cm' => '25', 'sample_number' => 'SMP-1',
            'status' => 'draft', 'recorded_by' => $user->id, 'idempotency_key' => 'gene-1',
        ]);
        app(FieldOpsService::class)->approveConcreteDelivery($delivery->refresh(), $user);
        // Pile fase testing tanpa uji apa pun → missing_test.
        $pile->update(['status' => 'testing']);

        $data = $service->build($pile->refresh());
        $codes = collect($data['anomalies'])->pluck('code');

        $this->assertTrue($codes->contains('depth_mismatch'), 'Depth mismatch harus terdeteksi.');
        $this->assertTrue($codes->contains('concrete_overconsumption'), 'Overbreak harus terdeteksi.');
        $this->assertTrue($codes->contains('slump_out_of_spec'), 'Slump 25 cm harus di luar rentang 10–20.');
        $this->assertTrue($codes->contains('missing_test'));
        $this->assertSame(1, $data['deliveries']->count());
    }

    public function test_genealogy_page_renders_and_is_company_scoped(): void
    {
        [$company, $user, $pile] = $this->fixture();
        [$other] = $this->fixture('GB');
        $outsider = User::factory()->create();
        $outsider->companies()->attach($other->id, ['is_default' => true, 'is_active' => true]);

        $roleB = Role::firstOrCreate(['company_id' => $other->id, 'code' => 'viewer-g'], ['name' => 'Viewer G']);
        $permission = Permission::firstOrCreate(['code' => 'project.view'], ['name' => 'project.view', 'module' => 'project']);
        $roleB->permissions()->syncWithoutDetaching([$permission->id]);
        $membership = DB::table('company_user')->where('company_id', $other->id)->where('user_id', $outsider->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $roleB->id]);

        $this->actingAs($user)->withSession(['company_id' => $company->id])
            ->get("/admin/bored-piles/{$pile->id}/genealogy")
            ->assertOk()
            ->assertSee("Genealogi {$pile->pile_number}");
        $this->actingAs($outsider)->withSession(['company_id' => $other->id])
            ->get("/admin/bored-piles/{$pile->id}/genealogy")
            ->assertNotFound();
    }

    private function fixture(string $code = 'GP'): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'viewer-'.$code], ['name' => 'Viewer '.$code]);
        $permission = Permission::firstOrCreate(['code' => 'project.view'], ['name' => 'project.view', 'module' => 'project']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $membership = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C'.$code, 'name' => 'Client']);
        $project = Project::create([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-'.$code, 'name' => 'Project '.$code,
            'contract_value' => '1000000000', 'overbreak_tolerance_percent' => '5', 'status' => 'in_progress',
        ]);
        $pile = BoredPile::create([
            'project_id' => $project->id, 'project_zone_id' => ProjectZone::create(['project_id' => $project->id, 'code' => 'Z1', 'name' => 'Zona 1'])->id,
            'pile_number' => 'BP-G1', 'diameter_mm' => '1000', 'planned_depth_m' => '20', 'theoretical_concrete_m3' => '38',
            'status' => 'drilling', 'created_by' => $user->id,
        ]);

        return [$company, $user, $pile];
    }
}
