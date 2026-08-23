<?php

namespace Tests\Feature\Foundation;

use App\Models\Company;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\MaintenanceWorkOrder;
use App\Models\Nonconformity;
use App\Models\User;
use App\Services\EquipmentService;
use App\Services\QmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistryAutoLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_mwo_registers_document_idempotently(): void
    {
        [$company, $user, $equipment, $wo] = $this->fixture();
        $service = app(EquipmentService::class);

        $closed = $service->closeMaintenanceOrder($wo, ['actual_cost' => '1500000'], $user);

        $this->assertSame('closed', $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertDatabaseHas('documents', [
            'company_id' => $company->id,
            'document_type' => 'maintenance_work_order',
            'number' => 'MWO-001',
        ]);
    }

    public function test_closed_mwo_cannot_be_closed_twice(): void
    {
        [, $user, , $wo] = $this->fixture();
        $service = app(EquipmentService::class);
        $service->closeMaintenanceOrder($wo, [], $user);

        $this->expectException(ValidationException::class);
        $service->closeMaintenanceOrder($wo->refresh(), [], $user);
    }

    public function test_effective_capa_and_closed_ncr_register_documents(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $owner = User::factory()->create();
        $verifier = User::factory()->create();
        $ncr = Nonconformity::create(['company_id' => $company->id, 'number' => 'NCR-REG-1', 'source_type' => 'inspection', 'severity' => 'major', 'description' => 'Dimensi cage di luar toleransi', 'reported_by' => $owner->id]);
        $action = $ncr->actions()->create(['action' => 'Kalibrasi jig fabrikasi', 'owner_id' => $owner->id, 'due_at' => '2026-09-30', 'evidence' => 'Foto + sertifikat kalibrasi']);

        app(QmsService::class)->verifyCapa($action, $verifier, 'Efektif di lapangan');

        $this->assertSame('effective', $action->refresh()->status);
        $this->assertSame('closed', $ncr->refresh()->status);
        // CAPA efektif dan NCR tertutup keduanya otomatis terdaftar.
        $this->assertDatabaseHas('documents', ['company_id' => $company->id, 'document_type' => 'corrective_action', 'number' => 'NCR-REG-1-CA'.$action->id]);
        $this->assertDatabaseHas('documents', ['company_id' => $company->id, 'document_type' => 'nonconformity_report', 'number' => 'NCR-REG-1']);
        $rows = Document::where('company_id', $company->id)->count();
        $this->assertSame(2, $rows);
    }

    private function fixture(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'Graha Pondasi']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $equipment = Equipment::create(['company_id' => $company->id, 'code' => 'EX-200', 'name' => 'Boring Machine 200', 'ownership' => 'owned', 'category' => 'boring_rig', 'current_hour_meter' => '1200']);
        $wo = MaintenanceWorkOrder::create([
            'company_id' => $company->id, 'equipment_id' => $equipment->id, 'number' => 'MWO-001',
            'type' => 'breakdown', 'problem' => 'Hidrolik bocor', 'meter_reading' => '1200',
            'status' => 'open', 'opened_by' => $user->id,
        ]);

        return [$company, $user, $equipment, $wo];
    }
}
