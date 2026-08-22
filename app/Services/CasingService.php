<?php

namespace App\Services;

use App\Models\CasingMovement;
use App\Models\CasingUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CasingService
{
    /** Aturan perpindahan status casing; setiap gerak tercatat di history. */
    private const RULES = [
        'installed' => ['in_stock', 'extracted', 'repaired'],
        'extracted' => ['installed'],
        'left_in_pile' => ['installed'],
        'damage_reported' => ['in_stock', 'installed', 'extracted', 'repaired'],
        'repaired' => ['damage_reported'],
        'lost' => ['in_stock', 'installed', 'extracted', 'damage_reported'],
    ];

    public function __construct(private AuditTrail $audit) {}

    public function create(int $companyId, array $data, User $actor): CasingUnit
    {
        return DB::transaction(function () use ($companyId, $data, $actor) {
            throw_if(CasingUnit::where('company_id', $companyId)->where('code', $data['code'])->exists(), ValidationException::withMessages(['code' => 'Kode casing sudah dipakai.']));
            $unit = CasingUnit::create([...$data, 'company_id' => $companyId, 'created_by' => $actor->id]);
            $this->audit->record($companyId, $actor->id, 'equipment.casing_created', $unit);

            return $unit;
        }, 3);
    }

    public function move(CasingUnit $unit, string $type, ?int $pileId, ?string $notes, float|string $cost, Carbon $occurredAt, User $actor): CasingUnit
    {
        return DB::transaction(function () use ($unit, $type, $pileId, $notes, $cost, $occurredAt, $actor) {
            $unit = CasingUnit::lockForUpdate()->findOrFail($unit->id);
            throw_unless($actor->companies()->whereKey($unit->company_id)->where('company_user.is_active', true)->exists(), ValidationException::withMessages(['company' => 'Anda bukan anggota aktif perusahaan ini.']));
            $allowed = self::RULES[$type] ?? [];
            throw_unless(in_array($unit->status, $allowed, true), ValidationException::withMessages(['type' => "Casing berstatus {$unit->status} tidak dapat {$type}."]));
            $update = ['status' => $type === 'left_in_pile' ? 'left_in_pile' : $type];
            if ($type === 'installed') {
                throw_unless($pileId, ValidationException::withMessages(['bored_pile_id' => 'Instalasi wajib menyebut titik pile.']));
                $update['current_bored_pile_id'] = $pileId;
                $update['usage_cycle_count'] = $unit->usage_cycle_count + 1;
            } elseif (in_array($type, ['extracted', 'left_in_pile'], true)) {
                if ($type === 'extracted') {
                    $update['status'] = 'extracted';
                    $update['current_bored_pile_id'] = null;
                }
            }
            if ((float) $cost > 0) {
                $update['rental_cost_total'] = bcadd((string) $unit->rental_cost_total, (string) $cost, 2);
            }
            $movement = CasingMovement::create(['casing_unit_id' => $unit->id, 'type' => $type, 'bored_pile_id' => $pileId, 'occurred_at' => $occurredAt, 'notes' => $notes, 'cost' => (string) $cost, 'recorded_by' => $actor->id]);
            $unit->update($update);
            $this->audit->record($unit->company_id, $actor->id, 'equipment.casing_moved', $movement);

            return $unit->refresh();
        }, 3);
    }
}
