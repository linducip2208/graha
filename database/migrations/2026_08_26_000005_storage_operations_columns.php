<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storage operations gelombang 5 (ADR-078): kolom additive untuk upload queue
 * idempotent dan retensi metadata. Tidak ada perubahan data existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stored_files', function (Blueprint $t) {
            // Upload queue: client menghasilkan UUID; finalize idempotent by upload_id.
            $t->uuid('upload_id')->nullable()->unique();
            $t->timestamp('archived_at')->nullable();
            $t->timestamp('retention_due_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('stored_files', function (Blueprint $t) {
            foreach (['upload_id', 'archived_at', 'retention_due_at'] as $column) {
                $t->dropColumn($column);
            }
        });
    }
};
