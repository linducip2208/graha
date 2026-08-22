<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('tender_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $t->foreignId('competitor_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->decimal('bid_value', 20, 2)->nullable();
            $t->unsignedSmallInteger('rank')->nullable();
            $t->boolean('is_winner')->default(false);
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['tender_id', 'is_winner']);
            $t->unique(['tender_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_participants');
        Schema::dropIfExists('competitors');
    }
};
