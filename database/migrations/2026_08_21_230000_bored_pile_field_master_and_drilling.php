<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bored_piles', function (Blueprint $t) {
            $t->string('grid_reference', 60)->nullable()->after('pile_number');
            $t->decimal('latitude', 11, 8)->nullable();
            $t->decimal('longitude', 11, 8)->nullable();
            $t->decimal('platform_elevation', 10, 3)->nullable();
            $t->decimal('design_toe_level', 10, 3)->nullable();
            $t->decimal('actual_toe_level', 10, 3)->nullable();
            $t->decimal('cut_off_level', 10, 3)->nullable();
            $t->string('concrete_grade', 30)->nullable();
            $t->string('drilling_method', 60)->nullable();
            $t->foreignId('rig_equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $t->string('operator_name', 120)->nullable();
            $t->string('supervisor_name', 120)->nullable();
            $t->string('consultant_name', 150)->nullable();
            $t->text('hold_reason')->nullable();
            $t->text('rework_reason')->nullable();
            $t->text('rejection_reason')->nullable();
        });
        Schema::create('bored_pile_drillings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->constrained()->restrictOnDelete();
            $t->timestamp('drilling_started_at');
            $t->timestamp('drilling_finished_at')->nullable();
            $t->decimal('groundwater_level_m', 8, 3)->nullable();
            $t->string('drilling_tool', 80)->nullable();
            $t->text('obstruction')->nullable();
            $t->text('problem')->nullable();
            $t->text('corrective_action')->nullable();
            $t->string('cleaning_method', 60)->nullable();
            $t->unsignedInteger('final_cleaning_minutes')->nullable();
            $t->decimal('sediment_depth_mm', 8, 2)->nullable();
            $t->string('weather', 40)->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->string('status', 20)->default('draft')->index();
            $t->timestamps();
            $t->index(['bored_pile_id', 'status']);
        });
        Schema::create('bored_pile_drilling_layers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bored_pile_drilling_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('sequence');
            $t->decimal('depth_from_m', 8, 3);
            $t->decimal('depth_to_m', 8, 3);
            $t->string('soil_description', 200);
            $t->timestamps();
            $t->index(['bored_pile_drilling_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bored_pile_drilling_layers');
        Schema::dropIfExists('bored_pile_drillings');
        Schema::table('bored_piles', function (Blueprint $t) {
            foreach (['grid_reference', 'latitude', 'longitude', 'platform_elevation', 'design_toe_level', 'actual_toe_level', 'cut_off_level', 'concrete_grade', 'drilling_method', 'operator_name', 'supervisor_name', 'consultant_name', 'hold_reason', 'rework_reason', 'rejection_reason'] as $column) {
                $t->dropColumn($column);
            }
            $t->dropConstrainedForeignId('rig_equipment_id');
        });
    }
};
