<?php

namespace App\Http\Controllers;

use App\Models\Rfq;
use App\Models\Vendor;
use App\Models\VendorQuotation;
use App\Services\RfqService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RfqController extends Controller
{
    public function index(Request $request, CurrentCompany $current, RfqService $service)
    {
        $companyId = $current->id();
        $rfqs = Rfq::where('company_id', $companyId)->withCount(['items', 'vendors', 'quotations'])->orderByDesc('id')->get();
        $selected = $rfqs->firstWhere('id', (int) $request->query('rfq')) ?? $rfqs->first();

        return view('procurement.rfq', [
            'rfqs' => $rfqs,
            'rfq' => $selected?->load(['items.item', 'quotations.vendor', 'vendors.vendor']),
            'comparison' => $selected ? $service->compare($selected) : collect(),
            'vendors' => Vendor::where('company_id', $companyId)->where('status', 'approved')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, CurrentCompany $current, RfqService $service)
    {
        $data = $request->validate([
            'number' => ['required', 'max:80'],
            'title' => ['required', 'max:200'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'max:1000'],
            'items' => ['required', 'string'],
        ]);
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', trim($data['items'])) ?: [] as $index => $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                return back()->withErrors(['items' => 'Baris '.($index + 1).' harus format: SKU|kuantitas.'])->withInput();
            }
            $lines[] = ['sku' => $parts[0], 'quantity' => $parts[1]];
        }
        unset($data['items']);
        $service->create($current->id(), $data, $lines, $request->user());

        return back()->with('status', 'RFQ dibuat dan terbuka.');
    }

    public function invite(Request $request, Rfq $rfq, CurrentCompany $current, RfqService $service)
    {
        abort_unless($rfq->company_id === $current->id(), 404);
        $data = $request->validate(['vendor_ids' => ['required', 'array', 'min:1'], 'vendor_ids.*' => ['integer']]);
        $count = $service->invite($rfq->refresh(), $data['vendor_ids'], $request->user());

        return back()->with('status', "{$count} vendor diundang ke RFQ {$rfq->number}.");
    }

    public function submitQuotation(Request $request, Rfq $rfq, CurrentCompany $current, RfqService $service)
    {
        abort_unless($rfq->company_id === $current->id(), 404);
        $data = $request->validate([
            'vendor_id' => ['required', 'integer'],
            'number' => ['required', 'max:80'],
            'delivery_lead_days' => ['nullable', 'integer', 'min:0'],
            'payment_term' => ['nullable', 'max:100'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'technical_score' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'commercial_score' => ['nullable', 'decimal:0,2', 'between:0,100'],
            'recommendation' => ['nullable', 'max:500'],
            'prices' => ['required', 'array'],
            'prices.*' => ['required', 'decimal:0,2'],
        ]);
        abort_unless(Vendor::where('company_id', $current->id())->whereKey($data['vendor_id'])->exists(), 422);
        $priceLines = DB::transaction(function () use ($rfq, $data) {
            $rfq->loadMissing('items');
            $prices = $data['prices'];
            $lines = [];
            foreach ($rfq->items as $item) {
                throw_unless(isset($prices[$item->id]), ValidationException::withMessages(['prices' => "Harga untuk item {$item->item_id} wajib diisi."]));
                $lines[] = ['item_id' => $item->item_id, 'quantity' => (string) $item->quantity, 'unit_price' => $prices[$item->item_id]];
            }

            return $lines;
        });
        $header = collect($data)->except(['vendor_id', 'prices'])->all();
        $service->submitQuotation($rfq, (int) $data['vendor_id'], $header, $priceLines, $request->user());

        return back()->with('status', 'Quotation tersimpan. Bandingkan lalu pilih pemenang.');
    }

    public function select(Request $request, VendorQuotation $quotation, CurrentCompany $current, RfqService $service)
    {
        abort_unless($quotation->company_id === $current->id(), 404);
        $service->select($quotation, $request->user());

        return back()->with('status', 'Pemenang dipilih; RFQ ditutup. Lanjutkan membuat PO melalui menu Vendor & PO.');
    }
}
