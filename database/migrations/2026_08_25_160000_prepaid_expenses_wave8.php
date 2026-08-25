<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Backlog F gelombang 8: prepaid expense (beban dibayar dimuka) dengan amortisasi bulanan (ADR-072). */
    public function up(): void
    {
        Schema::create('prepaid_expenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('vendor_ref', 120)->nullable();
            $t->decimal('total_amount', 20, 2);
            $t->unsignedTinyInteger('period_count'); // jumlah bulan amortisasi 1-120
            $t->date('first_period_date'); // hari pertama bulan pertama
            $t->string('status', 20)->default('active')->index(); // active|completed
            $t->date('last_posted_period')->nullable(); // bulan terakhir diposting
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prepaid_expenses');
    }
};
