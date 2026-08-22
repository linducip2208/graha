<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\CompanySetting;
use App\Models\ReinforcementCage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReinforcementCageService
{
    public function __construct(private AuditTrail $audit) {}

    public function create(int $companyId, array $data, User $actor): ReinforcementCage
    {
        return DB::transaction(function () use ($companyId, $data, $actor) {
            throw_if(ReinforcementCage::where('company_id', $companyId)->where('number', $data['number'])->exists(), ValidationException::withMessages(['number' => 'Nomor cage sudah dipakai.']));
            throw_if(bccomp((string) $data['total_length_m'], '0', 3) !== 1, ValidationException::withMessages(['total_length_m' => 'Panjang harus positif.']));
            $cage = ReinforcementCage::create([...$data, 'company_id' => $companyId, 'qc_status' => 'draft', 'created_by' => $actor->id]);
            $this->audit->record($companyId, $actor->id, 'manufacturing.cage_created', $cage);

            return $cage;
        }, 3);
    }

    /** QC independen: pembuat cage tidak boleh memeriksa sendiri. */
    public function recordQc(ReinforcementCage $cage, bool $passed, ?string $notes, User $actor): ReinforcementCage
    {
        return DB::transaction(function () use ($cage, $passed, $notes, $actor) {
            $cage = ReinforcementCage::lockForUpdate()->findOrFail($cage->id);
            throw_unless($actor->companies()->whereKey($cage->company_id)->where('company_user.is_active', true)->exists(), ValidationException::withMessages(['company' => 'Anda bukan anggota aktif perusahaan ini.']));
            throw_if((int) $cage->created_by === (int) $actor->id, ValidationException::withMessages(['qc' => 'Pembuat cage tidak boleh memeriksa sendiri.']));
            throw_unless($cage->qc_status === 'draft', ValidationException::withMessages(['status' => 'QC sudah final.']));
            if ($passed) {
                throw_unless($cage->actual_weight_kg !== null, ValidationException::withMessages(['actual_weight_kg' => 'Timbangan aktual wajib diisi sebelum QC lolos.']));
                $tolerance = (float) CompanySetting::val($cage->company_id, 'steel_variance_tolerance_percent');
                $variance = abs((float) $cage->weightVariancePercent());
                throw_if($variance > $tolerance, ValidationException::withMessages(['actual_weight_kg' => "Selisih berat baja {$variance}% melebihi toleransi {$tolerance}%. Perbaiki data atau tinjau ulang."]));
            }
            $cage->update([
                'qc_status' => $passed ? 'passed' : 'failed',
                'qc_notes' => $notes,
                'qc_by' => $actor->id,
                'qc_at' => now(),
            ]);
            $this->audit->record($cage->company_id, $actor->id, 'manufacturing.cage_qc', $cage);

            return $cage->refresh();
        }, 3);
    }

    public function deliverToPile(ReinforcementCage $cage, BoredPile $pile, User $actor): ReinforcementCage
    {
        return DB::transaction(function () use ($cage, $pile, $actor) {
            $cage = ReinforcementCage::lockForUpdate()->findOrFail($cage->id);
            $pile = BoredPile::lockForUpdate()->findOrFail($pile->id);
            throw_unless($cage->company_id === $pile->project->company_id, ValidationException::withMessages(['pile' => 'Titik pile berbeda perusahaan.']));
            throw_unless($cage->qc_status === 'passed', ValidationException::withMessages(['qc' => 'Hanya cage lolos QC yang dapat dikirim ke titik.']));
            throw_unless(in_array($pile->status, ['cleaning', 'inspection', 'cage_installation'], true), ValidationException::withMessages(['pile' => "Status pile {$pile->status} belum siap menerima cage."]));
            throw_if(ReinforcementCage::where('bored_pile_id', $pile->id)->where('qc_status', 'passed')->whereKey($cage->id)->exists() === false && ReinforcementCage::where('bored_pile_id', $pile->id)->whereNotNull('delivered_at')->exists(), ValidationException::withMessages(['pile' => 'Titik pile sudah mempunyai cage terkirim.']));
            $cage->update(['bored_pile_id' => $pile->id, 'delivered_at' => now()]);
            $this->audit->record($cage->company_id, $actor->id, 'manufacturing.cage_delivered', $cage);

            return $cage->refresh();
        }, 3);
    }

    /** Gate opsional (setting require_cage_passed): transisi inspection -> cage_installation butuh minimal satu cage passed terkirim. */
    public function installationGate(BoredPile $pile): void
    {
        if (CompanySetting::val($pile->project->company_id, 'require_cage_passed') !== '1') {
            return;
        }
        $ok = ReinforcementCage::where('bored_pile_id', $pile->id)->where('qc_status', 'passed')->whereNotNull('delivered_at')->exists();
        throw_unless($ok, ValidationException::withMessages(['status' => 'Butuh minimal satu cage lolos QC dan terkirim sebelum cage installation.']));
    }
}
