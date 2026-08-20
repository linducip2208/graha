<?php

namespace App\Http\Controllers;

use App\Models\ApprovalWorkflow;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Models\Warehouse;
use App\Services\ApprovalEngine;
use App\Services\ProcurementAccountingService;
use App\Services\PurchaseOrderService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcurementController extends Controller
{
    public function index(CurrentCompany $current)
    {
        $id = $current->id();

        return view('procurement.index', [
            'vendors' => Vendor::where('company_id', $id)->orderBy('name')->get(), 'items' => Item::where('company_id', $id)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('company_id', $id)->with('bins')->get(),
            'orders' => PurchaseOrder::where('company_id', $id)->with(['vendor', 'items.item', 'revisions'])->latest()->get(),
            'receipts' => GoodsReceipt::where('company_id', $id)->latest()->limit(50)->get(),
            'invoices' => VendorInvoice::where('company_id', $id)->latest()->limit(50)->get(),
            'workflows' => ApprovalWorkflow::where('company_id', $id)->where('document_type', 'purchase_order')->where('is_active', true)->get(),
        ]);
    }

    public function vendor(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['code' => ['required', 'max:50', 'unique:vendors,code,NULL,id,company_id,'.$current->id()], 'name' => ['required', 'max:255'], 'tax_number' => ['nullable', 'max:50'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'max:40']]);
        Vendor::create([...$data, 'company_id' => $current->id()]);

        return back()->with('status', 'Vendor ditambahkan.');
    }

    public function accountingIndex(CurrentCompany $current)
    {
        return view('procurement.accounting', ['receipts' => GoodsReceipt::where('company_id', $current->id())->latest()->limit(100)->get(), 'invoices' => VendorInvoice::where('company_id', $current->id())->latest()->limit(100)->get()]);
    }

    public function order(Request $request, CurrentCompany $current, PurchaseOrderService $service)
    {
        $data = $request->validate(['number' => ['required', 'max:80', 'unique:purchase_orders,number,NULL,id,company_id,'.$current->id()], 'vendor_id' => ['required', 'exists:vendors,id'], 'order_date' => ['required', 'date'], 'currency' => ['required', 'size:3'], 'item_id' => ['required', 'exists:items,id'], 'quantity' => ['required', 'decimal:0,4', 'gt:0'], 'unit_price' => ['required', 'decimal:0,2', 'gt:0']]);
        abort_unless(Vendor::where('company_id', $current->id())->whereKey($data['vendor_id'])->exists() && Item::where('company_id', $current->id())->whereKey($data['item_id'])->exists(), 422);
        $order = DB::transaction(function () use ($data, $current, $request) {
            $order = PurchaseOrder::create(['company_id' => $current->id(), 'vendor_id' => $data['vendor_id'], 'number' => $data['number'], 'order_date' => $data['order_date'], 'currency' => strtoupper($data['currency']), 'created_by' => $request->user()->id]);
            $order->items()->create(['item_id' => $data['item_id'], 'quantity' => $data['quantity'], 'unit_price' => $data['unit_price']]);

            return $order;
        }, 3);
        $service->recalculate($order);

        return back()->with('status', 'Draft PO dibuat.');
    }

    public function submit(Request $request, PurchaseOrder $order, CurrentCompany $current, ApprovalEngine $engine)
    {
        $this->owned($order, $current);
        $data = $request->validate(['workflow_id' => ['required', 'exists:approval_workflows,id'], 'idempotency_key' => ['required', 'max:100']]);
        $workflow = ApprovalWorkflow::where('company_id', $current->id())->where('document_type', 'purchase_order')->whereKey($data['workflow_id'])->firstOrFail();
        if ($workflow->min_amount !== null && bccomp((string) $order->total, (string) $workflow->min_amount, 2) < 0) {
            throw ValidationException::withMessages(['workflow_id' => 'Nilai PO di bawah batas workflow.']);
        }
        if ($workflow->max_amount !== null && bccomp((string) $order->total, (string) $workflow->max_amount, 2) > 0) {
            throw ValidationException::withMessages(['workflow_id' => 'Nilai PO di atas batas workflow.']);
        }
        $approval = $engine->submit($workflow, $order, $request->user(), $data['idempotency_key']);
        $approval->update(['amount' => $order->total, 'currency' => $order->currency]);
        $order->update(['status' => 'pending_approval']);

        return back()->with('status', 'PO dikirim ke approval workflow.');
    }

    public function activate(Request $request, PurchaseOrder $order, CurrentCompany $current, PurchaseOrderService $service)
    {
        $this->owned($order, $current);
        $service->activateApproved($order, $request->user());

        return back()->with('status', 'PO aktif dan siap diterima.');
    }

    public function receive(Request $request, PurchaseOrder $order, CurrentCompany $current, PurchaseOrderService $service)
    {
        $this->owned($order, $current);
        $data = $request->validate(['number' => ['required', 'max:80'], 'warehouse_id' => ['required', 'exists:warehouses,id'], 'purchase_order_item_id' => ['required', 'exists:purchase_order_items,id'], 'warehouse_bin_id' => ['required', 'exists:warehouse_bins,id'], 'quantity' => ['required', 'decimal:0,4', 'gt:0'], 'idempotency_key' => ['required', 'max:120']]);
        abort_unless(Warehouse::where('company_id', $current->id())->whereKey($data['warehouse_id'])->exists(), 422);
        $service->receive($order, $data['warehouse_id'], [$data], $data['number'], $data['idempotency_key'], $request->user());

        return back()->with('status', 'Goods receipt diposting ke inventory.');
    }

    public function invoice(Request $request, PurchaseOrder $order, CurrentCompany $current, PurchaseOrderService $service)
    {
        $this->owned($order, $current);
        $data = $request->validate(['number' => ['required', 'max:80'], 'invoice_date' => ['required', 'date'], 'total' => ['required', 'decimal:0,2', 'gt:0']]);
        $invoice = VendorInvoice::create([...$data, 'company_id' => $current->id(), 'vendor_id' => $order->vendor_id, 'purchase_order_id' => $order->id]);
        $service->match($invoice);

        return back()->with('status', 'Invoice dicatat dan three-way matching dijalankan.');
    }

    public function postReceipt(Request $request, GoodsReceipt $receipt, CurrentCompany $current, ProcurementAccountingService $service)
    {
        abort_unless($receipt->company_id === $current->id(), 404);
        $service->postGoodsReceipt($receipt, $request->user());

        return back()->with('status', 'Jurnal Inventory/GRNI diposting.');
    }

    public function postInvoice(Request $request, VendorInvoice $invoice, CurrentCompany $current, ProcurementAccountingService $service)
    {
        abort_unless($invoice->company_id === $current->id(), 404);
        $service->postVendorInvoice($invoice, $request->user());

        return back()->with('status', 'Jurnal GRNI/AP diposting.');
    }

    private function owned(PurchaseOrder $order, CurrentCompany $current): void
    {
        abort_unless($order->company_id === $current->id(), 404);
    }
}
