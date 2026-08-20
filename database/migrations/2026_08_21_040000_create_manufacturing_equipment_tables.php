<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills_of_material', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('output_item_id')->constrained('items')->restrictOnDelete();
            $t->string('code', 50);
            $t->unsignedInteger('version');
            $t->decimal('output_quantity', 20, 4)->default(1);
            $t->string('status', 20)->default('draft');
            $t->timestamps();
            $t->unique(['company_id', 'code', 'version'], 'bom_company_code_version_unique');
        });
        Schema::create('bill_of_material_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bill_of_material_id')->constrained('bills_of_material')->cascadeOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 20, 4);
            $t->decimal('scrap_percent', 7, 3)->default(0);
            $t->timestamps();
            $t->unique(['bill_of_material_id', 'item_id'], 'bom_item_unique');
        });
        Schema::create('production_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('bill_of_material_id')->constrained('bills_of_material')->restrictOnDelete();
            $t->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $t->foreignId('output_bin_id')->constrained('warehouse_bins')->restrictOnDelete();
            $t->string('number');
            $t->decimal('planned_quantity', 20, 4);
            $t->decimal('completed_quantity', 20, 4)->default(0);
            $t->string('status', 30)->default('planned')->index();
            $t->decimal('actual_material_cost', 20, 2)->default(0);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
        });
        Schema::create('production_material_issues', function (Blueprint $t) {
            $t->id();
            $t->foreignId('production_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('stock_movement_id')->constrained()->restrictOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 20, 4);
            $t->string('lot_number', 80)->default('');
            $t->timestamps();
            $t->unique('stock_movement_id');
        });
        Schema::create('equipment', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code', 50);
            $t->string('name');
            $t->string('ownership', 20);
            $t->string('category', 50);
            $t->decimal('current_hour_meter', 14, 2)->default(0);
            $t->decimal('fuel_target_lph', 12, 4)->nullable();
            $t->string('status', 30)->default('available')->index();
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('equipment_meter_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $t->decimal('reading', 14, 2);
            $t->timestamp('recorded_at');
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['equipment_id', 'reading']);
        });
        Schema::create('maintenance_work_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $t->string('number');
            $t->string('type', 30);
            $t->string('status', 30)->default('open')->index();
            $t->text('problem');
            $t->decimal('meter_reading', 14, 2);
            $t->decimal('actual_cost', 20, 2)->default(0);
            $t->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
        });
        Schema::create('fuel_usages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->decimal('liters', 14, 4);
            $t->decimal('start_meter', 14, 2);
            $t->decimal('end_meter', 14, 2);
            $t->decimal('liters_per_hour', 14, 4)->nullable();
            $t->boolean('is_anomaly')->default(false)->index();
            $t->string('reference', 80);
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('used_at');
            $t->timestamps();
            $t->unique(['company_id', 'reference']);
        });
    }

    public function down(): void
    {
        foreach (['fuel_usages', 'maintenance_work_orders', 'equipment_meter_logs', 'equipment', 'production_material_issues', 'production_orders', 'bill_of_material_items', 'bills_of_material'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
