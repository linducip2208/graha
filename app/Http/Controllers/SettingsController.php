<?php

namespace App\Http\Controllers;

use App\Models\ApprovalWorkflow;
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
            'taxRates' => TaxRate::where('company_id', $companyId)->count(),
            'activeTaxRates' => TaxRate::where('company_id', $companyId)->where('is_active', true)->count(),
            'workflows' => ApprovalWorkflow::where('company_id', $companyId)->where('is_active', true)->count(),
            'sequences' => NumberSequence::where('company_id', $companyId)->orderBy('document_type')->get(),
            'providers' => SignatureProvider::where('company_id', $companyId)->count(),
        ]);
    }
}
