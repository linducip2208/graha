<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_cost_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->date('forecast_date');
            $table->decimal('cost_to_complete', 20, 2);
            $table->text('basis')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 120);
            $table->timestamps();
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['project_id', 'forecast_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_cost_forecasts');
    }
};
