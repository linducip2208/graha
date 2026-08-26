<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P11 — Grup pondasi (pile cap / zona / grup kustom) dan keanggotaan pile.
 * Readiness grup dihitung on-demand oleh FoundationGroupService dari data
 * nyata; tabel ini hanya menyimpan definisi grup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foundation_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('type', 30)->default('pile_cap')->index(); // pile_cap|zone|custom_group
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'name']);
        });

        Schema::create('foundation_group_piles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('foundation_group_id')->constrained()->cascadeOnDelete();
            $t->foreignId('bored_pile_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('sequence')->default(0);
            $t->timestamps();
            $t->unique(['foundation_group_id', 'bored_pile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foundation_group_piles');
        Schema::dropIfExists('foundation_groups');
    }
};
