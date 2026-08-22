<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\Warehouse;
use App\Services\StockOpnameService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class StockOpnameController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();

        return view('inventory.opname', [
            'warehouses' => Warehouse::where('company_id', $companyId)->with('bins')->get(),
            'counts' => StockCount::where('company_id', $companyId)->with(['warehouse', 'lines.item'])->orderByDesc('id')->limit(30)->get(),
            'balances' => StockBalance::where('company_id', $companyId)->with(['item', 'bin'])->get(),
        ]);
    }

    public function store(Request $request, CurrentCompany $current, StockOpnameService $service)
    {
        $data = $request->validate([
            'number' => ['required', 'max:80'],
            'warehouse_id' => ['required', 'integer'],
            'notes' => ['nullable', 'max:500'],
            'lines' => ['required', 'string'],
        ]);
        abort_unless(Warehouse::where('company_id', $current->id())->whereKey($data['warehouse_id'])->exists(), 422);
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($data['lines'])) ?: [] as $index => $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                return back()->withErrors(['lines' => 'Baris '.($index + 1).' harus format: SKU|hasil_hitung.'])->withInput();
            }
            $item = Item::where('company_id', $current->id())->where('sku', $parts[0])->first();
            if (! $item) {
                return back()->withErrors(['lines' => "SKU {$parts[0]} tidak ditemukan."])->withInput();
            }
            $lines[] = ['item_id' => $item->id, 'counted_quantity' => $parts[1]];
        }
        unset($data['lines']);
        abort_unless(Item::where('company_id', $current->id())->exists(), 422);
        $service->create($current->id(), $data, $lines, $request->user());

        return back()->with('status', 'Opname tersimpan sebagai draft. Approval oleh user lain akan memposting adjustment.');
    }

    public function approve(Request $request, StockCount $count, CurrentCompany $current, StockOpnameService $service)
    {
        abort_unless($count->company_id === $current->id(), 404);
        $service->approve($count, $request->user());

        return back()->with('status', 'Opname disetujui; adjustment diposting ke ledger.');
    }
}
