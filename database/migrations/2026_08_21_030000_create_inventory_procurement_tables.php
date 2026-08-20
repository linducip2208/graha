<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code', 20);
            $t->string('name');
            $t->unsignedTinyInteger('precision')->default(3);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('unit_id')->constrained()->restrictOnDelete();
            $t->string('sku', 60);
            $t->string('name');
            $t->string('category', 60);
            $t->string('tracking_type', 20)->default('none');
            $t->decimal('minimum_stock', 20, 4)->default(0);
            $t->decimal('reorder_point', 20, 4)->default(0);
            $t->boolean('allow_negative')->default(false);
            $t->boolean('is_active')->default(true)->index();
            $t->timestamps();
            $t->unique(['company_id', 'sku']);
        });
        Schema::create('warehouses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('warehouse_bins', function (Blueprint $t) {
            $t->id();
            $t->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $t->string('code', 40);
            $t->string('name');
            $t->timestamps();
            $t->unique(['warehouse_id', 'code']);
        });
        Schema::create('stock_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $t->foreignId('warehouse_bin_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('lot_number', 80)->default('');
            $t->decimal('quantity', 20, 4)->default(0);
            $t->decimal('reserved_quantity', 20, 4)->default(0);
            $t->timestamps();
            $t->unique(['company_id', 'item_id', 'warehouse_id', 'warehouse_bin_id', 'lot_number'], 'stock_balance_dimension_unique');
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->uuid('transaction_id')->index();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $t->foreignId('warehouse_bin_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('lot_number', 80)->default('');
            $t->string('movement_type', 30)->index();
            $t->decimal('quantity', 20, 4);
            $t->decimal('balance_after', 20, 4);
            $t->decimal('unit_cost', 20, 4)->default(0);
            $t->string('reference_type', 80);
            $t->string('reference_id', 80);
            $t->string('idempotency_key', 120);
            $t->text('reason')->nullable();
            $t->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('posted_at');
            $t->timestamps();
            $t->unique(['company_id', 'idempotency_key']);
        });
        Schema::create('purchase_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('number');
            $t->string('status', 30)->default('draft')->index();
            $t->decimal('budget_available', 20, 2)->default(0);
            $t->decimal('estimated_total', 20, 2)->default(0);
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
        });
        Schema::create('purchase_request_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 20, 4);
            $t->decimal('estimated_unit_price', 20, 4);
            $t->date('required_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['purchase_request_items', 'purchase_requests', 'stock_movements', 'stock_balances', 'warehouse_bins', 'warehouses', 'items', 'units'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
