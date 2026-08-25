<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\CompanySetting;
use App\Models\SlurryTest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Slurry Control (ADR-074): evaluasi limit dari settings company —
 * TIDAK ada standar nilai yang di-hardcode; default semua OFF.
 * Tanpa kebijakan aktif: data hanya terekam, tidak menjadi gate.
 */
class SlurryControlService
{
    public const LIMITS = [
        'density_min' => ['key' => 'slurry_density_min', 'label' => 'Density min', 'field' => 'density'],
        'density_max' => ['key' => 'slurry_density_max', 'label' => 'Density maks', 'field' => 'density'],
        'viscosity_min' => ['key' => 'slurry_viscosity_min', 'label' => 'Viskositas min', 'field' => 'viscosity'],
        'viscosity_max' => ['key' => 'slurry_viscosity_max', 'label' => 'Viskositas maks', 'field' => 'viscosity'],
        'ph_min' => ['key' => 'slurry_ph_min', 'label' => 'pH min', 'field' => 'ph'],
        'ph_max' => ['key' => 'slurry_ph_max', 'label' => 'pH maks', 'field' => 'ph'],
        'sand_content_max' => ['key' => 'slurry_sand_content_max', 'label' => 'Sand content maks', 'field' => 'sand_content_percent'],
    ];

    /** Kebijakan slurry aktif untuk company? */
    public function policyEnabled(int $companyId): bool
    {
        return CompanySetting::val($companyId, 'slurry_policy_enabled') === '1';
    }

    /**
     * Pelanggaran limit uji terhadap kebijakan company (hanya limit terisi).
     *
     * @return array<int, array{code: string, label: string, value: string, limit: string}>
     */
    public function violations(SlurryTest $test): array
    {
        if (! $this->policyEnabled($test->company_id)) {
            return [];
        }
        $violations = [];
        foreach (self::LIMITS as $code => $limit) {
            $bound = CompanySetting::val($test->company_id, $limit['key']);
            $value = $test->{$limit['field']};
            if (! filled($bound) || $value === null) {
                continue;
            }
            $isMax = str_contains($code, '_max');
            $out = $isMax
                ? bccomp((string) $value, $bound, 4) === 1
                : bccomp((string) $value, $bound, 4) === -1;
            if ($out) {
                $violations[] = ['code' => $code, 'label' => $limit['label'], 'value' => (string) $value, 'limit' => $bound];
            }
        }

        return $violations;
    }

    public function record(BoredPile $pile, array $data, User $actor): SlurryTest
    {
        return DB::transaction(function () use ($pile, $data, $actor) {
            throw_unless(in_array($data['phase'], SlurryTest::PHASES, true), ValidationException::withMessages(['phase' => 'Fase slurry tidak dikenal.']));
            throw_unless(in_array($data['type'], SlurryTest::TYPES, true), ValidationException::withMessages(['type' => 'Jenis slurry tidak dikenal.']));

            $test = SlurryTest::create([
                ...$data,
                'company_id' => $pile->project->company_id,
                'bored_pile_id' => $pile->id,
                'sampled_by' => $data['sampled_by'] ?? $actor->id,
                'status' => 'pending',
            ]);
            app(AuditTrail::class)->record($pile->project->company_id, $actor->id, 'field.slurry_test_recorded', $test);

            return $test;
        });
    }

    /** Keputusan accept/reject oleh QA — manusia memutuskan, service hanya melaporkan violations. */
    public function decide(SlurryTest $test, string $decision, User $actor): SlurryTest
    {
        return DB::transaction(function () use ($test, $decision, $actor) {
            $test = SlurryTest::lockForUpdate()->findOrFail($test->id);
            throw_unless($test->status === 'pending', ValidationException::withMessages(['status' => 'Uji slurry sudah diputuskan.']));
            throw_unless(in_array($decision, ['accepted', 'rejected'], true), ValidationException::withMessages(['decision' => 'Keputusan harus accepted atau rejected.']));
            $test->update(['status' => $decision, 'verified_by' => $actor->id, 'verified_at' => now()]);
            app(AuditTrail::class)->record($test->company_id, $actor->id, 'field.slurry_test_'.$decision, $test);

            return $test->refresh();
        });
    }

    /** Uji before_drilling terakhir accepted → syarat readiness drilling bila policy aktif. */
    public function preDrillAccepted(BoredPile $pile): bool
    {
        return SlurryTest::where('bored_pile_id', $pile->id)
            ->where('phase', 'before_drilling')->where('status', 'accepted')
            ->exists();
    }

    /** Uji before_casting terakhir accepted → syarat readiness casting bila policy aktif. */
    public function preCastAccepted(BoredPile $pile): bool
    {
        return SlurryTest::where('bored_pile_id', $pile->id)
            ->where('phase', 'before_casting')->where('status', 'accepted')
            ->exists();
    }
}
