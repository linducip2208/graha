<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_cost_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_bin_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('lot_number', 80)->default('');
            $table->foreignId('source_movement_id')->constrained('stock_movements')->restrictOnDelete();
            $table->decimal('original_quantity', 20, 4);
            $table->decimal('remaining_quantity', 20, 4);
            $table->decimal('unit_cost', 20, 4)->default(0);
            $table->timestamps();
            $table->index(['company_id', 'item_id', 'warehouse_id', 'warehouse_bin_id', 'lot_number', 'id'], 'fifo_layer_lookup');
        });
        Schema::create('inventory_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->constrained('stock_movements')->cascadeOnDelete();
            $table->foreignId('inventory_cost_layer_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->decimal('unit_cost', 20, 4);
            $table->decimal('amount', 20, 4);
            $table->timestamps();
            $table->unique(['stock_movement_id', 'inventory_cost_layer_id'], 'fifo_allocation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_allocations');
        Schema::dropIfExists('inventory_cost_layers');
    }
};
