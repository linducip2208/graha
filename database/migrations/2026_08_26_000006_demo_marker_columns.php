<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demo dataset v2 (ADR-079): penanda is_demo pada company & project supaya
 * data demo dapat diidentifikasi dan dibersihkan secara andal — tanpa
 * menyebar flag ke setiap tabel transaksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->boolean('is_demo')->default(false)->after('name');
        });
        Schema::table('projects', function (Blueprint $t) {
            $t->boolean('is_demo')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->dropColumn('is_demo');
        });
        Schema::table('projects', function (Blueprint $t) {
            $t->dropColumn('is_demo');
        });
    }
};
