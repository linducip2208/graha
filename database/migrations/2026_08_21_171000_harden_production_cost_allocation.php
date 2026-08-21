<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->decimal('completed_cost', 20, 2)->default(0)->after('actual_overhead_cost');
        });
        Schema::table('production_dispositions', function (Blueprint $table) {
            $table->decimal('cost_amount', 20, 2)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('production_dispositions', fn (Blueprint $table) => $table->dropColumn('cost_amount'));
        Schema::table('production_orders', fn (Blueprint $table) => $table->dropColumn('completed_cost'));
    }
};
