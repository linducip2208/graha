<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_dispositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_inspection_id')->constrained()->restrictOnDelete();
            $table->string('number', 80);
            $table->string('disposition', 30)->index();
            $table->decimal('quantity', 20, 4);
            $table->text('reason');
            $table->text('instruction')->nullable();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->foreignId('journal_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 120);
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_dispositions');
    }
};
