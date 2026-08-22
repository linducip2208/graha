<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\FuelTank;
use App\Models\FuelTankTransaction;
use App\Services\FuelTankService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class FuelTankController extends Controller
{
    public function index(Request $request, CurrentCompany $current, FuelTankService $service)
    {
        $companyId = $current->id();
        $tanks = FuelTank::where('company_id', $companyId)->withSum('transactions as balance', 'liters')->orderBy('code')->get();
        $selected = $tanks->firstWhere('id', (int) $request->query('tank')) ?? $tanks->first();

        return view('operations.fuel-tanks', [
            'tanks' => $tanks,
            'tank' => $selected,
            'transactions' => $selected ? FuelTankTransaction::where('fuel_tank_id', $selected->id)->with(['equipment', 'project'])->latest('occurred_at')->limit(50)->get() : collect(),
            'equipments' => Equipment::where('company_id', $companyId)->orderBy('name')->get(),
            'balance' => $selected ? $service->balance($selected) : '0',
        ]);
    }

    public function store(Request $request, CurrentCompany $current, FuelTankService $service)
    {
        $data = $request->validate([
            'code' => ['required', 'max:40'],
            'name' => ['required', 'max:120'],
            'capacity_l' => ['required', 'decimal:0,2', 'gt:0'],
            'opening_liters' => ['nullable', 'decimal:0,2', 'min:0'],
        ]);
        abort_unless(FuelTank::where('company_id', $current->id())->where('code', $data['code'])->doesntExist(), 422);
        $tank = FuelTank::create([...collect($data)->except('opening_liters')->all(), 'company_id' => $current->id()]);
        if (! empty($data['opening_liters'])) {
            $service->record($tank, [
                'type' => 'opening', 'occurred_at' => now(), 'reference' => 'saldo awal',
                'liters' => $data['opening_liters'], 'idempotency_key' => 'open:'.$tank->id,
                'project_id' => null, 'equipment_id' => null,
            ], $request->user());
        }

        return back()->with('status', 'Tangki BBM didaftarkan.');
    }

    public function record(Request $request, FuelTank $tank, CurrentCompany $current, FuelTankService $service)
    {
        abort_unless($tank->company_id === $current->id(), 404);
        $data = $request->validate([
            'type' => ['required', 'in:receipt,issue_to_equipment,issue_other,reading_adjustment'],
            'liters' => ['required', 'decimal:0,2', 'gt:0'],
            'equipment_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'max:120'],
            'notes' => ['nullable', 'max:500'],
            'idempotency_key' => ['required', 'max:120'],
        ]);
        if ($data['type'] === 'issue_to_equipment') {
            abort_unless(! empty($data['equipment_id']) && Equipment::where('company_id', $current->id())->whereKey($data['equipment_id'])->exists(), 422);
        } else {
            unset($data['equipment_id']);
        }
        $data['occurred_at'] = now();
        $service->record($tank->refresh(), $data, $request->user());

        return back()->with('status', 'Transaksi BBM tangki tercatat.');
    }

    public function reconcile(Request $request, FuelTank $tank, CurrentCompany $current, FuelTankService $service)
    {
        abort_unless($tank->company_id === $current->id(), 404);
        $data = $request->validate(['reading' => ['required', 'decimal:0,2']]);
        $result = $service->reconcile($tank, $data['reading'], $request->user());

        return back()->with('status', "Rekonsiliasi: buku {$result['book']}, fisik {$result['actual']}, selisih {$result['variance']} L".($result['adjusted'] ? ' — penyesuaian dicatat.' : ' — seimbang.'));
    }
}
