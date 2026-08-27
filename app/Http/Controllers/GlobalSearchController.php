<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\ContractChange;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Equipment;
use App\Models\Journal;
use App\Models\MaterialRequest;
use App\Models\Nonconformity;
use App\Models\ProgressBilling;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\Tender;
use App\Models\Vendor;
use App\Support\AccessScopeService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function query(Request $request, CurrentCompany $current, AccessScopeService $scope): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }
        $companyId = $current->id();
        $user = $request->user();
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $results = [];

        if ($user->hasPermission('project.view', $companyId)) {
            $results = [...$results, ...$scope->applyToProjectQuery(Project::query(), $user, $companyId)
                ->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like))
                ->limit(4)->get()
                ->map(fn ($r) => ['type' => 'Proyek', 'label' => "{$r->code} — {$r->name}", 'href' => '/admin/projects/'.$r->id])->all()];
            $results = [...$results, ...BoredPile::whereHas('project', fn ($q) => $scope->applyToProjectQuery($q, $user, $companyId))
                ->where('pile_number', 'like', $like)->with('project:id,code')->limit(3)->get()
                ->map(fn ($r) => ['type' => 'Titik Pile', 'label' => "Pile {$r->pile_number}", 'sublabel' => $r->project?->code, 'href' => '/admin/projects/'.$r->project_id.'?tab=piles'])->all()];
        }
        if ($user->hasPermission('tender.view', $companyId)) {
            $results = [...$results, ...Tender::where('company_id', $companyId)
                ->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('project_name', 'like', $like))
                ->with('customer:id,name')->limit(4)->get()
                ->map(fn ($r) => ['type' => 'Tender', 'label' => "{$r->number} — {$r->project_name}", 'sublabel' => $r->customer?->name, 'href' => '/admin/tenders/'.$r->id])->all()];
            $results = [...$results, ...Customer::where('company_id', $companyId)
                ->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like))
                ->limit(3)->get()->map(fn ($r) => ['type' => 'Pelanggan', 'label' => "{$r->code} — {$r->name}", 'href' => '/admin/tenders'])->all()];
        }
        if ($user->hasPermission('contract.view', $companyId)) {
            $results = [...$results, ...$scope->applyToChildQuery(ContractChange::where('company_id', $companyId), $user, $companyId)
                ->where('title', 'like', $like)->with('project:id,code')->limit(3)->get()
                ->map(fn ($r) => ['type' => 'Kontrak', 'label' => "{$r->number} — {$r->title}", 'sublabel' => $r->project?->code, 'href' => '/admin/contracts/'.$r->id])->all()];
        }
        if ($user->hasPermission('procurement.view', $companyId)) {
            $results = [...$results, ...Vendor::where('company_id', $companyId)
                ->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like))
                ->limit(3)->get()->map(fn ($r) => ['type' => 'Vendor', 'label' => "{$r->code} — {$r->name}", 'href' => '/admin/procurement'])->all()];
            $results = [...$results, ...$scope->applyToChildQuery(PurchaseOrder::where('company_id', $companyId), $user, $companyId)
                ->where('number', 'like', $like)->limit(3)->get()
                ->map(fn ($r) => ['type' => 'PO', 'label' => "{$r->number} v{$r->version}", 'sublabel' => strtoupper($r->status), 'href' => '/admin/procurement#po-'.$r->id])->all()];
            $results = [...$results, ...Rfq::where('company_id', $companyId)
                ->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('title', 'like', $like))
                ->limit(3)->get()->map(fn ($r) => ['type' => 'RFQ', 'label' => "{$r->number} — {$r->title}", 'href' => '/admin/procurement/rfq'])->all()];
        }
        if ($user->hasPermission('inventory.view', $companyId)) {
            $results = [...$results, ...$scope->applyToChildQuery(MaterialRequest::where('company_id', $companyId), $user, $companyId)
                ->where('number', 'like', $like)->limit(3)->get()
                ->map(fn ($r) => ['type' => 'Permintaan Material', 'label' => $r->number, 'sublabel' => strtoupper($r->status), 'href' => '/admin/inventory/material-requests'])->all()];
        }
        if ($user->hasPermission('finance.view', $companyId)) {
            $results = [...$results, ...$scope->applyToChildQuery(ProgressBilling::where('company_id', $companyId), $user, $companyId)
                ->where('number', 'like', $like)->limit(3)->get()
                ->map(fn ($r) => ['type' => 'Billing', 'label' => $r->number, 'sublabel' => 'Rp '.number_format((float) $r->gross_amount, 0, ',', '.'), 'href' => '/admin/billing'])->all()];
            $results = [...$results, ...Journal::where('company_id', $companyId)
                ->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('description', 'like', $like))
                ->limit(3)->get()->map(fn ($r) => ['type' => 'Jurnal', 'label' => $r->number, 'sublabel' => str($r->description)->limit(40), 'href' => '/admin/finance/journals'])->all()];
        }
        if ($user->hasPermission('qms.view', $companyId)) {
            $results = [...$results, ...$scope->applyToChildQuery(Nonconformity::where('company_id', $companyId), $user, $companyId)
                ->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('description', 'like', $like))
                ->limit(3)->get()->map(fn ($r) => ['type' => 'NCR', 'label' => "{$r->number} — ".str($r->description)->limit(50), 'sublabel' => strtoupper($r->status), 'href' => '/admin/qms'])->all()];
        }
        if ($user->hasPermission('document.view', $companyId)) {
            $results = [...$results, ...$scope->applyToChildQuery(Document::where('company_id', $companyId), $user, $companyId)
                ->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('title', 'like', $like))
                ->limit(3)->get()->map(fn ($r) => ['type' => 'Dokumen', 'label' => "{$r->number} — {$r->title}", 'href' => '/admin/documents'])->all()];
        }
        if ($user->hasPermission('equipment.view', $companyId)) {
            $results = [...$results, ...Equipment::where('company_id', $companyId)
                ->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like))
                ->limit(3)->get()->map(fn ($r) => ['type' => 'Equipment', 'label' => "{$r->code} — {$r->name}", 'href' => '/admin/operations'])->all()];
        }

        usort($results, fn ($a, $b) => strcmp($a['type'], $b['type']));

        return response()->json(['results' => array_slice($results, 0, 20)]);
    }
}
