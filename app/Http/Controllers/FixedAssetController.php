<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AssetDepreciation;
use App\Models\FiscalPeriod;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\Project;
use App\Services\FixedAssetService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class FixedAssetController extends Controller
{
    public function index(CurrentCompany $current)
    {
        $id = $current->id();

        return view('fixed-assets.index', ['categories' => FixedAssetCategory::where('company_id', $id)->orderBy('code')->get(), 'assets' => FixedAsset::where('company_id', $id)->with('category')->orderBy('code')->get(), 'periods' => FiscalPeriod::where('company_id', $id)->where('status', 'open')->get(), 'projects' => Project::where('company_id', $id)->orderBy('code')->get(), 'accounts' => Account::where('company_id', $id)->where('is_active', true)->orderBy('code')->get(), 'depreciations' => AssetDepreciation::where('company_id', $id)->with('asset')->latest('depreciation_date')->limit(100)->get()]);
    }

    public function category(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['code' => ['required', 'max:50'], 'name' => ['required', 'max:150'], 'default_useful_life_months' => ['required', 'integer', 'min:1', 'max:1200']]);
        FixedAssetCategory::create([...$data, 'company_id' => $current->id()]);

        return back()->with('status', 'Kategori aset ditambahkan.');
    }

    public function asset(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['fixed_asset_category_id' => ['required', 'integer'], 'project_id' => ['nullable', 'integer'], 'code' => ['required', 'max:80'], 'name' => ['required', 'max:150'], 'acquisition_date' => ['required', 'date'], 'depreciation_start_date' => ['required', 'date', 'after_or_equal:acquisition_date'], 'acquisition_cost' => ['required', 'decimal:0,2', 'gt:0'], 'residual_value' => ['required', 'decimal:0,2', 'min:0', 'lt:acquisition_cost'], 'useful_life_months' => ['required', 'integer', 'min:1', 'max:1200']]);
        abort_unless(FixedAssetCategory::where('company_id', $current->id())->whereKey($data['fixed_asset_category_id'])->exists(), 422);
        if ($data['project_id'] ?? null) {
            abort_unless(Project::where('company_id', $current->id())->whereKey($data['project_id'])->exists(), 422);
        }
        FixedAsset::create([...$data, 'company_id' => $current->id(), 'created_by' => $request->user()->id]);

        return back()->with('status', 'Aset tetap ditambahkan.');
    }

    public function depreciate(Request $request, FixedAsset $asset, CurrentCompany $current, FixedAssetService $service)
    {
        abort_unless($asset->company_id === $current->id(), 404);
        $data = $request->validate(['fiscal_period_id' => ['required', 'integer'], 'depreciation_date' => ['required', 'date'], 'idempotency_key' => ['required', 'max:120']]);
        $period = FiscalPeriod::where('company_id', $current->id())->findOrFail($data['fiscal_period_id']);
        $service->depreciate($asset, $period, $data['depreciation_date'], $data['idempotency_key'], $request->user());

        return back()->with('status', 'Depresiasi diposting.');
    }

    public function dispose(Request $request, FixedAsset $asset, CurrentCompany $current, FixedAssetService $service)
    {
        abort_unless($asset->company_id === $current->id(), 404);
        $data = $request->validate(['disposal_date' => ['required', 'date'], 'proceeds' => ['required', 'decimal:0,2', 'min:0'], 'idempotency_key' => ['required', 'max:120']]);
        $service->dispose($asset, $data['disposal_date'], $data['proceeds'], $data['idempotency_key'], $request->user());

        return back()->with('status', 'Aset dilepas. Jurnal disposal (akumulasi, nilai perolehan, hasil jual, laba/rugi) diposting.');
    }
}
