<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advanced Bored Pile gelombang 1 (ADR-073):
 * - Bottom cleaning inspection gate (opsional per company).
 * - Snapshot hasil readiness engine (drill/cast) untuk jejak "last checked".
 * - Kolom survey tambahan pada bored_piles (easting/northing/elevasi) —
 *   semuanya nullable sehingga backward compatible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pile_bottom_cleaning_inspections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->constrained()->restrictOnDelete();
            $t->string('method', 60)->nullable();
            $t->decimal('sediment_thickness_mm', 8, 2)->nullable();
            $t->timestamp('cleaned_at')->nullable();
            $t->timestamp('inspected_at');
            $t->foreignId('inspected_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('witnessed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 20)->default('pending')->index(); // pending|accepted|rejected
            $t->text('notes')->nullable();
            $t->foreignId('evidence_file_id')->nullable()->constrained('stored_files', 'id')->nullOnDelete();
            $t->timestamps();
            $t->index(['bored_pile_id', 'status']);
        });

        Schema::create('pile_readiness_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 10); // drill|cast
            $t->string('status', 20); // READY|NOT_READY|READY_TO_CAST|BLOCKED
            $t->json('blockers');
            $t->json('checklist');
            $t->foreignId('checked_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['bored_pile_id', 'kind']);
        });

        Schema::table('bored_piles', function (Blueprint $t) {
            $t->decimal('design_easting', 14, 4)->nullable();
            $t->decimal('design_northing', 14, 4)->nullable();
            $t->decimal('actual_easting', 14, 4)->nullable();
            $t->decimal('actual_northing', 14, 4)->nullable();
            $t->decimal('design_top_elevation', 10, 3)->nullable();
            $t->decimal('actual_top_elevation', 10, 3)->nullable();
            $t->decimal('design_cutoff_level', 10, 3)->nullable();
            $t->decimal('actual_cutoff_level', 10, 3)->nullable();
            $t->boolean('casing_required')->default(false);
            $t->string('slurry_type', 30)->nullable(); // bentonite|polymer|water|other
            $t->timestamp('platform_ready_at')->nullable();
            $t->timestamp('concrete_booking_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pile_readiness_checks');
        Schema::dropIfExists('pile_bottom_cleaning_inspections');
        Schema::table('bored_piles', function (Blueprint $t) {
            foreach (['design_easting', 'design_northing', 'actual_easting', 'actual_northing', 'design_top_elevation', 'actual_top_elevation', 'design_cutoff_level', 'actual_cutoff_level', 'casing_required', 'slurry_type', 'platform_ready_at', 'concrete_booking_confirmed_at'] as $column) {
                $t->dropColumn($column);
            }
        });
    }
};
