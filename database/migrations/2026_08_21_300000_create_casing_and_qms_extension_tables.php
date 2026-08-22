<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('casing_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code', 40);
            $t->decimal('diameter_mm', 10, 2);
            $t->decimal('length_m', 10, 3);
            $t->string('ownership', 20)->default('owned');
            $t->string('supplier_name', 150)->nullable();
            $t->decimal('rental_cost_total', 20, 2)->default(0);
            $t->unsignedInteger('usage_cycle_count')->default(0);
            $t->string('condition', 20)->default('good');
            $t->string('status', 30)->default('in_stock')->index();
            $t->foreignId('current_bored_pile_id')->nullable()->constrained('bored_piles')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('casing_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('casing_unit_id')->constrained()->cascadeOnDelete();
            $t->string('type', 30)->index();
            $t->foreignId('bored_pile_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('occurred_at');
            $t->text('notes')->nullable();
            $t->decimal('cost', 20, 2)->default(0);
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('customer_satisfaction_surveys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('respondent_name', 150)->nullable();
            $t->date('survey_date');
            $t->unsignedTinyInteger('quality_score');
            $t->unsignedTinyInteger('schedule_score');
            $t->unsignedTinyInteger('communication_score');
            $t->text('comments')->nullable();
            $t->string('follow_up_action', 300)->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('quality_objectives', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('title', 200);
            $t->string('kpi_metric', 150)->nullable();
            $t->decimal('target_value', 14, 2)->nullable();
            $t->decimal('actual_value', 14, 2)->nullable();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->date('due_date')->nullable();
            $t->string('status', 20)->default('open')->index();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['quality_objectives', 'customer_satisfaction_surveys', 'casing_movements', 'casing_units'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
