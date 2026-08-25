<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Backlog gelombang 10: dokumen registry ter-link proyek (Backlog G) agar tab Documents per proyek ter-filter nyata. */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->foreignId('project_id')->nullable()->after('owner_id')->constrained()->nullOnDelete();
            $t->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->dropConstrainedForeignId('project_id');
        });
    }
};
