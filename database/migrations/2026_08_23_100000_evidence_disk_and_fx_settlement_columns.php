<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Evidence kini dapat disimpan di disk privat yang configurable (local atau S3-compatible seperti R2).
        Schema::table('field_evidences', function (Blueprint $t) {
            if (! Schema::hasColumn('field_evidences', 'disk')) {
                $t->string('disk', 30)->default('local')->after('disk_path');
            }
        });
        // Kurs efektif dokumen vendor invoice untuk perhitungan selisih kurs realized.
        Schema::table('vendor_invoices', function (Blueprint $t) {
            if (! Schema::hasColumn('vendor_invoices', 'exchange_rate')) {
                $t->decimal('exchange_rate', 18, 6)->default(1)->after('currency');
            }
        });
        // Selisih kurs realized yang diakui saat penyelesaian kas (audit trail).
        foreach (['customer_receipts', 'vendor_payments'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'fx_difference')) {
                    $t->decimal('fx_difference', 20, 2)->default(0)->after('withholding_amount');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('field_evidences', fn (Blueprint $t) => $t->dropColumn('disk'));
        Schema::table('vendor_invoices', fn (Blueprint $t) => $t->dropColumn('exchange_rate'));
        foreach (['customer_receipts', 'vendor_payments'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('fx_difference'));
        }
    }
};
