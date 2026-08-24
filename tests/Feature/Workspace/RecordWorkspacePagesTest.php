<?php

namespace Tests\Feature\Workspace;

use App\Models\Company;
use App\Models\Equipment;
use App\Models\Nonconformity;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecordWorkspacePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_ncr_po_equipment_record_pages_render_and_are_scoped(): void
    {
        [$company, $user] = $this->fixture('GA');
        $this->giveAll($company, $user);

        $vendor = Vendor::create(['company_id' => $company->id, 'code' => 'V-RW', 'name' => 'Vendor RW']);
        $order = PurchaseOrder::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'number' => 'PO-RW-1', 'order_date' => '2026-08-01', 'created_by' => $user->id]);
        $ncr = Nonconformity::create(['company_id' => $company->id, 'number' => 'NCR-RW-1', 'source_type' => 'inspection', 'severity' => 'minor', 'description' => 'Cover tidak rata', 'reported_by' => $user->id]);
        $equipment = Equipment::create(['company_id' => $company->id, 'code' => 'EX-RW', 'name' => 'Rig RW', 'ownership' => 'owned', 'category' => 'rig']);

        $this->actingAs($user)->withSession(['company_id' => $company->id]);
        $this->get("/admin/procurement/orders/{$order->id}")->assertOk()->assertSee("PO {$order->number}");
        $this->get("/admin/qms/ncrs/{$ncr->id}")->assertOk()->assertSee($ncr->description)->assertSee('CAPA');
        $this->get("/admin/operations/equipment/{$equipment->id}")->assertOk()->assertSee($equipment->name);

        // Cross-company: 404 (outsider diberi permission agar yang diuji isolasi data, bukan gate permission).
        [$other] = $this->fixture('GB');
        $outsider = User::factory()->create();
        $this->giveAll($other, $outsider);
        $this->actingAs($outsider)->withSession(['company_id' => $other->id]);
        $this->get("/admin/procurement/orders/{$order->id}")->assertNotFound();
        $this->get("/admin/qms/ncrs/{$ncr->id}")->assertNotFound();
        $this->get("/admin/operations/equipment/{$equipment->id}")->assertNotFound();
    }

    private function fixture(string $code): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);

        return [$company, User::factory()->create()];
    }

    private function giveAll(Company $company, User $user): void
    {
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'rw-'.$company->id], ['name' => 'RW']);
        foreach (['procurement.view', 'qms.view', 'manufacturing.view'] as $perm) {
            $p = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$p->id]);
        }
        $m = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $m->id, 'role_id' => $role->id]);
    }
}
