<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** ADR-076: preferensi launcher + custom cover per workspace (JSON map key->path). */
    public function up(): void
    {
        Schema::table('company_experiences', function (Blueprint $t) {
            $t->json('launcher_config')->nullable()->after('nav_config');
            $t->json('launcher_covers')->nullable()->after('launcher_config');
        });
    }

    public function down(): void
    {
        Schema::table('company_experiences', function (Blueprint $t) {
            $t->dropColumn(['launcher_config', 'launcher_covers']);
        });
    }
};
