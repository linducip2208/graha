<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->decimal('labor_rate_per_hour', 20, 2)->default(0);
            $table->decimal('overhead_rate_per_hour', 20, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('routing_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_of_material_id')->constrained('bills_of_material')->restrictOnDelete();
            $table->foreignId('work_center_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('name');
            $table->decimal('standard_minutes_per_unit', 14, 4);
            $table->text('work_instruction')->nullable();
            $table->timestamps();
            $table->unique(['bill_of_material_id', 'sequence']);
        });

        Schema::create('production_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('routing_operation_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_processed', 20, 4);
            $table->decimal('actual_hours', 14, 4);
            $table->decimal('labor_cost', 20, 2);
            $table->decimal('overhead_cost', 20, 2);
            $table->timestamp('performed_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 120);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key']);
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->decimal('actual_labor_cost', 20, 2)->default(0)->after('actual_material_cost');
            $table->decimal('actual_overhead_cost', 20, 2)->default(0)->after('actual_labor_cost');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn(['actual_labor_cost', 'actual_overhead_cost']);
        });
        Schema::dropIfExists('production_operation_logs');
        Schema::dropIfExists('routing_operations');
        Schema::dropIfExists('work_centers');
    }
};
