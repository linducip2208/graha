<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** ADR-063/064/065: dashboard builder config, industry pack, edition. */
    public function up(): void
    {
        Schema::table('company_experiences', function (Blueprint $t) {
            foreach (['industry_pack', 'edition'] as $col) {
                if (! Schema::hasColumn('company_experiences', $col)) {
                    $t->string($col, 40)->nullable()->after('frontend_theme');
                }
            }
            if (! Schema::hasColumn('company_experiences', 'dashboard_config')) {
                $t->json('dashboard_config')->nullable()->after('edition'); // [{id,w}]
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_experiences', fn (Blueprint $t) => $t->dropColumn(['industry_pack', 'edition', 'dashboard_config']));
    }
};
