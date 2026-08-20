<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retention_releases', function (Blueprint $table) {
            $table->string('idempotency_key', 120)->nullable()->after('journal_id');
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('retention_releases', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'status']);
            $table->dropUnique(['company_id', 'idempotency_key']);
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'idempotency_key']);
        });
    }
};
