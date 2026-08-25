<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\CasingMovement;
use App\Models\CasingUnit;
use App\Models\CompanySetting;
use App\Models\ConcreteDelivery;
use App\Models\ConstraintLog;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\InspectionTestPlan;
use App\Models\JobSafetyAnalysis;
use App\Models\PileBottomCleaningInspection;
use App\Models\PileReadinessCheck;
use App\Models\ReinforcementCage;
use App\Models\User;

/**
 * Deterministic Readiness Engine (ADR-073): READY/NOT_READY atas checklist
 * tetap dari data nyata — TANPA AI dan TANPA mengubah transisi status pile.
 *
 * Aturan fitur opsional (evidence rules, ITP hold point, cage QC, cleaning
 * gate, JSA, slurry) hanya berlaku bila company mengaktifkannya di settings —
 * backward compatible untuk perusahaan yang tidak memakai fitur tersebut.
 */
class PileReadinessService
{
    public const DRILL_READY = 'READY';

    public const DRILL_NOT_READY = 'NOT_READY';

    public const CAST_READY = 'READY_TO_CAST';

    public const CAST_BLOCKED = 'BLOCKED';

    public function __construct(
        private EvidenceRequirementService $evidenceRules,
        private PilePdfService $pilePdf,
    ) {}

    /** @return array{status: string, blockers: array<int, string>, checklist: array<int, array{key: string, label: string, state: string, detail: string}>, checked_at: string} */
    public function drillReadiness(BoredPile $pile): array
    {
        $companyId = $pile->project->company_id;
        $checklist = [];
        $add = function (string $key, string $label, bool $pass, bool $enabled = true, string $detail = '') use (&$checklist) {
            $state = ! $enabled ? 'skip' : ($pass ? 'pass' : 'fail');
            $checklist[] = ['key' => $key, 'label' => $label, 'state' => $state, 'detail' => $detail];

            return $state !== 'fail';
        };

        // 1. Gambar kerja disetujui pada registry dokumen proyek.
        $drawings = Document::where('company_id', $companyId)
            ->where('project_id', $pile->project_id)
            ->where('document_type', 'like', '%drawing%');
        $hasDrawing = (clone $drawings)->exists();
        $approvedDrawing = (clone $drawings)->where('workflow_status', 'approved')->exists();
        $add('approved_drawing', 'Gambar kerja disetujui', $approvedDrawing, true,
            ! $hasDrawing ? 'Belum ada gambar kerja terdaftar di registry proyek.' : ($approvedDrawing ? '' : 'Gambar kerja ada namun belum ada yang berstatus approved.'));

        // 2. Setting-out selesai (aktivitas setting_out sudah ditutup).
        $settingOutDone = $pile->activities()->where('to_status', 'setting_out')->whereNotNull('finished_at')->exists();
        $add('setting_out_complete', 'Setting-out selesai', $settingOutDone);

        // 3. Koordinat survey tersedia.
        $hasCoords = filled($pile->coordinate_x) && filled($pile->coordinate_y)
            || filled($pile->design_easting) && filled($pile->design_northing)
            || filled($pile->latitude) && filled($pile->longitude);
        $add('survey_coordinates', 'Koordinat survey tersedia', $hasCoords);

        // 4. Platform kerja & akses siap (attestasi lapangan teraudit).
        $platformReady = $pile->platform_ready_at !== null;
        $add('platform_access', 'Platform kerja & akses siap', $platformReady, true,
            $platformReady ? 'Dikonfirmasi '.$pile->platform_ready_at->format('d/m/y H:i') : 'Belum dikonfirmasi via form attestasi.');

        // 5. Rig dialokasikan.
        $rigAllocated = $pile->rig_equipment_id !== null
            && Equipment::whereKey($pile->rig_equipment_id)->whereIn('status', ['operational', 'active'])->exists();
        $add('rig_allocated', 'Rig dialokasikan', $rigAllocated, true,
            $pile->rig_equipment_id === null ? 'Belum ada rig pada pile.' : ($rigAllocated ? '' : 'Rig terdaftar namun tidak berstatus operasional.'));

        // 6. Operator/supervisor tersedia.
        $crewReady = filled($pile->operator_name) || filled($pile->supervisor_name);
        $add('crew_available', 'Operator/supervisor tersedia', $crewReady);

        // 7. Casing tersedia bila pile mensyaratkan casing.
        if ($pile->casing_required) {
            $casingAvailable = CasingUnit::where('company_id', $companyId)
                ->whereIn('status', ['in_stock', 'extracted', 'repaired'])
                ->exists();
            $add('casing_available', 'Casing tersedia', $casingAvailable, true, $casingAvailable ? '' : 'Tidak ada unit casing siap pakai.');
        } else {
            $add('casing_available', 'Casing tersedia', true, false, 'Pile tidak mensyaratkan casing.');
        }

        // 8. JSA aktif bila company mewajibkan.
        if (CompanySetting::val($companyId, 'require_jsa_active') === '1') {
            $jsaActive = JobSafetyAnalysis::where('company_id', $companyId)
                ->where('project_id', $pile->project_id)
                ->where('status', 'active')
                ->whereDate('valid_from', '<=', now())->whereDate('valid_until', '>=', now())
                ->exists();
            $add('jsa_complete', 'JSA/HSE aktif', $jsaActive, true, $jsaActive ? '' : 'Tidak ada JSA aktif yang mencakup proyek ini.');
        } else {
            $add('jsa_complete', 'JSA/HSE aktif', true, false, 'Company tidak mewajibkan JSA.');
        }

        // 9. ITP pre-drill hold point lolos bila company mewajibkan.
        [$itpRequired, $itpOpen] = $this->holdPointState($pile);
        $add('itp_pre_drill', 'ITP pre-drill hold point lolos', $itpOpen === 0, $itpRequired,
            $itpRequired && $itpOpen > 0 ? "{$itpOpen} hold point ITP belum tertutup." : '');

        // 10. Material readiness — tidak ada constraint material terbuka.
        $materialBlocked = $this->openConstraints($pile, 'material')->isNotEmpty();
        $add('material_readiness', 'Material readiness (constraint material tertutup)', ! $materialBlocked);

        // 11. Status proyek aktif.
        $projectActive = in_array($pile->project->status, ['active', 'in_progress'], true);
        $add('project_active', 'Status proyek aktif', $projectActive);

        // 12. Tidak ada hold/constraint pemblokir pada pile.
        $noHold = $pile->status !== 'hold' && $this->openConstraints($pile)->isEmpty();
        $add('no_blocking_hold', 'Tidak ada hold/constraint pemblokir', $noHold);

        return $this->result(self::DRILL_READY, self::DRILL_NOT_READY, $checklist);
    }

