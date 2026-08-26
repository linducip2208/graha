<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_heartbeats', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->timestamp('last_seen_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('system_health_states', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('status', 20)->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });
        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('status', 20)->index();
            $table->string('disk', 40)->default('local');
            $table->string('path', 500)->nullable()->unique();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_status', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');
        Schema::dropIfExists('system_health_states');
        Schema::dropIfExists('system_heartbeats');
    }
};
