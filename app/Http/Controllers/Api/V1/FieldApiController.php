<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BoredPile;
use App\Models\Item;
use App\Models\Project;
use App\Models\ProjectDailyReport;
use App\Models\User;
use App\Services\MaterialRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class FieldApiController extends Controller
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

    public function token(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required'], 'device' => ['nullable', 'max:120']]);
        $user = User::where('email', $data['email'])->where('is_active', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Kredensial tidak valid.']);
        }

        return response()->json(['token' => $user->createToken($data['device'] ?? 'field-app')->plainTextToken, 'user' => ['id' => $user->id, 'name' => $user->name]]);
    }

    public function projects(Request $request): JsonResponse
    {
        $companyId = $this->company($request);

        return response()->json(['data' => Project::where('company_id', $companyId)->withCount('boredPiles')->orderBy('code')->paginate(25)]);
    }

    public function boredPiles(Request $request): JsonResponse
    {
        $companyId = $this->company($request);
        $data = $request->validate(['project_id' => ['required', 'integer']]);
        abort_unless(Project::where('company_id', $companyId)->whereKey($data['project_id'])->exists(), 404);
        $piles = BoredPile::where('project_id', $data['project_id'])->orderBy('pile_number')->get();

        return response()->json(['data' => $piles]);
    }

    public function storeDailyReport(Request $request): JsonResponse
    {
        $companyId = $this->company($request);
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'report_date' => ['required', 'date'],
            'weather' => ['nullable', 'max:60'],
            'manpower_count' => ['nullable', 'integer', 'min:0'],
            'work_summary' => ['required', 'max:3000'],
            'issues' => ['nullable', 'max:2000'],
        ]);
        abort_unless(Project::where('company_id', $companyId)->whereIn('status', ['active', 'in_progress'])->whereKey($data['project_id'])->exists(), 404);
        abort_unless($request->user()->hasPermission('project.manage', $companyId), 403, 'Butuh permission project.manage.');
        $report = ProjectDailyReport::create([...$data, 'prepared_by' => $request->user()->id]);

        return response()->json(['data' => $report], 201);
    }

    public function materialRequests(Request $request): JsonResponse
    {
        $companyId = $this->company($request);
        $requests = MaterialRequest::where('company_id', $companyId)->with('lines.item:id,sku,name')->orderByDesc('id')->limit(50)->get();

        return response()->json(['data' => $requests]);
    }

    public function storeMaterialRequest(Request $request): JsonResponse
    {
        $companyId = $this->company($request);
        $data = $request->validate([
            'number' => ['required', 'max:80'],
            'project_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'bored_pile_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sku' => ['required', 'max:60'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        abort_unless(Project::where('company_id', $companyId)->whereKey($data['project_id'])->exists(), 404);
        abort_unless($request->user()->hasPermission('inventory.manage', $companyId), 403, 'Butuh permission inventory.manage.');
        $service = app(MaterialRequestService::class);
        $lines = collect($data['lines'])->map(function (array $line) use ($companyId) {
            $item = Item::where('company_id', $companyId)->where('sku', $line['sku'])->first();
            abort_unless($item, 422, "SKU {$line['sku']} tidak ditemukan.");

            return ['item_id' => $item->id, 'quantity' => (string) $line['quantity']];
        })->all();
        unset($data['lines']);
        $mr = $service->create($companyId, $data, $lines, $request->user());

        return response()->json(['data' => $mr->load('lines.item')], 201);
    }
}