    /** @return array{status: string, blockers: array<int, string>, checklist: array<int, array{key: string, label: string, state: string, detail: string}>, checked_at: string} */
    public function castReadiness(BoredPile $pile): array
    {
        $companyId = $pile->project->company_id;
        $checklist = [];
        $add = function (string $key, string $label, bool $pass, bool $enabled = true, string $detail = '') use (&$checklist) {
            $state = ! $enabled ? 'skip' : ($pass ? 'pass' : 'fail');
            $checklist[] = ['key' => $key, 'label' => $label, 'state' => $state, 'detail' => $detail];

            return $state !== 'fail';
        };

        // 1. Target depth tercapai (dalam toleransi depth company).
        $tolerance = max(0.5, (float) CompanySetting::val($companyId, 'pile_depth_tolerance_percent'));
        $minDepth = (float) $pile->planned_depth_m * (1 - $tolerance / 100);
        $depthReached = $pile->actual_depth_m !== null && (float) $pile->actual_depth_m >= $minDepth;
        $add('target_depth', 'Target kedalaman tercapai', $depthReached, true,
            $depthReached ? '' : ($pile->actual_depth_m === null ? 'Kedalaman aktual belum terekam.' : "Aktual {$pile->actual_depth_m} m < minimum ".round($minDepth, 2).' m.'));

        // 2. Bore log tercatat (drilling record dengan lapisan tanah).
        $boreLogged = BoredPileDrilling::where('bored_pile_id', $pile->id)->whereHas('layers')->exists();
        $add('bore_log', 'Bore log tercatat (lapisan tanah)', $boreLogged);

        // 3. Bottom cleaning — gate inspeksi hanya bila company mengaktifkan.
        $cleaningGate = CompanySetting::val($companyId, 'require_cleaning_inspection') === '1';
        $latestCleaning = PileBottomCleaningInspection::where('bored_pile_id', $pile->id)->orderByDesc('inspected_at')->first();
        if ($cleaningGate) {
            $cleaningOk = $latestCleaning?->status === 'accepted';
            $add('bottom_cleaning', 'Bottom cleaning diterima inspeksi', $cleaningOk, true,
                $latestCleaning === null ? 'Belum ada record inspeksi cleaning.' : ($cleaningOk ? '' : 'Inspeksi terakhir berstatus '.$latestCleaning->status.'.'));
        } else {
            $sedimentRecorded = $latestCleaning !== null
                || BoredPileDrilling::where('bored_pile_id', $pile->id)->whereNotNull('sediment_depth_mm')->exists();
            $add('bottom_cleaning', 'Sediment thickness tercatat', $sedimentRecorded, true, $sedimentRecorded ? '' : 'Data sediment/cleaning belum terekam.');
        }

        // 4. Sediment dalam toleransi bila batas dikonfigurasi (default OFF).
        $sedimentMax = CompanySetting::val($companyId, 'sediment_max_mm');
        if (filled($sedimentMax)) {
            $measured = $latestCleaning?->sediment_thickness_mm
                ?? BoredPileDrilling::where('bored_pile_id', $pile->id)->max('sediment_depth_mm');
            $within = $measured !== null && (float) $measured <= (float) $sedimentMax;
            $add('sediment_tolerance', 'Sediment dalam toleransi', $within, true,
                $measured === null ? 'Sediment belum terukur.' : ($within ? '' : "Sediment {$measured} mm > batas {$sedimentMax} mm."));
        } else {
            $add('sediment_tolerance', 'Sediment dalam toleransi', true, false, 'Batas sediment tidak dikonfigurasi (record only).');
        }

        // 5. Cage delivered ke titik pile.
        $cageDelivered = ReinforcementCage::where('bored_pile_id', $pile->id)->whereNotNull('delivered_at')->exists();
        $add('cage_delivered', 'Cage delivered ke lokasi', $cageDelivered);

        // 6. Cage QC passed bila company mewajibkan.
        $requireCageQc = CompanySetting::val($companyId, 'require_cage_passed') === '1';
        $cageQcPassed = ReinforcementCage::where('bored_pile_id', $pile->id)->where('qc_status', 'passed')->exists();
        $add('cage_qc', 'Cage QC passed', $cageQcPassed, $requireCageQc, $requireCageQc && ! $cageQcPassed ? 'Belum ada cage dengan QC passed pada pile ini.' : '');

        // 7. Casing terpasang/ditarik sesuai alur bila required.
        if ($pile->casing_required) {
            $casingValid = CasingUnit::where('current_bored_pile_id', $pile->id)->exists()
                || CasingMovement::where('bored_pile_id', $pile->id)->whereIn('type', ['installed', 'extracted'])->exists();
            $add('casing_valid', 'Status casing valid', $casingValid, true, $casingValid ? '' : 'Belum ada pergerakan casing tercatat untuk pile ini.');
        } else {
            $add('casing_valid', 'Status casing valid', true, false, 'Pile tidak mensyaratkan casing.');
        }

        // 8. Booking/pengiriman beton tersiap (attestasi atau delivery nyata).
        $bookingOk = $pile->concrete_booking_confirmed_at !== null
            || ConcreteDelivery::where('bored_pile_id', $pile->id)->exists();
        $add('concrete_booking', 'Booking/pengiriman beton tersiap', $bookingOk, true,
            $bookingOk ? '' : 'Belum ada konfirmasi booking maupun delivery beton.');

        // 9. ITP pre-pour hold point bila wajib.
        [$itpRequired, $itpOpen] = $this->holdPointState($pile);
        $add('itp_pre_pour', 'ITP pre-pour hold point lolos', $itpOpen === 0, $itpRequired,
            $itpRequired && $itpOpen > 0 ? "{$itpOpen} hold point ITP belum tertutup." : '');

        // 10. Evidence wajib tersedia bila rules aktif.
        if ($this->evidenceRules->enabled($companyId)) {
            $missing = count($this->evidenceRules->missing($pile));
            $add('required_evidence', 'Evidence wajib lengkap', $missing === 0, true,
                $missing > 0 ? "{$missing} kategori foto kurang dari minimum." : '');
        } else {
            $add('required_evidence', 'Evidence wajib lengkap', true, false, 'Evidence rules tidak aktif.');
        }

        // 11. Tidak ada NCR kritis terbuka pada pile (engineering tetap manusia).
        $criticalNcr = $this->pilePdf->linkedNonconformities($pile)->where('severity', 'critical')->where('status', '!=', 'closed')->count();
        $add('no_critical_ncr', 'Tidak ada NCR kritis terbuka', $criticalNcr === 0, true, $criticalNcr > 0 ? "{$criticalNcr} NCR kritis masih terbuka." : '');

        // 12. Tidak ada blocking hold.
        $noHold = $pile->status !== 'hold' && $this->openConstraints($pile)->isEmpty();
        $add('no_blocking_hold', 'Tidak ada hold/constraint pemblokir', $noHold);

        return $this->result(self::CAST_READY, self::CAST_BLOCKED, $checklist);
    }

