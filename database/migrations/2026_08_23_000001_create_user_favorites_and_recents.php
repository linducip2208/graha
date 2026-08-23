<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 160);
            $table->string('href', 500);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'href']);
        });

        Schema::create('user_recent_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 160);
            $table->string('href', 500);
            $table->timestamp('visited_at')->index();
            $table->timestamps();
            $table->unique(['user_id', 'href']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_recent_views');
        Schema::dropIfExists('user_favorites');
    }
};
