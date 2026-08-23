<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CasingUnit;
use App\Models\Equipment;
use App\Models\FuelTank;
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
}
