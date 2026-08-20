<?php

namespace App\Services;

use App\Models\AccountingMapping;
use App\Models\GoodsReceipt;
use App\Models\Journal;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Models\VendorInvoice;
use Illuminate\Validation\ValidationException;

class ProcurementAccountingService
{
    public function __construct(private AccountingService $accounting) {}

    public function postGoodsReceipt(GoodsReceipt $receipt, User $actor): Journal
    {
        $receipt->load('items');
        $total = '0';
        foreach ($receipt->items as $line) {
            $poItem = PurchaseOrderItem::findOrFail($line->purchase_order_item_id);
            $total = bcadd($total, bcmul((string) $line->quantity, (string) $poItem->unit_price, 2), 2);
        }

        return $this->postMapped($receipt->company_id, 'goods_receipt', $total, $receipt->received_at->toDateString(), (string) $receipt->id, 'Penerimaan barang '.$receipt->number, 'gr-accounting:'.$receipt->id, $actor);
    }

    public function postVendorInvoice(VendorInvoice $invoice, User $actor): Journal
    {
        throw_unless($invoice->match_status === 'matched', ValidationException::withMessages(['invoice' => 'Hanya invoice berstatus matched yang dapat diposting.']));

        return $this->postMapped($invoice->company_id, 'vendor_invoice', (string) $invoice->total, $invoice->invoice_date->toDateString(), (string) $invoice->id, 'Invoice vendor '.$invoice->number, 'vendor-invoice-accounting:'.$invoice->id, $actor);
    }

    private function postMapped(int $companyId, string $event, string $amount, string $date, string $sourceId, string $description, string $key, User $actor): Journal
    {
        $mappings = AccountingMapping::where('company_id', $companyId)->where('event_type', $event)->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date))->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>=', $date))->get()->keyBy('entry_side');
        throw_unless($mappings->has('debit') && $mappings->has('credit'), ValidationException::withMessages(['mapping' => "Mapping debit/kredit untuk $event belum lengkap."]));

        return $this->accounting->post($companyId, $date, $event, $sourceId, $description, [
            ['account_id' => $mappings['debit']->account_id, 'debit' => $amount, 'credit' => '0'],
            ['account_id' => $mappings['credit']->account_id, 'debit' => '0', 'credit' => $amount],
        ], $key, $actor);
    }
}
