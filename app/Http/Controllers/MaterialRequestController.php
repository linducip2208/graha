<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\Item;
use App\Models\MaterialRequest;
use App\Models\Project;
use App\Models\Warehouse;
use App\Services\MaterialRequestService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class MaterialRequestController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();

        return view('inventory.material-requests', [
            'requests' => MaterialRequest::where('company_id', $companyId)->with(['project', 'lines.item'])->orderByDesc('id')->limit(30)->get(),
            'projects' => Project::where('company_id', $companyId)->whereIn('status', ['active', 'in_progress'])->orderBy('code')->get(),
            'warehouses' => Warehouse::where('company_id', $companyId)->orderBy('code')->get(),
            'piles' => BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $companyId))->with('project')->get(),
        ]);
    }

    public function store(Request $request, CurrentCompany $current, MaterialRequestService $service)
    {
        $data = $request->validate([
            'number' => ['required', 'max:80'],
            'project_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'bored_pile_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'max:500'],
            'lines' => ['required', 'string'],
        ]);
        abort_unless(Project::where('company_id', $current->id())->whereKey($data['project_id'])->exists(), 422);
        abort_unless(Warehouse::where('company_id', $current->id())->whereKey($data['warehouse_id'])->exists(), 422);
        if (! empty($data['bored_pile_id'])) {
            abort_unless(BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $current->id()))->whereKey($data['bored_pile_id'])->exists(), 422);
        } else {
            unset($data['bored_pile_id']);
        }
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($data['lines'])) ?: [] as $index => $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2) {
                return back()->withErrors(['lines' => 'Baris '.($index + 1).' harus format: SKU|qty.'])->withInput();
            }
            $item = Item::where('company_id', $current->id())->where('sku', $parts[0])->first();
            if (! $item) {
                return back()->withErrors(['lines' => "SKU {$parts[0]} tidak ditemukan."])->withInput();
            }
            $lines[] = ['item_id' => $item->id, 'quantity' => $parts[1]];
        }
        unset($data['lines']);
        $service->create($current->id(), $data, $lines, $request->user());

        return back()->with('status', 'Permintaan material dibuat — menunggu approval user lain.');
    }

    public function approve(Request $request, MaterialRequest $material_request, CurrentCompany $current, MaterialRequestService $service)
    {
        abort_unless($material_request->company_id === $current->id(), 404);
        $service->approve($material_request, $request->user());

        return back()->with('status', 'Permintaan di-approve — siap diterbitkan dari gudang.');
    }

    public function returnLine(Request $request, MaterialRequest $material_request, int $line, CurrentCompany $current, MaterialRequestService $service)
    {
        abort_unless($material_request->company_id === $current->id(), 404);
        $data = $request->validate(['quantity' => ['required', 'decimal:0,4', 'gt:0']]);
        $service->returnLine($material_request->refresh(), $line, $data['quantity'], $request->user());

        return back()->with('status', 'Material dikembalikan ke gudang; jurnal & biaya proyek dikoreksi.');
    }

    public function issue(Request $request, MaterialRequest $material_request, CurrentCompany $current, MaterialRequestService $service)
    {
        abort_unless($material_request->company_id === $current->id(), 404);
        $result = $service->issue($material_request, $request->user());
        $fullyIssued = $result->lines()->whereColumn('issued_quantity', '<', 'quantity')->doesntExist();

        return back()->with('status', $fullyIssued
            ? 'Seluruh material diterbitkan; jurnal biaya proyek diposting.'
            : 'Sebagian diterbitkan; sisa baris masih menunggu stok.');
    }
}
