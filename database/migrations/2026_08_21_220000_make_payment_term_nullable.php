<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_term_days')->default(null)->nullable()->change();
        });
        DB::table('customers')->where('payment_term_days', 30)->update(['payment_term_days' => null]);
    }

    public function down(): void
    {
        DB::table('customers')->whereNull('payment_term_days')->update(['payment_term_days' => 30]);
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_term_days')->default(30)->nullable(false)->change();
        });
    }
};
