<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_access_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 80);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'permission']);
            $table->index(['permission', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_access_grants');
    }
};
