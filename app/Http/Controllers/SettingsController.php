<?php

namespace App\Http\Controllers;

use App\Models\ApprovalWorkflow;
use App\Models\CompanySetting;
use App\Models\NumberSequence;
use App\Models\SignatureProvider;
use App\Models\TaxRate;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $user = $request->user();

        return view('settings.index', [
            'canFinance' => $user->hasPermission('finance.manage', $companyId),
            'canApprove' => $user->hasPermission('approval.view', $companyId),
            'canSignature' => $user->hasPermission('signature.view', $companyId),
            'canOrganization' => $user->hasPermission('organization.view', $companyId),
            'values' => collect(CompanySetting::DEFAULTS)->mapWithKeys(fn ($default, $key) => [$key => CompanySetting::val($companyId, $key)]),
            'taxRates' => TaxRate::where('company_id', $companyId)->count(),
            'activeTaxRates' => TaxRate::where('company_id', $companyId)->where('is_active', true)->count(),
            'workflows' => ApprovalWorkflow::where('company_id', $companyId)->where('is_active', true)->count(),
            'sequences' => NumberSequence::where('company_id', $companyId)->orderBy('document_type')->get(),
            'providers' => SignatureProvider::where('company_id', $companyId)->count(),
        ]);
    }

    public function save(Request $request, CurrentCompany $current)
    {
        $data = $request->validate([
            'company_address' => ['nullable', 'max:300'],
            'company_phone' => ['nullable', 'max:40'],
            'company_email' => ['nullable', 'email', 'max:120'],
            'company_npwp' => ['nullable', 'max:40'],
            'default_payment_term_days' => ['required', 'integer', 'between:0,365'],
            'default_vendor_payment_term_days' => ['required', 'integer', 'between:0,365'],
            'default_retention_percent' => ['required', 'decimal:0,4', 'between:0,100'],
            'default_ppn_percent' => ['required', 'decimal:0,4', 'between:0,100'],
            'default_overbreak_tolerance_percent' => ['required', 'decimal:0,3', 'between:0,100'],
            'pile_depth_tolerance_percent' => ['required', 'decimal:0,3', 'between:0,100'],
            'slump_min_cm' => ['required', 'decimal:0,2', 'between:0,50'],
            'slump_max_cm' => ['required', 'decimal:0,2', 'between:0,50'],
            'bid_weight_margin' => ['required', 'numeric', 'between:0,100'],
            'bid_weight_hps' => ['required', 'numeric', 'between:0,100'],
            'bid_weight_competition' => ['required', 'numeric', 'between:0,100'],
            'bid_weight_payment' => ['required', 'numeric', 'between:0,100'],
            'bid_threshold_recommended' => ['required', 'numeric', 'between:1,100'],
            'bid_threshold_no_bid' => ['required', 'numeric', 'between:0,99'],
            'require_pile_test_pass' => ['nullable', 'boolean'],
            'require_cleaning_inspection' => ['nullable', 'boolean'],
            'require_jsa_active' => ['nullable', 'boolean'],
            'sediment_max_mm' => ['nullable', 'decimal:0,2', 'between:0,9999'],
            'invoice_footer_note' => ['nullable', 'max:500'],
        ]);
        abort_unless($request->user()->hasPermission('finance.manage', $current->id()), 403);
        $data['require_pile_test_pass'] = $request->boolean('require_pile_test_pass') ? '1' : '0';
        $data['require_cleaning_inspection'] = $request->boolean('require_cleaning_inspection') ? '1' : '0';
        $data['require_jsa_active'] = $request->boolean('require_jsa_active') ? '1' : '0';
        CompanySetting::put($current->id(), array_map(fn ($v) => (string) $v, $data));

        return back()->with('status', 'Pengaturan perusahaan tersimpan.');
    }
}
