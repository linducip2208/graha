<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\CorrectiveAction;
use App\Models\DocumentSignature;
use App\Models\HseIncidentAction;
use App\Models\MaterialRequest;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class MyWorkController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $user = $request->user();

        $toDecide = collect();
        $mySubmissions = collect();
        if ($user->hasPermission('approval.view', $companyId)) {
            $pending = ApprovalRequest::with(['workflow', 'approvable'])
                ->where('company_id', $companyId)
                ->where('status', 'pending')
                ->orderByRaw('due_at IS NULL, due_at asc');
            $toDecide = (clone $pending)->where('submitted_by', '!=', $user->id)->limit(25)->get();
            $mySubmissions = (clone $pending)->where('submitted_by', $user->id)->limit(15)->get();
        }

        $capaActions = collect();
        if ($user->hasPermission('qms.view', $companyId)) {
            $capaActions = CorrectiveAction::where('owner_id', $user->id)
                ->whereHas('nonconformity', fn ($q) => $q->where('company_id', $companyId))
                ->whereIn('status', ['open', 'in_progress'])
                ->orderBy('due_at')
                ->limit(15)
                ->get();
        }

        $hseActions = collect();
        if ($user->hasPermission('hse.view', $companyId)) {
            $hseActions = HseIncidentAction::where('owner_id', $user->id)
                ->whereHas('hseIncident', fn ($q) => $q->where('company_id', $companyId))
                ->whereIn('status', ['open', 'in_progress'])
                ->orderBy('due_at')
                ->limit(15)
                ->get();
        }

        $materialRequests = collect();
        if ($user->hasPermission('inventory.view', $companyId)) {
            $materialRequests = MaterialRequest::with(['project:id,code,name', 'warehouse:id,code,name'])
                ->where('company_id', $companyId)
                ->whereIn('status', ['requested', 'approved'])
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        $signatures = collect();
        if ($user->hasPermission('signature.view', $companyId)) {
            $signatures = DocumentSignature::with('version.document')
                ->where('company_id', $companyId)
                ->where('signer_id', $user->id)
                ->where('status', 'pending')
                ->limit(10)
                ->get();
        }

        return view('my-work', [
            'company' => $current->get(),
            'toDecide' => $toDecide,
            'mySubmissions' => $mySubmissions,
            'capaActions' => $capaActions,
            'hseActions' => $hseActions,
            'materialRequests' => $materialRequests,
            'signatures' => $signatures,
        ]);
    }
}
