<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('tax_number', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('status', 30)->default('approved')->index();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 30)->default('draft')->index();
            $table->char('currency', 3)->default('IDR');
            $table->decimal('total', 20, 2)->default(0);
            $table->date('order_date');
            $table->text('revision_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_price', 20, 2);
            $table->decimal('received_quantity', 20, 4)->default(0);
            $table->timestamps();
            $table->unique(['purchase_order_id', 'item_id']);
        });
        Schema::create('purchase_order_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->text('reason');
            $table->foreignId('revised_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['purchase_order_id', 'version']);
        });
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->string('status', 30)->default('posted');
            $table->timestamp('received_at');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 120);
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'idempotency_key']);
        });
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_bin_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->foreignId('stock_movement_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('vendor_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->date('invoice_date');
            $table->decimal('total', 20, 2);
            $table->string('match_status', 30)->default('pending')->index();
            $table->json('match_details')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'vendor_id', 'number'], 'vendor_invoice_number_unique');
        });
    }

    public function down(): void
    {
        foreach (['vendor_invoices', 'goods_receipt_items', 'goods_receipts', 'purchase_order_revisions', 'purchase_order_items', 'purchase_orders', 'vendors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
