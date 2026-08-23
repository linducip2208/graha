<?php

namespace Tests\Feature\Tender;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Tender;
use App\Models\TenderOutcome;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PlanningSupportService;
use App\Services\TenderDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BidDecisionAndPlanningTest extends TestCase
{
    use RefreshDatabase;

    public function test_strong_margin_yields_recommended_bid_and_persists_snapshot(): void
    {
        [$company, $user, $tender] = $this->fixture();
        // Estimasi: BOQ 1.2M vs RAP 800k = margin 33% → skor penuh.
        $tender->estimate()->create(['version' => 1, 'status' => 'approved', 'boq_total' => '1200000', 'rap_total' => '800000', 'created_by' => $user->id]);
        $tender->update(['owner_estimate' => '1200000']);

        $decision = app(TenderDecisionService::class)->evaluate($tender->refresh(), $user);

        $this->assertSame(TenderDecisionService::RECOMMEND_BID, $decision['recommendation']);
        $this->assertGreaterThanOrEqual(70, $decision['score']);
        $this->assertDatabaseHas('tenders', ['id' => $tender->id]);
        $this->assertNotNull($tender->refresh()->bid_decision_at);
        $this->assertSame($user->id, (int) $tender->bid_decision_by);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'event' => 'tender.bid_decision']);
    }

    public function test_thin_margin_without_data_forces_review(): void
    {
        [$company, $user, $tender] = $this->fixture();
        // Tanpa estimasi & tanpa owner_estimate/cost → margin kosong wajib review.
        $decision = app(TenderDecisionService::class)->evaluate($tender, $user);

        $this->assertSame(TenderDecisionService::REVIEW_REQUIRED, $decision['recommendation']);
        $this->assertNotEmpty($decision['reasons']);
        $this->assertFalse(collect($decision['factors'])->contains(fn ($f) => $f['key'] === 'margin'), 'Faktor margin tidak boleh dikarang.');
    }

    public function test_loss_reason_aggregation_groups_lost_outcomes(): void
    {
        [$company, $user, $tenderA] = $this->fixture();
        $tenderB = Tender::create(['company_id' => $company->id, 'customer_id' => $tenderA->customer_id, 'number' => 'T-GP-B', 'year' => 2026, 'project_name' => 'Proyek B', 'status' => 'bidding', 'created_by' => $user->id]);
        foreach ([[$tenderA, 'harga'], [$tenderB, 'teknis']] as [$tender, $reason]) {
            TenderOutcome::create(['tender_id' => $tender->id, 'outcome' => 'lost', 'announced_at' => '2026-08-01', 'primary_reason' => $reason, 'winning_bid_value' => '500000000', 'recorded_by' => $user->id]);
            $tender->update(['status' => 'lost']);
        }
        // Tender lost di perusahaan lain tidak boleh bocor ke analitik GP.
        [, , $tenderC] = $this->fixture(code: 'GB');
        TenderOutcome::create(['tender_id' => $tenderC->id, 'outcome' => 'lost', 'announced_at' => '2026-08-02', 'primary_reason' => 'harga', 'recorded_by' => $user->id]);
        $analysis = app(TenderDecisionService::class)->lossAnalysis($tenderA->company_id);

        $this->assertSame(2, $analysis['total_lost']);
        $this->assertSame(1, $analysis['by_reason']['harga']['total'] ?? 0);
        $this->assertSame(1, $analysis['by_reason']['teknis']['total'] ?? 0);
    }

    public function test_constraint_log_transitions_guarded(): void
    {
        [$company, $user, , $project] = $this->fixture();
        $service = app(PlanningSupportService::class);
        $log = $service->createConstraint($company->id, [
            'project_id' => $project->id, 'type' => 'permit', 'title' => 'Izin kerja menunggu',
            'description' => 'Izin gambar revisi belum turun.', 'raised_at' => now()->toDateString(),
        ], $user);

        $this->expectException(ValidationException::class);
        $service->updateConstraintStatus($log, 'resolved', '', $user);
    }

    public function test_constraint_resolves_with_notes_and_blocks_reopen(): void
    {
        [$company, $user, , $project] = $this->fixture();
        $service = app(PlanningSupportService::class);
        $log = $service->createConstraint($company->id, ['project_id' => $project->id, 'type' => 'material', 'title' => 'Baja telat', 'description' => 'Supplier terlambat.', 'raised_at' => now()->toDateString()], $user);
        $log = $service->updateConstraintStatus($log, 'resolved', 'Barang tiba lengkap.', $user);
        $this->assertSame('resolved', $log->status);
        $this->assertNotNull($log->resolved_at);

        $this->expectException(ValidationException::class);
        $service->updateConstraintStatus($log, 'in_progress', null, $user);
    }

    public function test_procurement_plan_late_detection_and_linking(): void
    {
        [$company, $user, , $project] = $this->fixture();
        $service = app(PlanningSupportService::class);
        $late = $service->createPlan($company->id, ['project_id' => $project->id, 'title' => 'Besi D16', 'quantity' => '5000', 'required_date' => now()->subDays(3)->toDateString()], $user);
        $ontime = $service->createPlan($company->id, ['project_id' => $project->id, 'title' => 'Semen', 'quantity' => '2000', 'required_date' => now()->addMonth()->toDateString()], $user);

        $lateRows = PlanningSupportService::latePlansForCompany($company->id);
        $this->assertTrue($lateRows->contains(fn ($p) => $p->id === $late->id));
        $this->assertFalse($lateRows->contains(fn ($p) => $p->id === $ontime->id));

        // Link PO: dokumen harus benar-benar ada di perusahaan yang sama.
        $vendor = Vendor::create(['company_id' => $company->id, 'code' => 'V-PL', 'name' => 'Supplier Plan']);
        $po = PurchaseOrder::create(['company_id' => $company->id, 'vendor_id' => $vendor->id, 'number' => 'PO-PL-1', 'order_date' => now()->toDateString(), 'created_by' => $user->id]);
        $linked = $service->linkDocument($late, 'po', $po->id, $user);
        $this->assertSame('po_created', $linked->status);
        $this->assertSame($po->id, (int) $linked->purchase_order_id);

        try {
            $service->linkDocument($ontime, 'po', 99999, $user);
            $this->fail('PO lintas keberadaan harus ditolak.');
        } catch (ValidationException) {
            $this->assertNull($ontime->refresh()->purchase_order_id);
        }
    }

    private function fixture(string $code = 'GP'): array
    {
        $company = Company::create(['code' => $code, 'name' => $code]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $customer = Customer::create(['company_id' => $company->id, 'code' => 'C-'.$code, 'name' => 'Pelanggan '.$code, 'payment_term_days' => 30]);
        $project = Project::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'code' => 'P-'.$code, 'name' => 'Proyek '.$code, 'status' => 'in_progress']);
        $tender = Tender::create([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'number' => 'T-'.$code,
            'year' => 2026, 'project_name' => 'Proyek '.$code, 'status' => 'bidding', 'created_by' => $user->id,
        ]);

        return [$company, $user, $tender, $project];
    }
}
