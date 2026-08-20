<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('tax_id', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->unsignedSmallInteger('year')->index();
            $table->string('project_name');
            $table->string('location')->nullable();
            $table->string('work_type')->nullable();
            $table->decimal('owner_estimate', 20, 2)->nullable();
            $table->decimal('bid_value', 20, 2)->nullable();
            $table->decimal('estimated_cost', 20, 2)->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'year', 'status']);
        });
        Schema::create('tender_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_id')->constrained()->restrictOnDelete();
            $table->string('outcome', 10)->index();
            $table->date('announced_at');
            $table->string('winner_name')->nullable();
            $table->decimal('winning_bid_value', 20, 2)->nullable();
            $table->decimal('negotiated_value', 20, 2)->nullable();
            $table->decimal('contract_value', 20, 2)->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->string('elimination_stage')->nullable();
            $table->string('primary_reason')->nullable();
            $table->json('additional_reasons')->nullable();
            $table->text('evaluation_notes')->nullable();
            $table->text('lesson_learned')->nullable();
            $table->text('improvement_recommendation')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique('tender_id');
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_tender_id')->nullable()->constrained('tenders')->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('location')->nullable();
            $table->decimal('contract_value', 20, 2)->nullable();
            $table->decimal('estimated_cost', 20, 2)->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique('source_tender_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
        Schema::dropIfExists('tender_outcomes');
        Schema::dropIfExists('tenders');
        Schema::dropIfExists('customers');
    }
};
