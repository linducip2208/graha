<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('kind', 30)->index();
            $table->decimal('rate_percent', 9, 4);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::table('progress_billings', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('advance_recovery')->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 20, 2)->default(0)->after('tax_rate_id');
        });
        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 20, 2)->default(0)->after('invoice_date');
            $table->foreignId('tax_rate_id')->nullable()->after('subtotal')->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 20, 2)->default(0)->after('tax_rate_id');
        });
        foreach (['customer_receipts', 'vendor_payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('withholding_tax_rate_id')->nullable()->after('amount')->constrained('tax_rates')->nullOnDelete();
                $table->decimal('withholding_amount', 20, 2)->default(0)->after('withholding_tax_rate_id');
                $table->string('bukti_potong_number', 80)->nullable()->after('withholding_amount');
                $table->date('bukti_potong_date')->nullable()->after('bukti_potong_number');
            });
        }

        Schema::table('accounting_mappings', fn (Blueprint $table) => $table->string('entry_side', 40)->change());
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::table('progress_billings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
            $table->dropColumn('tax_amount');
        });
        Schema::table('vendor_invoices', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'tax_amount']);
            $table->dropConstrainedForeignId('tax_rate_id');
        });
        foreach (['customer_receipts', 'vendor_payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('withholding_tax_rate_id');
                $table->dropColumn(['withholding_amount', 'bukti_potong_number', 'bukti_potong_date']);
            });
        }
    }
};
