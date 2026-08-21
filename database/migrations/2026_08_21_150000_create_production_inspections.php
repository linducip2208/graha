<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_order_id')->constrained()->restrictOnDelete();
            $table->string('number', 80);
            $table->decimal('inspected_quantity', 20, 4);
            $table->string('result', 30)->index();
            $table->text('criteria');
            $table->text('findings')->nullable();
            $table->string('evidence_reference')->nullable();
            $table->foreignId('inspected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('inspected_at');
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['production_order_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_inspections');
    }
};
