<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->char('currency', 3);
            $t->date('effective_date');
            $t->decimal('rate_to_idr', 18, 6);
            $t->string('source', 60)->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'currency', 'effective_date']);
        });
        foreach ([
            ['progress_billings'],
            ['vendor_invoices'],
            ['customer_receipts'],
            ['retention_releases'],
        ] as [$table]) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'currency')) {
                    $t->char('currency', 3)->default('IDR')->after('company_id');
                }
                if ($table === 'progress_billings' && ! Schema::hasColumn($table, 'exchange_rate')) {
                    $t->decimal('exchange_rate', 18, 6)->default(1)->after('currency');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_rates');
        Schema::table('progress_billings', fn (Blueprint $t) => $t->dropColumn(['exchange_rate']));
        foreach (['vendor_invoices', 'customer_receipts', 'retention_releases'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('currency'));
        }
    }
};