    /** Simpan snapshot hasil evaluasi sebagai history (immutable). */
    public function recordCheck(BoredPile $pile, string $kind, User $actor): PileReadinessCheck
    {
        $result = $kind === PileReadinessCheck::KIND_DRILL
            ? $this->drillReadiness($pile)
            : $this->castReadiness($pile);

        return PileReadinessCheck::create([
            'company_id' => $pile->project->company_id,
            'bored_pile_id' => $pile->id,
            'kind' => $kind,
            'status' => $result['status'],
            'blockers' => $result['blockers'],
            'checklist' => $result['checklist'],
            'checked_by' => $actor->id,
        ]);
    }

    /** Snapshot terakhir per kind untuk kartu "terakhir dicek" di UI. */
    public function latestChecks(BoredPile $pile): array
    {
        $rows = PileReadinessCheck::where('bored_pile_id', $pile->id)
            ->whereIn('kind', [PileReadinessCheck::KIND_DRILL, PileReadinessCheck::KIND_CAST])
            ->latest('id')->get()->unique('kind');

        return [
            PileReadinessCheck::KIND_DRILL => $rows->firstWhere('kind', PileReadinessCheck::KIND_DRILL),
            PileReadinessCheck::KIND_CAST => $rows->firstWhere('kind', PileReadinessCheck::KIND_CAST),
        ];
    }

