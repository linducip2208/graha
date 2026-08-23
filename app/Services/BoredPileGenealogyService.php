<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\BoredPileDrilling;
use App\Models\CasingUnit;
use App\Models\CompanySetting;
use App\Models\ConcreteDelivery;
use App\Models\FieldEvidence;
use App\Models\PileTest;
use App\Models\ReinforcementCage;
use App\Models\User;

/**
 * Pile Genealogy (ADR-047): satu identitas pile menautkan seluruh siklus
 * hidupnya — lokasi, bore log, delivery beton, slump/sample, pengujian,
 * cage, casing, evidence, hingga aktivitas — plus flag anomali yang
 * dihitung dari data nyata dan ambang configurable perusahaan.
 */
class BoredPileGenealogyService
{
    public function __construct(private AuditTrail $audit) {}

    public function build(BoredPile $pile): array
    {
        $pile = BoredPile::query()->with(['project.customer', 'zone'])->findOrFail($pile->id);
        $drillings = BoredPileDrilling::where('bored_pile_id', $pile->id)->with(['layers', 'recorder', 'verifier'])->orderBy('drilling_started_at')->get();
        $deliveries = ConcreteDelivery::where('bored_pile_id', $pile->id)->with(['vendor', 'recorder'])->orderBy('arrived_at')->orderBy('id')->get();
        $tests = PileTest::where('bored_pile_id', $pile->id)->orderBy('scheduled_date')->get();
        $cages = ReinforcementCage::where('bored_pile_id', $pile->id)->whereNotNull('delivered_at')->orderBy('delivered_at')->get();
        $casings = CasingUnit::where('current_bored_pile_id', $pile->id)->get();
        $evidences = FieldEvidence::where('company_id', $pile->project->company_id)
            ->where(function ($q) use ($drillings, $deliveries, $tests): void {
                $q->where(fn ($t) => $t->where('evidence_type', 'drilling')->whereIn('evidence_id', $drillings->pluck('id')))
                    ->orWhere(fn ($t) => $t->where('evidence_type', 'delivery')->whereIn('evidence_id', $deliveries->pluck('id')))
                    ->orWhere(fn ($t) => $t->where('evidence_type', 'test')->whereIn('evidence_id', $tests->pluck('id')));
            })->with('uploader')->latest()->limit(24)->get();
        $activities = $pile->activities()->orderBy('started_at')->get();

        return [
            'pile' => $pile,
            'drillings' => $drillings,
            'deliveries' => $deliveries,
            'tests' => $tests,
            'cages' => $cages,
            'casings' => $casings,
            'evidences' => $evidences,
            'activities' => $activities,
            'anomalies' => $this->anomalies($pile, $drillings, $deliveries, $tests, $cages, $evidences),
        ];
    }

    /** Daftar anomali nyata; hanya flag dari data, tanpa tuduhan otomatis. */
    public function anomalies(BoredPile $pile, $drillings, $deliveries, $tests, $cages, $evidences): array
    {
        $flags = [];
        $companyId = $pile->project->company_id;

        // 1. Depth mismatch vs rencana di luar toleransi perusahaan.
        if ($pile->actual_depth_m !== null && bccomp((string) $pile->planned_depth_m, '0', 3) === 1) {
            $deviation = abs((float) bcdiv(bcmul(bcsub((string) $pile->actual_depth_m, (string) $pile->planned_depth_m, 3), '100', 4), (string) $pile->planned_depth_m, 4));
            $tolerance = max(0.5, (float) CompanySetting::val($companyId, 'pile_depth_tolerance_percent'));
            if ($deviation > $tolerance) {
                $flags[] = ['code' => 'depth_mismatch', 'severity' => 'warning', 'detail' => "Kedalaman aktual {$pile->actual_depth_m} m menyimpang {$deviation}% dari rencana {$pile->planned_depth_m} m (toleransi {$tolerance}%)."];
            }
        }

        // 2. Konsumsi beton melebihi toleransi (sudah dihitung ulang saat approve delivery).
        if ($pile->overbreak_exceeded) {
            $flags[] = ['code' => 'concrete_overconsumption', 'severity' => 'critical', 'detail' => "Overbreak {$pile->overbreak_percent}% melebihi toleransi proyek."];
        }

        // 3. Slump di luar rentang spesifikasi perusahaan.
        $min = CompanySetting::val($companyId, 'slump_min_cm');
        $max = CompanySetting::val($companyId, 'slump_max_cm');
        if (bccomp($min, $max, 2) !== 1) {
            foreach ($deliveries as $delivery) {
                if ($delivery->slump_cm !== null && (bccomp((string) $delivery->slump_cm, $min, 2) === -1 || bccomp((string) $delivery->slump_cm, $max, 2) === 1)) {
                    $flags[] = ['code' => 'slump_out_of_spec', 'severity' => 'warning', 'detail' => "Delivery {$delivery->delivery_order_number}: slump {$delivery->slump_cm} cm di luar rentang {$min}–{$max} cm."];
                }
            }
        }

        // 4. Pile sudah melewati casting tetapi tidak punya jadwal/hasil uji sama sekali.
        if (in_array($pile->status, ['testing', 'completed'], true) && $tests->isEmpty()) {
            $flags[] = ['code' => 'missing_test', 'severity' => 'critical', 'detail' => 'Belum ada pengujian pile (PIT/PDA/CSL/SLT/DLT) padahal fase pengujian/completed.'];
        }

        // 5. Cage ditolak QC pada pile ini.
        foreach ($cages as $cage) {
            if ($cage->qc_status === 'failed') {
                $flags[] = ['code' => 'rejected_cage', 'severity' => 'critical', 'detail' => "Cage {$cage->number} berstatus gagal QC namun tercatat terkirim ke titik ini."];
            }
        }

        // 6. Completed tanpa satu pun foto evidence.
        if ($pile->status === 'completed' && $evidences->isEmpty()) {
            $flags[] = ['code' => 'missing_evidence', 'severity' => 'warning', 'detail' => 'Status completed tetapi belum ada foto evidence apa pun.'];
        }

        return $flags;
    }

    /** Catat akses genealogy/as-built untuk jejak audit dokumen teknis. */
    public function recordViewed(BoredPile $pile, User $actor, string $context): void
    {
        $this->audit->record($pile->project->company_id, $actor->id, $context, $pile);
    }
}
