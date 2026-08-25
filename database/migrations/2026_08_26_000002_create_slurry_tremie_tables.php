<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advanced Bored Pile gelombang 2 (ADR-074):
 * - Slurry control terstruktur (bentonite/polymer/water) — fitur opsional,
 *   tanpa kebijakan = record only.
 * - Tremie log dengan embedment deterministik + flag rentang.
 * - Nomor urut truck pada concrete_deliveries untuk timeline antar-truk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slurry_tests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->constrained()->restrictOnDelete();
            $t->string('phase', 30)->index(); // before_drilling|during_drilling|before_cage|before_casting
            $t->timestamp('tested_at');
            $t->string('type', 30); // bentonite|polymer|water|other
            $t->string('batch_number', 60)->nullable();
            $t->decimal('density', 8, 3)->nullable();
            $t->decimal('viscosity', 8, 2)->nullable();
            $t->decimal('ph', 5, 2)->nullable();
            $t->decimal('sand_content_percent', 6, 2)->nullable();
            $t->decimal('temperature', 6, 2)->nullable();
            $t->foreignId('sampled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->string('status', 20)->default('pending')->index(); // pending|accepted|rejected
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['bored_pile_id', 'phase']);
        });

        Schema::create('pile_tremie_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('sequence');
            $t->timestamp('recorded_at');
            $t->decimal('tremie_total_length_m', 8, 2);
            $t->decimal('tremie_tip_depth_m', 8, 2);
            $t->decimal('concrete_level_m', 8, 2)->nullable();
            $t->decimal('embedment_m', 8, 2)->nullable(); // dihitung deterministik bila kosong
            $t->string('flag', 20)->default('normal')->index(); // normal|warning|out_of_range
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['bored_pile_id', 'sequence']);
        });

        Schema::table('concrete_deliveries', function (Blueprint $t) {
            $t->unsignedInteger('sequence')->nullable()->after('bored_pile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pile_tremie_logs');
        Schema::dropIfExists('slurry_tests');
        Schema::table('concrete_deliveries', function (Blueprint $t) {
            $t->dropColumn('sequence');
        });
    }
};
