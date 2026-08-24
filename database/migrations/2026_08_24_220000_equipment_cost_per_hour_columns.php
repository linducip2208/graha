<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_usages', function (Blueprint $t) {
            $t->decimal('unit_cost', 20, 4)->nullable()->after('liters');
        });
        Schema::table('equipment', function (Blueprint $t) {
            $t->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets')->nullOnDelete()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $t) {
            $t->dropConstrainedForeignId('fixed_asset_id');
        });
        Schema::table('fuel_usages', function (Blueprint $t) {
            $t->dropColumn('unit_cost');
        });
    }
};
