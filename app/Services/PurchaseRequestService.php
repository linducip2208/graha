<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function __construct(private AuditTrail $audit) {}

    public function submit(PurchaseRequest $request, User $actor): PurchaseRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            $request = PurchaseRequest::with('items')->lockForUpdate()->findOrFail($request->id);
            $total = '0';
            foreach ($request->items as $item) {
                $total = bcadd($total, bcmul((string) $item->quantity, (string) $item->estimated_unit_price, 2), 2);
            }throw_if(bccomp($total, (string) $request->budget_available, 2) === 1, ValidationException::withMessages(['budget' => 'Estimasi PR melebihi budget tersedia.']));
            $request->update(['estimated_total' => $total, 'status' => 'submitted']);
            $this->audit->record($request->company_id, $actor->id, 'procurement.pr_submitted', $request);

            return $request->refresh();
        }, 3);
    }
}
