<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->unsignedInteger('default_useful_life_months');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('fixed_asset_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->date('acquisition_date');
            $table->date('depreciation_start_date');
            $table->decimal('acquisition_cost', 20, 2);
            $table->decimal('residual_value', 20, 2)->default(0);
            $table->unsignedInteger('useful_life_months');
            $table->string('depreciation_method', 30)->default('straight_line');
            $table->string('status', 30)->default('active')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('fixed_asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->date('depreciation_date');
            $table->decimal('amount', 20, 2);
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->string('idempotency_key', 120);
            $table->timestamps();
            $table->unique(['fixed_asset_id', 'fiscal_period_id']);
            $table->unique(['company_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciations');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('fixed_asset_categories');
    }
};
