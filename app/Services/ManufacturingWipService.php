<?php

namespace App\Services;

use App\Models\ProductionOrder;
use Illuminate\Support\Collection;

class ManufacturingWipService
{
    public function reconcile(int $companyId): Collection
    {
        return ProductionOrder::where('company_id', $companyId)->with(['bom.outputItem', 'inspections.dispositions'])->orderBy('number')->get()->map(function (ProductionOrder $order): array {
            $actual = bcadd(bcadd((string) $order->actual_material_cost, (string) $order->actual_labor_cost, 2), (string) $order->actual_overhead_cost, 2);
            $scrappedQuantity = '0';
            $scrappedCost = '0';
            foreach ($order->inspections->flatMap->dispositions->where('disposition', 'scrap') as $disposition) {
                $scrappedQuantity = bcadd($scrappedQuantity, (string) $disposition->quantity, 4);
                $scrappedCost = bcadd($scrappedCost, (string) $disposition->cost_amount, 2);
            }
            $accountedQuantity = bcadd((string) $order->completed_quantity, $scrappedQuantity, 4);
            $residual = bcsub(bcsub($actual, (string) $order->completed_cost, 2), $scrappedCost, 2);
            $terminal = bccomp($accountedQuantity, (string) $order->planned_quantity, 4) >= 0;

            return ['order' => $order, 'actual_cost' => $actual, 'completed_cost' => (string) $order->completed_cost, 'scrapped_cost' => $scrappedCost, 'residual_wip' => $residual, 'accounted_quantity' => $accountedQuantity, 'terminal' => $terminal, 'anomaly' => $terminal && bccomp($residual, '0', 2) !== 0];
        });
    }
}
