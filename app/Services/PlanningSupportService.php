<?php

namespace App\Services;

use App\Models\ConstraintLog;
use App\Models\ProcurementPlan;
use App\Models\Project;
use App\Models\ProjectWbs;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
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

    /** Vendor lifecycle (ADR-054): suspend/blacklist wajib catatan; hanya approved boleh transaksi. */
    public function setVendorStatus(int $companyId, int $vendorId, string $status, ?string $note, User $actor): Vendor
    {
        return DB::transaction(function () use ($companyId, $vendorId, $status, $note, $actor) {
            throw_unless(in_array($status, ['approved', 'suspended', 'blacklisted'], true), ValidationException::withMessages(['status' => 'Status vendor tidak dikenal.']));
            $vendor = Vendor::where('company_id', $companyId)->lockForUpdate()->find($vendorId);
            throw_unless($vendor, ValidationException::withMessages(['vendor' => 'Vendor tidak ditemukan.']));
            if (in_array($status, ['suspended', 'blacklisted'], true)) {
                throw_unless(filled($note), ValidationException::withMessages(['note' => 'Penangguhan/blacklist wajib menyertakan alasan.']));
            }
            $vendor->update([
                'status' => $status,
                'qualified_at' => $status === 'approved' ? now() : $vendor->qualified_at,
                'status_note' => in_array($status, ['suspended', 'blacklisted'], true) ? mb_substr((string) $note, 0, 300) : null,
            ]);
            $this->audit->record($companyId, $actor->id, 'procurement.vendor_status_changed', $vendor);

            return $vendor->refresh();
        }, 3);
    }

    /** WBS node baru (ADR-055): parent satu proyek, kedalaman maksimum 4 level. */
    public function createWbs(int $projectId, array $data, User $actor): ProjectWbs
    {
        return DB::transaction(function () use ($projectId, $data, $actor) {
            $depth = 0;
            $parentId = $data['parent_id'] ?? null;
            $seen = [];
            while ($parentId !== null) {
                throw_if(isset($seen[$parentId]) || $depth > 10, ValidationException::withMessages(['parent_id' => 'Struktur parent tidak valid (siklus terdeteksi).']));
                $seen[$parentId] = true;
                $parent = ProjectWbs::find($parentId);
                throw_unless($parent && (int) $parent->project_id === $projectId, ValidationException::withMessages(['parent_id' => 'Parent WBS bukan milik proyek ini.']));
                $parentId = $parent->parent_id;
                $depth++;
            }
            throw_if($depth > 3, ValidationException::withMessages(['parent_id' => "Kedalaman maksimum 4 level (Project → Phase → Work Package → Activity); parent berada di level {$depth}."]));
            throw_unless(bccomp((string) ($data['budget'] ?? '0'), '0', 2) >= 0, ValidationException::withMessages(['budget' => 'Budget tidak boleh negatif.']));
            $wbs = ProjectWbs::create([...collect($data)->except('company_id')->all(), 'project_id' => $projectId]);
            $this->audit->record(Project::find($projectId)->company_id, $actor->id, 'planning.wbs_created', $wbs);

            return $wbs;
        }, 3);
    }

    /** Flatten pohon WBS menjadi daftar ber-indent (depth) untuk render. */
    public static function wbsTree(int $projectId)
    {
        $all = ProjectWbs::where('project_id', $projectId)->orderBy('code')->get()->keyBy('id');
        $children = [];
        foreach ($all as $node) {
            $children[$node->parent_id][] = $node;
        }
        $out = collect();
        $walk = function ($parentId, $depth) use (&$walk, &$out, $children): void {
            foreach ($children[$parentId] ?? [] as $node) {
                $node->depth = $depth;
                $out->push($node);
                $walk($node->id, $depth + 1);
            }
        };
        $walk(null, 0);

        return $out;
    }
}
