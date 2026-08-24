<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CasingUnit;
use App\Models\ConstraintLog;
use App\Models\Equipment;
use App\Models\FuelTank;
use App\Models\ProcurementPlan;
use App\Models\ReinforcementCage;
use App\Models\Tool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetApiController extends Controller
{
    private function company(Request $request): int
    {
        $user = $request->user();
        $memberships = $user->companies()->where('company_user.is_active', true)->pluck('companies.id');
        $requested = (int) ($request->header('X-Company-Id', 0));
        if ($requested > 0) {
            abort_unless($memberships->contains($requested), 403, 'Anda bukan anggota perusahaan tersebut.');

            return $requested;
        }
        abort_if($memberships->isEmpty(), 403, 'Tidak ada membership aktif.');

        return (int) $memberships->first();
    }

    public function cages(Request $request): JsonResponse
    {
        $companyId = $this->company($request);
        abort_unless($request->user()->hasPermission('manufacturing.view', $companyId), 403, 'Butuh permission manufacturing.view.');

        return response()->json(['data' => ReinforcementCage::where('company_id', $companyId)
            ->when($request->query('qc_status'), fn ($q, $v) => $q->where('qc_status', $v))
            ->with('pile:id,pile_number')->orderByDesc('id')->paginate(50)]);
    }

    public function casings(Request $request): JsonResponse
    {
        $companyId = $this->company($request);
        abort_unless($request->user()->hasPermission('equipment.view', $companyId), 403, 'Butuh permission equipment.view.');

        return response()->json(['data' => CasingUnit::where('company_id', $companyId)
            ->with('currentPile:id,pile_number')->orderBy('code')->get()]);
    }

    public function fuelTanks(Request $request): JsonResponse
    {
        $companyId = $this->company($request);
        abort_unless($request->user()->hasPermission('equipment.view', $companyId), 403, 'Butuh permission equipment.view.');

        return response()->json(['data' => FuelTank::where('company_id', $companyId)->orderBy('code')->get()]);
    }

    public function tools(Request $request): JsonResponse
    {
        $companyId = $this->company($request);
        abort_unless($request->user()->hasPermission('inventory.view', $companyId), 403, 'Butuh permission inventory.view.');

        return response()->json(['data' => Tool::where('company_id', $companyId)
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('code')->get()]);
    }

    public function equipment(Request $request): JsonResponse
    {
        $companyId = $this->company($request);
        abort_unless($request->user()->hasPermission('equipment.view', $companyId), 403, 'Butuh permission equipment.view.');

        return response()->json(['data' => Equipment::where('company_id', $companyId)
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('code')->get()]);
    }

    /** Constraint log proyek (ADR-049): transisi terjaga via UI/service; API read-only. */
    public function constraints(Request $request): JsonResponse
    {
        $companyId = $this->company($request);
        abort_unless($request->user()->hasPermission('project.view', $companyId), 403, 'Butuh permission project.view.');
        $projectId = (int) $request->query('project_id', 0);
        abort_if($projectId === 0 || ! \App\Models\Project::where('company_id', $companyId)->whereKey($projectId)->exists(), 404, 'Proyek tidak ditemukan di perusahaan ini.');

        return response()->json(['data' => ConstraintLog::where('company_id', $companyId)
            ->where('project_id', $projectId)
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('raised_at')->orderByDesc('id')->limit(200)->get()]);
    }

    /** Rencana pengadaan proyek (ADR-050) + status taut PR/PO. */
    public function procurementPlans(Request $request): JsonResponse
    {
        $companyId = $this->company($request);
        abort_unless($request->user()->hasPermission('procurement.view', $companyId), 403, 'Butuh permission procurement.view.');
        $projectId = (int) $request->query('project_id', 0);
        abort_if($projectId === 0 || ! \App\Models\Project::where('company_id', $companyId)->whereKey($projectId)->exists(), 404, 'Proyek tidak ditemukan di perusahaan ini.');

        return response()->json(['data' => ProcurementPlan::where('company_id', $companyId)
            ->where('project_id', $projectId)
            ->with('item:id,sku,name')->orderBy('required_date')->get()
            ->map(fn ($plan) => array_merge($plan->toArray(), [
                'is_late' => $plan->purchase_order_id === null && $plan->purchase_request_id === null && $plan->planned_po_date !== null && $plan->planned_po_date < now()->toDateString(),
            ]))]);
    }
}
