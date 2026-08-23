<?php

namespace Tests\Feature\Procurement;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Project;
use App\Models\Rfq;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PlanningSupportService;
use App\Services\RfqService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendorStatusWbsTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_vendor_cannot_be_invited_to_rfq(): void
    {
        [$company, $user, $vendor] = $this->fixture();
        app(PlanningSupportService::class)->setVendorStatus($company->id, $vendor->id, 'suspended', 'Performa buruk 3 PO terakhir', $user);
        [$rfq] = $this->rfqFixture($company, $user);

        $this->expectException(ValidationException::class);
        app(RfqService::class)->invite($rfq, [$vendor->id], $user);
    }

    public function test_vendor_status_requires_note_and_audits(): void
    {
        [$company, $user, $vendor] = $this->fixture();
        $service = app(PlanningSupportService::class);

        try {
            $service->setVendorStatus($company->id, $vendor->id, 'blacklisted', null, $user);
            $this->fail('Blacklist tanpa alasan harus ditolak.');
        } catch (ValidationException) {
            $this->assertSame('approved', $vendor->refresh()->status);
        }

        $service->setVendorStatus($company->id, $vendor->id, 'blacklisted', 'Penipuan dokumen', $user);
        $this->assertSame('blacklisted', $vendor->refresh()->status);
        $this->assertNotNull($vendor->status_note);
    }

    public function test_wbs_hierarchy_depth_and_project_guard(): void
    {
        [$company, $user, , $project] = $this->fixture();
        $service = app(PlanningSupportService::class);
        $root = $service->createWbs($project->id, ['company_id' => $company->id, 'code' => '1', 'name' => 'Fondasi', 'budget' => '500000000'], $user);
        $child = $service->createWbs($project->id, ['company_id' => $company->id, 'code' => '1.1', 'name' => 'Bored Pile', 'budget' => '300000000', 'parent_id' => $root->id], $user);
        $grand = $service->createWbs($project->id, ['company_id' => $company->id, 'code' => '1.1.1', 'name' => 'Drilling', 'parent_id' => $child->id], $user);
        $great = $service->createWbs($project->id, ['company_id' => $company->id, 'code' => '1.1.1.1', 'name' => 'Set-up rig', 'parent_id' => $grand->id], $user);
        $this->assertSame(4, collect([$root, $child, $grand, $great])->count());

        // Level 5 harus ditolak.
        $this->expectException(ValidationException::class);
        $service->createWbs($project->id, ['company_id' => $company->id, 'code' => '1.1.1.1.1', 'name' => 'Terlalu dalam', 'parent_id' => $great->id], $user);
    }

    public function test_wbs_parent_must_belong_to_same_project(): void
    {
        [$companyA, $userA, , $projectA] = $this->fixture('GA');
        [$companyB, , , $projectB] = $this->fixture('GB');
        $serviceA = app(PlanningSupportService::class);
        $rootB = $serviceA->createWbs($projectB->id, ['company_id' => $companyB->id, 'code' => '1', 'name' => 'Root B'], $userA);

        $this->expectException(ValidationException::class);
        $serviceA->createWbs($projectA->id, ['company_id' => $companyA->id, 'code' => '1', 'name' => 'Anak lintas proyek', 'parent_id' => $rootB->id], $userA);
    }

    private function fixture(string $code = 'GP'): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-'.$code, 'name' => 'Pelanggan']);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-'.$code, 'name' => 'Proyek '.$code, 'status' => 'in_progress']);
        $vendor = Vendor::create(['company_id' => $company->id, 'code' => 'V-'.$code, 'name' => 'Supplier '.$code]);
        $service = app(PlanningSupportService::class);

        return [$company, $user, $vendor, $project, $service];
    }

    private function rfqFixture(Company $company, User $user): array
    {
        $unit = Unit::create(['company_id' => $company->id, 'code' => 'KG', 'name' => 'Kilogram']);
        $item = Item::create(['company_id' => $company->id, 'unit_id' => $unit->id, 'sku' => 'SKU-RFQ', 'name' => 'Besi D16 RFQ', 'category' => 'steel']);
        $rfq = Rfq::create(['company_id' => $company->id, 'number' => 'RFQ-VS-1', 'title' => 'Pengadaan baja', 'status' => 'open', 'created_by' => $user->id, 'due_date' => now()->addDays(7)->toDateString()]);
        $rfq->items()->create(['item_id' => $item->id, 'quantity' => '1000']);

        return [$rfq, $item];
    }
}
