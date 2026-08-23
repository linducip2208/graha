<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('period', 20);
            $table->unsignedTinyInteger('quality_score');
            $table->unsignedTinyInteger('delivery_score');
            $table->unsignedTinyInteger('price_score');
            $table->unsignedTinyInteger('service_score');
            $table->text('notes')->nullable();
            $table->foreignId('evaluated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'vendor_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_evaluations');
    }
};
