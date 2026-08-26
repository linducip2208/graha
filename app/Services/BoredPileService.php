<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BoredPileService
{
    public const TRANSITIONS = [
        'planned' => ['setting_out', 'hold'], 'setting_out' => ['drilling', 'hold'], 'drilling' => ['cleaning', 'hold'],
        'cleaning' => ['inspection', 'hold'], 'inspection' => ['cage_installation', 'rework', 'rejected', 'hold'],
        'cage_installation' => ['casting', 'hold'], 'casting' => ['testing', 'hold'], 'testing' => ['completed', 'rework', 'rejected'],
        'hold' => ['planned', 'setting_out', 'drilling', 'cleaning', 'inspection', 'cage_installation', 'casting', 'testing'], 'rework' => ['drilling', 'inspection'],
    ];

    public function transition(BoredPile $pile, string $to, User $actor, ?string $notes = null): BoredPile
    {
        return DB::transaction(function () use ($pile, $to, $actor, $notes) {
            $pile = BoredPile::lockForUpdate()->findOrFail($pile->id);
            $from = $pile->status;
            throw_unless(in_array($to, self::TRANSITIONS[$from] ?? [], true), ValidationException::withMessages(['status' => "Transisi {$from} ke {$to} tidak diizinkan."]));
            if ($to === 'completed') {
                app(FieldOpsService::class)->completionGate($pile);
            }
            if ($to === 'cage_installation') {
                app(ReinforcementCageService::class)->installationGate($pile);
            }
            $pile->activities()->whereNull('finished_at')->latest()->first()?->update(['finished_at' => now()]);
            $pile->activities()->create(['from_status' => $from, 'to_status' => $to, 'started_at' => now(), 'notes' => $notes, 'recorded_by' => $actor->id]);
            $pile->update(['status' => $to]);

            return $pile->refresh();
        }, 3);
    }

    public function recordConcrete(BoredPile $pile, string $actualDepth, string $actualConcrete, User $actor): BoredPile
    {
        return DB::transaction(function () use ($pile, $actualDepth, $actualConcrete, $actor) {
            $pile = BoredPile::with('project')->lockForUpdate()->findOrFail($pile->id);
            throw_if(bccomp($actualConcrete, '0', 4) < 0 || bccomp($actualDepth, '0', 3) <= 0, ValidationException::withMessages(['quantity' => 'Kedalaman dan beton harus valid.']));
            $diameterM = bcdiv((string) $pile->diameter_mm, '1000', 8);
            $radius = bcdiv($diameterM, '2', 8);
            $theoreticalRaw = bcmul(bcmul('3.14159265', bcmul($radius, $radius, 8), 8), $actualDepth, 8);
            $theoretical = bcadd($theoreticalRaw, '0.00005', 4);
            $overbreak = bccomp($theoretical, '0', 4) === 1 ? bcmul(bcdiv(bcsub($actualConcrete, $theoretical, 4), $theoretical, 8), '100', 3) : '0.000';
            $exceeded = bccomp($overbreak, (string) $pile->project->overbreak_tolerance_percent, 3) === 1;
            $pile->update(['actual_depth_m' => $actualDepth, 'theoretical_concrete_m3' => $theoretical, 'actual_concrete_m3' => $actualConcrete, 'overbreak_percent' => $overbreak, 'overbreak_exceeded' => $exceeded]);
            app(AuditTrail::class)->record($pile->project->company_id, $actor->id, 'bored_pile.concrete_recorded', $pile);

            return $pile->refresh();
        }, 3);
    }
}
