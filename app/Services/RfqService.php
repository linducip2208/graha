<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Rfq;
use App\Models\RfqVendor;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorQuotation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RfqService
{
    public function __construct(private AuditTrail $audit) {}

    public function create(int $companyId, array $data, array $lines, User $actor): Rfq
    {
        return DB::transaction(function () use ($companyId, $data, $lines, $actor) {
            throw_if(Rfq::where('company_id', $companyId)->where('number', $data['number'])->exists(), ValidationException::withMessages(['number' => 'Nomor RFQ sudah dipakai.']));
            throw_if($lines === [], ValidationException::withMessages(['items' => 'Minimal satu item RFQ.']));
            $rfq = Rfq::create([...$data, 'company_id' => $companyId, 'status' => 'open', 'created_by' => $actor->id]);
            foreach ($lines as $line) {
                $item = Item::where('company_id', $companyId)->where('sku', $line['sku'])->first();
                throw_unless($item, ValidationException::withMessages(['items' => "SKU {$line['sku']} tidak ditemukan."]));
                throw_if(bccomp((string) $line['quantity'], '0', 4) !== 1, ValidationException::withMessages(['items' => "Kuantitas {$line['sku']} harus positif."]));
                $rfq->items()->create(['item_id' => $item->id, 'quantity' => $line['quantity'], 'description' => $line['description'] ?? null]);
            }
            $this->audit->record($companyId, $actor->id, 'procurement.rfq_created', $rfq);

            return $rfq->load('items');
        }, 3);
    }

    public function invite(Rfq $rfq, array $vendorIds, User $actor): int
    {
        return DB::transaction(function () use ($rfq, $vendorIds, $actor) {
            $rfq = Rfq::lockForUpdate()->findOrFail($rfq->id);
            throw_unless($actor->companies()->whereKey($rfq->company_id)->where('company_user.is_active', true)->exists(), ValidationException::withMessages(['company' => 'Anda bukan anggota aktif perusahaan RFQ ini.']));
            throw_unless($rfq->status === 'open', ValidationException::withMessages(['status' => 'RFQ sudah ditutup.']));
            $count = 0;
            foreach ($vendorIds as $vendorId) {
                $vendor = Vendor::where('company_id', $rfq->company_id)->find($vendorId);
                throw_unless($vendor, ValidationException::withMessages(['vendors' => "Vendor #{$vendorId} tidak ditemukan di perusahaan ini."]));
                throw_unless($vendor->status === 'approved', ValidationException::withMessages(['vendors' => "Vendor {$vendor->name} berstatus {$vendor->status} — hanya vendor approved yang dapat diundang."]));
                if (RfqVendor::where('rfq_id', $rfq->id)->where('vendor_id', $vendorId)->exists()) {
                    continue;
                }
                RfqVendor::create(['rfq_id' => $rfq->id, 'vendor_id' => $vendorId, 'invited_at' => now()]);
                $count++;
            }
            if ($count > 0) {
                $this->audit->record($rfq->company_id, $actor->id, 'procurement.rfq_invited', $rfq);
            }

            return $count;
        }, 3);
    }

    public function submitQuotation(Rfq $rfq, int $vendorId, array $header, array $priceLines, User $actor): VendorQuotation
    {
        return DB::transaction(function () use ($rfq, $vendorId, $header, $priceLines, $actor) {
            $rfq = Rfq::with('items')->lockForUpdate()->findOrFail($rfq->id);
            throw_unless($rfq->status === 'open', ValidationException::withMessages(['status' => 'RFQ sudah ditutup.']));
            $invitation = RfqVendor::where('rfq_id', $rfq->id)->where('vendor_id', $vendorId)->first();
            throw_unless($invitation, ValidationException::withMessages(['vendor' => 'Vendor belum diundang pada RFQ ini.']));
            $expectedItems = $rfq->items->sortBy('item_id')->pluck('item_id');
            $providedItems = collect($priceLines)->pluck('item_id')->sort()->values();
            throw_unless($expectedItems->count() === $providedItems->count() && $expectedItems->zip($providedItems)->every(fn ($pair) => $pair[0] == $pair[1]), ValidationException::withMessages(['prices' => 'Harga harus mencakup seluruh item RFQ dengan SKU yang sama.']));
            $quotation = VendorQuotation::updateOrCreate(
                ['rfq_id' => $rfq->id, 'vendor_id' => $vendorId],
                [...$header, 'company_id' => $rfq->company_id, 'status' => 'submitted', 'submitted_by' => $actor->id]
            );
            $quotation->items()->delete();
            foreach ($priceLines as $line) {
                throw_if(bccomp((string) $line['unit_price'], '0', 2) !== 1, ValidationException::withMessages(['prices' => 'Harga satuan harus positif.']));
                $quotation->items()->create(['item_id' => $line['item_id'], 'quantity' => $line['quantity'], 'unit_price' => $line['unit_price']]);
            }
            $invitation->update(['response_status' => 'quoted']);
            $this->audit->record($rfq->company_id, $actor->id, 'procurement.quotation_submitted', $quotation);

            return $quotation->load('items');
        }, 3);
    }

    /** @return Collection<int, array{vendor:string,total:string,lead:?int,tech:?string,comm:?string,status:string,id:int}> */
    public function compare(Rfq $rfq): Collection
    {
        return VendorQuotation::where('rfq_id', $rfq->id)->with(['items', 'vendor'])->get()
            ->map(fn (VendorQuotation $quote) => [
                'id' => $quote->id,
                'vendor' => $quote->vendor?->name ?? ('#'.$quote->vendor_id),
                'total' => $quote->totalPrice(),
                'lead' => $quote->delivery_lead_days,
                'tech' => $quote->technical_score,
                'comm' => $quote->commercial_score,
                'payment_term' => $quote->payment_term,
                'warranty' => $quote->warranty_months,
                'status' => $quote->status,
            ])->sortBy('total')->values();
    }

    public function select(VendorQuotation $quotation, User $actor): VendorQuotation
    {
        return DB::transaction(function () use ($quotation, $actor) {
            $quotation = VendorQuotation::lockForUpdate()->findOrFail($quotation->id);
            $rfq = Rfq::lockForUpdate()->findOrFail($quotation->rfq_id);
            throw_unless($rfq->status === 'open', ValidationException::withMessages(['status' => 'RFQ sudah ditutup.']));
            throw_unless($quotation->status === 'submitted', ValidationException::withMessages(['status' => 'Quotation sudah final.']));
            VendorQuotation::where('rfq_id', $rfq->id)->where('id', '!=', $quotation->id)->update(['status' => 'rejected']);
            $quotation->update(['status' => 'selected']);
            $rfq->update(['status' => 'closed']);
            RfqVendor::where('rfq_id', $rfq->id)->whereNotIn('response_status', ['quoted'])->update(['response_status' => 'no_response']);
            $this->audit->record($rfq->company_id, $actor->id, 'procurement.quotation_selected', $quotation);

            return $quotation->refresh();
        }, 3);
    }
}
