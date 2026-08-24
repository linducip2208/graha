<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Public Site V3: konfigurasi homepage publik per company (white-label). */
    public function up(): void
    {
        Schema::table('company_experiences', function (Blueprint $t) {
            $t->json('public_site')->nullable()->after('login_headline'); // {enabled,hero_title,hero_subtitle,cta_*,hero_image,sections{}}
        });
    }

    public function down(): void
    {
        Schema::table('company_experiences', fn (Blueprint $t) => $t->dropColumn('public_site'));
    }
};
