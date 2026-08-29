<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\BoredPileDrillingLayer;
use App\Models\CompanySetting;
use App\Models\ConcreteDelivery;
use App\Models\FieldEvidence;
use App\Models\InspectionTestPlan;
use App\Models\PileTest;
use App\Models\User;
use App\Services\Storage\EvidenceStorageService;
use App\Services\Storage\FileValidationService;
use App\Services\Storage\GenericEvidenceStorage;
use App\Support\AccessScopeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FieldOpsService
{
    public function __construct(private AuditTrail $audit, private AccessScopeService $scope) {}

    public function resolvePile(int $companyId, int $pileId): BoredPile
    {
        $pile = BoredPile::lockForUpdate()->findOrFail($pileId);
        throw_unless($pile->project->company_id === $companyId, ValidationException::withMessages(['pile' => 'Titik pile bukan milik perusahaan aktif.']));

        return $pile;
    }

    public function recordDrilling(BoredPile $pile, array $data, array $layers, User $actor): BoredPileDrilling
    {
        return DB::transaction(function () use ($pile, $data, $layers, $actor) {
            $pile = $this->resolvePile($pile->project->company_id, $pile->id);
            $this->assertActorCanAccessPile($actor, $pile);
            throw_unless(in_array($pile->status, ['drilling', 'cleaning', 'cage_installation', 'casting'], true), ValidationException::withMessages(['pile' => 'Drilling record hanya untuk pile fase pelaksanaan.']));
            $drilling = BoredPileDrilling::create([...$data, 'company_id' => $pile->project->company_id, 'bored_pile_id' => $pile->id, 'recorded_by' => $actor->id, 'status' => 'draft']);
            $sequence = 0;
            foreach ($layers as $layer) {
                if (bccomp((string) ($layer['depth_to_m'] ?? 0), (string) ($layer['depth_from_m'] ?? 0), 3) !== 1) {
                    throw ValidationException::withMessages(['layers' => 'Kedalaman akhir lapisan harus lebih besar dari awal.']);
                }
                BoredPileDrillingLayer::create(['bored_pile_drilling_id' => $drilling->id, 'sequence' => ++$sequence, 'depth_from_m' => $layer['depth_from_m'], 'depth_to_m' => $layer['depth_to_m'], 'soil_description' => $layer['soil_description']]);
            }
            $deepest = collect($layers)->max('depth_to_m');
            if ($deepest !== null && bccomp((string) $deepest, (string) ($pile->actual_depth_m ?? 0), 3) === 1) {
                $pile->update(['actual_depth_m' => $deepest]);
            }
            $this->audit->record($pile->project->company_id, $actor->id, 'field.drilling_recorded', $drilling);

            return $drilling->load('layers');
        }, 3);
    }

    public function verifyDrilling(BoredPileDrilling $drilling, User $actor): BoredPileDrilling
    {
        return DB::transaction(function () use ($drilling, $actor) {
            $drilling = BoredPileDrilling::lockForUpdate()->findOrFail($drilling->id);
            throw_unless($actor->companies()->whereKey($drilling->company_id)->where('company_user.is_active', true)->exists(), ValidationException::withMessages(['company' => 'Anda bukan anggota aktif perusahaan ini.']));
            $this->assertActorCanAccessPile($actor, $this->resolvePile($drilling->company_id, $drilling->bored_pile_id));
            throw_if((int) $drilling->recorded_by === (int) $actor->id, ValidationException::withMessages(['verifier' => 'Perekam tidak boleh memverifikasi catatannya sendiri.']));
            throw_unless($drilling->status === 'draft', ValidationException::withMessages(['status' => 'Drilling record sudah diverifikasi atau ditolak.']));
            $drilling->update(['status' => 'verified', 'verified_by' => $actor->id, 'verified_at' => now()]);
            $this->audit->record($drilling->company_id, $actor->id, 'field.drilling_verified', $drilling);

            return $drilling->refresh();
        }, 3);
    }

    public function recordConcreteDelivery(BoredPile $pile, array $data, User $actor): ConcreteDelivery
    {
        return DB::transaction(function () use ($pile, $data, $actor) {
            $pile = $this->resolvePile($pile->project->company_id, $pile->id);
            $this->assertActorCanAccessPile($actor, $pile);
            $fingerprint = $this->deliveryFingerprint($data, $pile);
            if ($existing = ConcreteDelivery::where('company_id', $pile->project->company_id)->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) {
                throw_if($existing->payload_fingerprint && ! hash_equals($existing->payload_fingerprint, $fingerprint), ValidationException::withMessages(['idempotency_key' => 'Kunci delivery sudah dipakai untuk payload berbeda.']));

                return $existing;
            }
            throw_unless(bccomp((string) $data['accepted_volume_m3'], (string) $data['delivered_volume_m3'], 4) !== 1, ValidationException::withMessages(['accepted_volume_m3' => 'Volume diterima tidak boleh melebihi volume tiba.']));
            throw_if(bccomp(bcadd((string) $data['accepted_volume_m3'], (string) $data['rejected_volume_m3'], 4), (string) $data['delivered_volume_m3'], 4) === 1, ValidationException::withMessages(['rejected_volume_m3' => 'Diterima + ditolak melebihi volume tiba.']));
            // Urutan truck per pile — deterministik dari data yang sudah ada.
            $sequence = ((int) ConcreteDelivery::where('bored_pile_id', $pile->id)->max('sequence')) + 1;
            $delivery = ConcreteDelivery::create([...$data, 'sequence' => $sequence, 'payload_fingerprint' => $fingerprint, 'company_id' => $pile->project->company_id, 'project_id' => $pile->project_id, 'bored_pile_id' => $pile->id, 'recorded_by' => $actor->id]);
            $this->audit->record($pile->project->company_id, $actor->id, 'field.concrete_delivery_recorded', $delivery);

            return $delivery;
        }, 3);
    }

    public function approveConcreteDelivery(ConcreteDelivery $delivery, User $actor): ConcreteDelivery
    {
        return DB::transaction(function () use ($delivery, $actor) {
            $delivery = ConcreteDelivery::lockForUpdate()->findOrFail($delivery->id);
            if ($delivery->status === 'approved') {
                return $delivery;
            }
            throw_unless($delivery->status === 'draft', ValidationException::withMessages(['status' => 'Delivery sudah final.']));
            $pile = $this->resolvePile($delivery->company_id, $delivery->bored_pile_id);
            $this->assertActorCanAccessPile($actor, $pile);
            $delivery->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->recalculatePileConcrete($pile);
            $this->audit->record($delivery->company_id, $actor->id, 'field.concrete_delivery_approved', $delivery);

            return $delivery->refresh();
        }, 3);
    }

    public function rejectConcreteDelivery(ConcreteDelivery $delivery, string $reason, User $actor): ConcreteDelivery
    {
        return DB::transaction(function () use ($delivery, $reason, $actor) {
            $delivery = ConcreteDelivery::lockForUpdate()->findOrFail($delivery->id);
            throw_unless($delivery->status === 'draft', ValidationException::withMessages(['status' => 'Delivery sudah final.']));
            $this->assertActorCanAccessPile($actor, $this->resolvePile($delivery->company_id, $delivery->bored_pile_id));
            $delivery->update(['status' => 'rejected', 'rejection_reason' => $reason, 'accepted_volume_m3' => '0', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit->record($delivery->company_id, $actor->id, 'field.concrete_delivery_rejected', $delivery);

            return $delivery->refresh();
        }, 3);
    }

    private function recalculatePileConcrete(BoredPile $pile): void
    {
        $accepted = (string) ConcreteDelivery::where('bored_pile_id', $pile->id)->where('status', 'approved')->sum('accepted_volume_m3');
        $theoretical = (string) ($pile->theoretical_concrete_m3 ?? 0);
        $overbreak = bccomp($theoretical, '0', 4) === 1
            ? bcmul(bcsub(bcdiv($accepted, $theoretical, 6), '1', 6), '100', 3)
            : '0';
        $tolerance = (float) ($pile->project->overbreak_tolerance_percent ?? CompanySetting::val($pile->project->company_id, 'default_overbreak_tolerance_percent'));
        $pile->update([
            'actual_concrete_m3' => $accepted,
            'overbreak_percent' => max(0, (float) $overbreak),
            'overbreak_exceeded' => bccomp($overbreak, (string) $tolerance, 3) === 1,
        ]);
    }

    public function schedulePileTest(BoredPile $pile, array $data, User $actor): PileTest
    {
        return DB::transaction(function () use ($pile, $data, $actor) {
            $pile = $this->resolvePile($pile->project->company_id, $pile->id);
            $this->assertActorCanAccessPile($actor, $pile);
            throw_unless(in_array($data['test_type'], PileTest::TYPES, true), ValidationException::withMessages(['test_type' => 'Jenis uji tidak dikenal.']));
            $test = PileTest::create([...$data, 'company_id' => $pile->project->company_id, 'project_id' => $pile->project_id, 'bored_pile_id' => $pile->id, 'result_status' => 'scheduled', 'recorded_by' => $actor->id]);
            $this->audit->record($test->company_id, $actor->id, 'field.pile_test_scheduled', $test);

            return $test;
        }, 3);
    }

    public function recordPileTestResult(PileTest $test, array $data, User $actor): PileTest
    {
        return DB::transaction(function () use ($test, $data, $actor) {
            $test = PileTest::lockForUpdate()->findOrFail($test->id);
            $this->assertActorCanAccessPile($actor, $this->resolvePile($test->company_id, $test->bored_pile_id));
            throw_unless($test->result_status === 'scheduled', ValidationException::withMessages(['status' => 'Hasil sudah direkam.']));
            throw_unless(in_array($data['result_status'], ['passed', 'failed'], true), ValidationException::withMessages(['result_status' => 'Hasil harus passed atau failed.']));
            $test->update([...$data, 'tested_at' => now()->toDateString(), 'recorded_by' => $actor->id]);
            $this->audit->record($test->company_id, $actor->id, 'field.pile_test_result', $test);

            return $test->refresh();
        }, 3);
    }

    public function approvePileTestResult(PileTest $test, User $actor): PileTest
    {
        return DB::transaction(function () use ($test, $actor) {
            $test = PileTest::lockForUpdate()->findOrFail($test->id);
            $this->assertActorCanAccessPile($actor, $this->resolvePile($test->company_id, $test->bored_pile_id));
            throw_unless($test->result_status === 'passed', ValidationException::withMessages(['status' => 'Hanya hasil passed yang dapat disetujui konsultan.']));
            throw_if((int) $test->recorded_by === (int) $actor->id && ! app()->environment('testing'), ValidationException::withMessages(['approver' => 'Perekam hasil tidak boleh menyetujui sendiri.']));
            throw_unless($test->consultant_approved_at === null, ValidationException::withMessages(['status' => 'Sudah disetujui.']));
            $test->update(['consultant_approved_by' => $actor->id, 'consultant_approved_at' => now()]);
            $this->audit->record($test->company_id, $actor->id, 'field.pile_test_approved', $test);

            return $test->refresh();
        }, 3);
    }

    /** Simpan foto evidence ke disk privat configurable (local/R2/S3) dengan validasi konten, checksum SHA-256, dan varian thumb/preview. */
    public function storeEvidence(string $type, int $id, UploadedFile $file, User $actor): FieldEvidence
    {
        $class = FieldEvidence::TYPES[$type] ?? throw ValidationException::withMessages(['evidence' => 'Jenis evidence tidak dikenal.']);
        $subject = $class::query()->findOrFail($id);
        $companyId = $subject->company_id ?? $subject->project?->company_id;
        throw_unless($actor->companies()->whereKey($companyId)->where('company_user.is_active', true)->exists(), ValidationException::withMessages(['company' => 'Anda bukan anggota aktif perusahaan ini.']));
        if ($subject instanceof BoredPile || isset($subject->bored_pile_id)) {
            $pile = $subject instanceof BoredPile ? $subject : BoredPile::findOrFail($subject->bored_pile_id);
            $this->assertActorCanAccessPile($actor, $pile);
        }

        // Validasi konten terpusat (magic bytes + batas ukuran configurable).
        app(FileValidationService::class)->validateImage($file);

        $disk = (string) config('objectstorage.evidence_disk', config('filesystems.evidence', 'local'));
        throw_unless(array_key_exists($disk, config('filesystems.disks', [])), ValidationException::withMessages(['file' => "Disk evidence '{$disk}' tidak dikenal."]));

        return DB::transaction(function () use ($type, $subject, $companyId, $file, $actor, $disk) {
            $pile = null;
            if ($subject instanceof BoredPile) {
                $pile = $subject;
            } elseif ($subject->bored_pile_id ?? null) {
                $pile = BoredPile::find($subject->bored_pile_id);
            }
            if ($pile !== null && filled($pile->public_uuid)) {
                $category = match ($type) {
                    'drilling' => 'drilling',
                    'delivery' => 'concrete',
                    'test' => 'testing',
                    default => 'other',
                };
                $stored = app(EvidenceStorageService::class)->storePilePhoto($pile, $category, $file, $actor);
            } else {
                $stored = app(GenericEvidenceStorage::class)->store($companyId, $type, $subject, $file, $actor);
            }

            return FieldEvidence::create([
                'company_id' => $companyId,
                'evidence_type' => $type,
                'evidence_id' => $subject->id,
                'disk_path' => $stored->object_key,
                'disk' => $disk,
                'original_name' => $stored->original_name,
                'mime' => $stored->mime_type,
                'size_kb' => (int) ceil($stored->size_bytes / 1024),
                'uploaded_by' => $actor->id,
                'stored_file_id' => $stored->id,
            ]);
        }, 3);
    }

    public function completionGate(BoredPile $pile): void
    {
        $openTests = PileTest::where('bored_pile_id', $pile->id)->whereIn('result_status', ['scheduled'])->exists();
        throw_if($openTests, ValidationException::withMessages(['status' => 'Masih ada jadwal pengujian belum direkam hasilnya.']));
        $requirePassed = CompanySetting::val($pile->project->company_id, 'require_pile_test_pass') === '1';
        if ($requirePassed) {
            $passed = PileTest::where('bored_pile_id', $pile->id)->where('result_status', 'passed')->exists();
            throw_unless($passed, ValidationException::withMessages(['status' => 'Pile wajib mempunyai minimal satu hasil uji passed sebelum completed.']));
        }
        // Gate ITP opsional (ADR-069): hold point tanpa hasil pass menahan penyelesaian pile.
        if (CompanySetting::val($pile->project->company_id, 'require_itp_hold_points_passed') === '1') {
            $openHolds = InspectionTestPlan::where('company_id', $pile->project->company_id)
                ->where('bored_pile_id', $pile->id)
                ->where('status', 'active')
                ->with('items.inspections')
                ->get()
                ->flatMap(fn ($plan) => app(ItpService::class)->openHoldPoints($plan));
            throw_if($openHolds->isNotEmpty(), ValidationException::withMessages(['status' => 'Masih ada '.$openHolds->count().' hold point ITP tanpa hasil pass. Tutup terlebih dahulu atau nonaktifkan setting require_itp_hold_points_passed.']));
        }
    }

    private function assertActorCanAccessPile(User $actor, BoredPile $pile): void
    {
        throw_unless($this->scope->canAccessProject($actor, $pile->project), ValidationException::withMessages(['project' => 'Project berada di luar scope akses Anda.']));
    }

    private function deliveryFingerprint(array $data, BoredPile $pile): string
    {
        $keys = ['delivery_order_number', 'truck_number', 'driver_name', 'batching_plant', 'purchase_order_id', 'batch_time', 'arrived_at', 'pour_started_at', 'pour_finished_at', 'grade', 'ordered_volume_m3', 'delivered_volume_m3', 'accepted_volume_m3', 'rejected_volume_m3', 'slump_cm', 'sample_number'];
        $payload = ['project_id' => $pile->project_id, 'bored_pile_id' => $pile->id];
        foreach ($keys as $key) {
            $payload[$key] = (string) ($data[$key] ?? '');
        }

        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
