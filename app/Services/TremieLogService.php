<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\CompanySetting;
use App\Models\PileTremieLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tremie Log (ADR-074): embedment dihitung deterministik dari panjang total
 * dan kedalaman ujung tremie. Flag normal/warning/out_of_range hanya INDIKATOR
 * — tidak pernah otomatis menolak pile. Batas min/max dari settings company.
 */
class TremieLogService
{
    public function __construct(private AuditTrail $audit) {}

    /** Fitur tremie log aktif untuk company? Default OFF (backward compatible). */
    public function enabled(int $companyId): bool
    {
        return CompanySetting::val($companyId, 'tremie_log_enabled') === '1';
    }

    public function record(BoredPile $pile, array $data, User $actor): PileTremieLog
    {
        return DB::transaction(function () use ($pile, $data, $actor) {
            $sequence = ((int) PileTremieLog::where('bored_pile_id', $pile->id)->max('sequence')) + 1;
            $embedment = $data['embedment_m'] ?? null;
            if (! filled($embedment)) {
                // Embedment = panjang tremie - kedalaman ujung (deterministik).
                $embedment = bcsub((string) $data['tremie_total_length_m'], (string) $data['tremie_tip_depth_m'], 2);
            }

            $log = PileTremieLog::create([
                ...$data,
                'company_id' => $pile->project->company_id,
                'bored_pile_id' => $pile->id,
                'sequence' => $sequence,
                'recorded_by' => $actor->id,
                'embedment_m' => max(0, (float) $embedment),
                'flag' => $this->flag($pile->project->company_id, (string) $embedment),
            ]);
            $this->audit->record($pile->project->company_id, $actor->id, 'field.tremie_log_recorded', $log);

            return $log;
        }, 3);
    }

    /** Flag rentang embedment; tanpa konfigurasi = normal (record only). */
    public function flag(int $companyId, string $embedment): string
    {
        $min = CompanySetting::val($companyId, 'tremie_min_embedment_m');
        $max = CompanySetting::val($companyId, 'tremie_max_embedment_m');
        if (! filled($min)) {
            return 'normal';
        }
        if (bccomp($embedment, $min, 2) === -1) {
            return 'out_of_range';
        }
        if (filled($max) && bccomp($embedment, $max, 2) === 1) {
            return bccomp(bcadd($max, '1.0', 2), $embedment, 2) === -1 ? 'out_of_range' : 'warning';
        }

        return 'normal';
    }

    /** Syarat readiness casting: minimal satu log tremie tercatat (hanya bila fitur aktif). */
    public function isReadyForCast(BoredPile $pile): bool
    {
        return PileTremieLog::where('bored_pile_id', $pile->id)->exists();
    }

    public function delete(BoredPile $pile, PileTremieLog $log, User $actor): void
    {
        throw_unless($log->bored_pile_id === $pile->id, ValidationException::withMessages(['log' => 'Log bukan milik pile ini.']));
        DB::transaction(function () use ($log, $actor) {
            $log->delete();
            $this->audit->record($log->company_id, $actor->id, 'field.tremie_log_deleted', $log);
        });
    }
}
