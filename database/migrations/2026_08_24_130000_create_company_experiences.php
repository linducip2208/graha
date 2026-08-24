<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Experience Platform fondasi (ADR-058): satu baris per company, additive, default aman. */
    public function up(): void
    {
        Schema::create('company_experiences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->unique()->constrained()->restrictOnDelete();
            $t->string('admin_theme', 40)->default('executive-navy');
            $t->string('frontend_theme', 40)->default('corporate');
            $t->char('primary_color', 7)->nullable();      // #RRGGBB
            $t->char('secondary_color', 7)->nullable();
            $t->char('accent_color', 7)->nullable();
            $t->string('font_ui', 60)->nullable();         // whitelist
            $t->string('font_heading', 60)->nullable();
            $t->string('density', 20)->nullable();         // compact|comfortable
            $t->string('button_style', 20)->nullable();    // soft|rounded|pill|square
            $t->string('card_style', 20)->nullable();      // bordered|elevated|minimal|soft
            $t->string('sidebar_style', 20)->nullable();   // dark|light|brand
            $t->string('topbar_style', 20)->nullable();    // light|dark|brand
            $t->string('system_name', 80)->nullable();
            $t->string('company_display_name', 120)->nullable();
            $t->string('footer_text', 200)->nullable();
            $t->string('support_email', 120)->nullable();
            $t->string('login_headline', 150)->nullable();
            $t->json('nav_config')->nullable(); // {hidden:[idx],labels:{idx:str},order:[idx]}
            $t->json('terminology')->nullable(); // {'Customer':'Client', ...}
            $t->boolean('is_published')->default(true);
            $t->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_experiences');
    }
};
