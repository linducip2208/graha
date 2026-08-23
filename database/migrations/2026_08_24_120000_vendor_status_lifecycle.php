<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Vendor status lifecycle (ADR-054): default approved agar data existing tetap berjalan. */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $t) {
            if (! Schema::hasColumn('vendors', 'status')) {
                $t->string('status', 20)->default('approved')->index()->after('name'); // approved|suspended|blacklisted
            }
            if (! Schema::hasColumn('vendors', 'qualified_at')) {
                $t->timestamp('qualified_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('vendors', 'status_note')) {
                $t->string('status_note', 300)->nullable()->after('qualified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', fn (Blueprint $t) => $t->dropColumn(['status', 'qualified_at', 'status_note']));
    }
};
