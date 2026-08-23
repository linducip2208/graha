<?php

namespace App\Services;

use App\Models\ConstraintLog;
use App\Models\ProcurementPlan;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Log kendala lapangan + rencana pengadaan proyek (ADR-049/050).
 * Transaksi kecil, guard perusahaan ketat, audit penuh.
 */
class PlanningSupportService
{
    public function __construct(private AuditTrail $audit) {}

    public function createConstraint(int $companyId, array $data, User $actor): ConstraintLog
    {
        return DB::transaction(function () use ($companyId, $data, $actor) {
            throw_unless(in_array($data['type'], ConstraintLog::TYPES, true), ValidationException::withMessages(['type' => 'Jenis kendala tidak dikenal.']));
            $log = ConstraintLog::create([...collect($data)->except('bored_pile_id')->all(), 'company_id' => $companyId, 'recorded_by' => $actor->id]);
            $this->audit->record($companyId, $actor->id, 'planning.constraint_created', $log);

            return $log;
        }, 3);
    }

    public function updateConstraintStatus(ConstraintLog $log, string $status, ?string $resolutionNotes, User $actor): ConstraintLog
    {
        return DB::transaction(function () use ($log, $status, $resolutionNotes, $actor) {
            $log = ConstraintLog::lockForUpdate()->findOrFail($log->id);
            throw_unless(in_array($status, ConstraintLog::STATUSES, true), ValidationException::withMessages(['status' => 'Status tidak dikenal.']));
            throw_unless($log->status !== 'resolved', ValidationException::withMessages(['status' => 'Kendala yang sudah selesai tidak dibuka lagi — buat entri baru bila muncul kembali.']));
            $allowed = match ($log->status) {
                'open' => ['in_progress', 'resolved'],
                'in_progress' => ['resolved'],
                default => [],
            };
            throw_unless(in_array($status, $allowed, true), ValidationException::withMessages(['status' => "Transisi {$log->status} → {$status} tidak diizinkan."]));
            $update = ['status' => $status];
            if ($status === 'resolved') {
                throw_unless(filled($resolutionNotes), ValidationException::withMessages(['resolution_notes' => 'Penyelesaian wajib menyertakan catatan.']));
                $update['resolved_at'] = now();
                $update['resolution_notes'] = $resolutionNotes;
            }
            $log->update($update);
            $this->audit->record($log->company_id, $actor->id, $status === 'resolved' ? 'planning.constraint_resolved' : 'planning.constraint_progress', $log);

            return $log->refresh();
        }, 3);
    }

    public function createPlan(int $companyId, array $data, User $actor): ProcurementPlan
    {
        return DB::transaction(function () use ($companyId, $data, $actor) {
            $plan = ProcurementPlan::create([...$data, 'company_id' => $companyId, 'created_by' => $actor->id]);
            $this->audit->record($companyId, $actor->id, 'procurement.plan_created', $plan);

            return $plan;
        }, 3);
    }

    /** Tautkan hasil eksekusi nyata (PR/PO) ke baris rencana; status mengikuti dokumen. */
    public function linkDocument(ProcurementPlan $plan, string $kind, int $documentId, User $actor): ProcurementPlan
    {
        return DB::transaction(function () use ($plan, $kind, $documentId, $actor) {
            $plan = ProcurementPlan::lockForUpdate()->findOrFail($plan->id);
            if ($kind === 'pr') {
                $plan->purchase_request_id = $documentId;
                if (in_array($plan->status, ['planned'], true)) {
                    $plan->status = 'pr_created';
                }
            } elseif ($kind === 'po') {
                throw_unless(PurchaseOrder::where('company_id', $plan->company_id)->whereKey($documentId)->exists(), ValidationException::withMessages(['document' => 'PO tidak ditemukan.']));
                $plan->purchase_order_id = $documentId;
                $plan->status = 'po_created';
            } else {
                throw ValidationException::withMessages(['kind' => 'Jenis dokumen tidak dikenal.']);
            }
            $plan->save();
            $this->audit->record($plan->company_id, $actor->id, 'procurement.plan_linked', $plan);

            return $plan->refresh();
        }, 3);
    }

    /** Rencana terlambat: jatuh tempo lewat tapi PO belum ada dan tidak dibatalkan. */
    public static function latePlansForCompany(int $companyId)
    {
        return ProcurementPlan::where('company_id', $companyId)
            ->whereIn('status', ['planned', 'pr_created'])
            ->whereDate('required_date', '<', today())
            ->with(['project:id,code,name', 'item:id,name'])
            ->orderBy('required_date')
            ->limit(20)
            ->get();
    }
}
