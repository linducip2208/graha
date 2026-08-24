<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** ADR-059: versi experience (draft/published/archived) + brand asset privat. */
    public function up(): void
    {
        Schema::create('experience_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('status', 20)->default('draft')->index(); // draft|published|archived
            $t->json('config');
            $t->string('logo_path')->nullable();
            $t->string('favicon_path')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'version']);
        });
        Schema::table('company_experiences', function (Blueprint $t) {
            $t->string('logo_path')->nullable()->after('login_headline');
            $t->string('favicon_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_versions');
        Schema::table('company_experiences', fn (Blueprint $t) => $t->dropColumn(['logo_path', 'favicon_path']));
    }
};
