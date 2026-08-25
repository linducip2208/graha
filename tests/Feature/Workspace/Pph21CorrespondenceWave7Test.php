<?php

namespace Tests\Feature\Workspace;

use App\Models\Company;
use App\Models\ContractCorrespondence;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Permission;
use App\Models\ProjectAward;
use App\Models\Role;
use App\Models\Tender;
use App\Models\User;
use App\Services\Pph21Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class Pph21CorrespondenceWave7Test extends TestCase
{
    use RefreshDatabase;

    public function test_pph21_brackets_and_ptkp_math(): void
    {
        $service = new Pph21Service;

        // TK0, gaji 10jt/bln: bruto 120jt − biaya jabatan 6jt − PTKP 58,5jt = PKP 55,5jt.
        // Lapisan: 50jt×5% = 2,5jt; 5,5jt×15% = 825rb → 3.325.000/tahun.
        $r = $service->calculate('10000000', 'TK0', true);
        $this->assertSame('55500000.00', $r['pkp']);
        $this->assertSame('3325000.00', $r['annual_tax']);
        $this->assertSame('277083.33', $r['monthly_tax']);
        $this->assertFalse($r['npwp_surcharge']);

        // Tanpa NPWP: disuratkan +20%.
        $noNpwp = $service->calculate('10000000', 'TK0', false);
        $this->assertSame('3990000.00', $noNpwp['annual_tax']);
        $this->assertTrue($noNpwp['npwp_surcharge']);

        // K/2: PTKP 60,3jt → PKP lebih kecil dari TK0 pada gaji sama.
        $k2 = $service->calculate('10000000', 'K2', true);
        $this->assertSame('53700000.00', $k2['pkp']);

        // Gaji rendah di bawah PTKP: PPh nol.
        $low = $service->calculate('3000000', 'K3', true);
        $this->assertSame('0', $low['annual_tax']);
    }

    public function test_pph21_validates_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Pph21Service)->calculate('5000000', 'ZZ9', true);
    }

    public function test_correspondence_store_and_isolation(): void
    {
        [$companyA, $companyB, , $award] = $this->fixture();
        $correspondence = ContractCorrespondence::create(['company_id' => $companyA->id, 'project_award_id' => $award->id, 'direction' => 'out', 'ref_number' => 'GP/OUT/2026/001', 'correspondence_date' => today()->toDateString(), 'subject' => 'Submit shop drawing revisi', 'created_by' => 1]);
        $this->assertSame('out', $correspondence->direction);
        $this->assertSame(0, ContractCorrespondence::where('company_id', $companyB->id)->count(), 'Isolasi lintas company.');
    }

    public function test_pages_render_and_endpoint_work(): void
    {
        [$company, , $owner, $award] = $this->fixture(withRole: true);
        $this->actingAs($owner)->withSession(['company_id' => $company->id]);
        $this->get('/admin/contract-admin')->assertOk();

        $customer = Customer::where('company_id', $company->id)->firstOrFail();
        $tender = Tender::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'number' => 'TND-2', 'year' => 2026, 'project_name' => 'T2', 'status' => 'won', 'created_by' => $owner->id]);
        $document = Document::create(['company_id' => $company->id, 'document_type' => 'letter', 'number' => 'DOC-9', 'title' => 'Surat Penawaran', 'workflow_status' => 'approved', 'signature_status' => 'unsigned', 'owner_id' => $owner->id]);
        $version = DocumentVersion::create(['document_id' => $document->id, 'version' => 1, 'disk' => 'local', 'path' => 'docs/d9.pdf', 'sha256' => str_repeat('b', 64), 'size_bytes' => 512, 'mime_type' => 'application/pdf', 'created_by' => $owner->id]);

        $this->post("/admin/contract-admin/{$award->id}/correspondences", ['direction' => 'in', 'ref_number' => 'OWN/IN/2026/77', 'correspondence_date' => today()->toDateString(), 'subject' => 'Approval pile cap', 'document_version_id' => $version->id])->assertRedirect();
        $this->assertDatabaseHas('contract_correspondences', ['company_id' => $company->id, 'ref_number' => 'OWN/IN/2026/77', 'direction' => 'in']);
        unset($tender);
    }

    /** @return array [companyA, companyB, award] */
    private function fixture(bool $withRole = false): array
    {
        static $n = 0;
        $n++;
        $companyA = Company::create(['code' => 'GPW7'.$n.uniqid()[0], 'name' => "GP A{$n}"]);
        $companyB = Company::create(['code' => 'GPX7'.$n.uniqid()[0], 'name' => "GP B{$n}"]);
        $owner = User::factory()->create();
        $owner->companies()->attach($companyA->id, ['is_default' => true, 'is_active' => true]);
        if ($withRole) {
            $role = Role::firstOrCreate(['company_id' => $companyA->id, 'code' => 'comm-'.$companyA->id], ['name' => 'Comm']);
            foreach (['contract.view', 'contract.manage'] as $permCode) {
                $permission = Permission::firstOrCreate(['code' => $permCode], ['name' => $permCode, 'module' => str($permCode)->before('.')]);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
            $membershipId = (int) \DB::table('company_user')->where('company_id', $companyA->id)->where('user_id', $owner->id)->value('id');
            \DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membershipId, 'role_id' => $role->id]);
        }
        $customer = Customer::create(['company_id' => $companyA->id, 'code' => 'C-W7', 'name' => 'Pelanggan']);
        $tender = Tender::create(['company_id' => $companyA->id, 'customer_id' => $customer->id, 'number' => 'TND-W7', 'year' => 2026, 'project_name' => 'Tender W7', 'status' => 'won', 'created_by' => $owner->id]);
        $award = ProjectAward::create(['company_id' => $companyA->id, 'tender_id' => $tender->id, 'customer_id' => $customer->id, 'source' => 'tender', 'award_type' => 'unit_price', 'award_number' => 'AWD-W7', 'award_date' => now()->toDateString(), 'contract_value' => '500000000', 'retention_percent' => '5', 'status' => 'signed']);

        return [$companyA, $companyB, $owner, $award];
    }
}