    /** Keputusan engineering tetap manusia — service hanya melapor. */
    private function result(string $okStatus, string $blockedStatus, array $checklist): array
    {
        $blockers = collect($checklist)
            ->filter(fn ($c) => $c['state'] === 'fail')
            ->map(fn ($c) => trim(($c['label'] ?? '').': '.($c['detail'] ?? '')))
            ->values()->all();

        return [
            'status' => count($blockers) === 0 ? $okStatus : $blockedStatus,
            'blockers' => $blockers,
            'checklist' => $checklist,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /** @return array{0: bool, 1: int} apakah wajib + jumlah hold point ITP terbuka */
    private function holdPointState(BoredPile $pile): array
    {
        $required = CompanySetting::val($pile->project->company_id, 'require_itp_hold_points_passed') === '1';
        if (! $required) {
            return [false, 0];
        }
        $plans = InspectionTestPlan::where('project_id', $pile->project_id)
            ->where(fn ($q) => $q->whereNull('bored_pile_id')->orWhere('bored_pile_id', $pile->id))
            ->with('items')->get();
        $open = $plans->flatMap->items->filter(fn ($item) => $item->holdOpen())->count();

        return [true, $open];
    }

    private function openConstraints(BoredPile $pile, ?string $type = null)
    {
        return ConstraintLog::where('project_id', $pile->project_id)
            ->where('status', '!=', 'resolved')
            ->where(fn ($q) => $q->whereNull('bored_pile_id')->orWhere('bored_pile_id', $pile->id))
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->get();
    }
}
