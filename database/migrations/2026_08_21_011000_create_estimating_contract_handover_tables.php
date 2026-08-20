<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_estimates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tender_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('status', 30)->default('draft')->index();
            $t->decimal('boq_total', 20, 2)->default(0);
            $t->decimal('rab_total', 20, 2)->default(0);
            $t->decimal('rap_total', 20, 2)->default(0);
            $t->text('revision_reason')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['tender_id', 'version']);
        });
        Schema::create('tender_estimate_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tender_estimate_id')->constrained()->cascadeOnDelete();
            $t->string('code', 40);
            $t->text('description');
            $t->string('uom', 20);
            $t->decimal('quantity', 20, 4);
            $t->decimal('boq_unit_price', 20, 2)->default(0);
            $t->decimal('rab_unit_cost', 20, 2)->default(0);
            $t->decimal('rap_unit_cost', 20, 2)->default(0);
            $t->timestamps();
            $t->unique(['tender_estimate_id', 'code']);
        });
        Schema::create('project_awards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('tender_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('source', 30);
            $t->string('award_type', 30);
            $t->string('award_number');
            $t->date('award_date');
            $t->decimal('contract_value', 20, 2);
            $t->decimal('retention_percent', 7, 4)->default(0);
            $t->string('status', 30)->default('received')->index();
            $t->boolean('legal_approved')->default(false);
            $t->boolean('finance_tax_approved')->default(false);
            $t->boolean('signed')->default(false);
            $t->foreignId('project_manager_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'award_number']);
        });
        Schema::create('project_handovers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_award_id')->constrained()->restrictOnDelete();
            $t->string('status', 30)->default('draft')->index();
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique('project_award_id');
        });
        Schema::create('project_handover_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_handover_id')->constrained()->cascadeOnDelete();
            $t->string('item_code', 60);
            $t->string('label');
            $t->boolean('is_required')->default(true);
            $t->boolean('is_complete')->default(false);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['project_handover_id', 'item_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_handover_items');
        Schema::dropIfExists('project_handovers');
        Schema::dropIfExists('project_awards');
        Schema::dropIfExists('tender_estimate_items');
        Schema::dropIfExists('tender_estimates');
    }
};
