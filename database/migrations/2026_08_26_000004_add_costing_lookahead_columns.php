<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advanced Bored Pile gelombang 4 (ADR-076): kolom additive untuk costing,
 * delay reason registry terpusat, dan planning lookahead. Tidak ada data
 * existing yang diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pile_tests', function (Blueprint $t) {
            $t->decimal('cost_amount', 16, 2)->nullable()->after('interpretation');
        });
        Schema::table('equipment_downtime_logs', function (Blueprint $t) {
            $t->string('delay_reason', 40)->nullable()->after('reason')->index();
        });
        Schema::table('bored_piles', function (Blueprint $t) {
            $t->date('planned_date')->nullable()->after('planned_depth_m')->index();
            $t->unsignedInteger('planned_shift')->nullable()->after('planned_date');
        });
    }

    public function down(): void
    {
        Schema::table('pile_tests', function (Blueprint $t) {
            $t->dropColumn('cost_amount');
        });
        Schema::table('equipment_downtime_logs', function (Blueprint $t) {
            $t->dropColumn('delay_reason');
        });
        Schema::table('bored_piles', function (Blueprint $t) {
            foreach (['planned_date', 'planned_shift'] as $column) {
                $t->dropColumn($column);
            }
        });
    }
};
