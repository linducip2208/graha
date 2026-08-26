<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advanced Bored Pile gelombang 3 (ADR-075):
 * - Interval pour beton → kurva theoretical vs aktual dari data nyata.
 * - Pembacaan geometri lubang (caliper/survey) — fitur opsional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pile_concrete_pour_intervals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('sequence');
            $t->timestamp('recorded_at');
            $t->decimal('depth_or_level_m', 8, 3);
            $t->decimal('incremental_volume_m3', 10, 4);
            $t->decimal('cumulative_volume_m3', 10, 4)->nullable();
            $t->foreignId('concrete_delivery_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pile_tremie_log_id')->nullable()->constrained()->nullOnDelete();
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['bored_pile_id', 'sequence']);
        });

        Schema::create('pile_geometry_readings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->constrained()->restrictOnDelete();
            $t->decimal('depth_m', 8, 3);
            $t->decimal('measured_diameter_mm', 10, 2)->nullable();
            $t->decimal('deviation_x_mm', 10, 2)->nullable();
            $t->decimal('deviation_y_mm', 10, 2)->nullable();
            $t->decimal('verticality_percent', 6, 3)->nullable();
            $t->timestamp('recorded_at');
            $t->string('source', 30)->default('manual'); // manual|survey|caliper_import|telemetry
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['bored_pile_id', 'depth_m']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pile_geometry_readings');
        Schema::dropIfExists('pile_concrete_pour_intervals');
    }
};
