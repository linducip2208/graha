<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->decimal('reorder_max', 20, 4)->default(0)->after('reorder_point');
            $t->unsignedSmallInteger('lead_time_days')->default(0)->after('reorder_max');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $t) {
            $t->dropColumn(['reorder_max', 'lead_time_days']);
        });
    }
};
