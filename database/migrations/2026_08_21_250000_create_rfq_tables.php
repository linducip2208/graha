<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number', 80);
            $t->string('title', 200);
            $t->date('due_date')->nullable();
            $t->text('notes')->nullable();
            $t->string('status', 20)->default('open')->index();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
        });
        Schema::create('rfq_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 20, 4);
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('rfq_vendors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $t->timestamp('invited_at')->nullable();
            $t->string('response_status', 20)->default('pending');
            $t->timestamps();
            $t->unique(['rfq_id', 'vendor_id']);
        });
        Schema::create('vendor_quotations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('rfq_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $t->string('number', 80);
            $t->unsignedInteger('delivery_lead_days')->nullable();
            $t->string('payment_term', 100)->nullable();
            $t->decimal('technical_score', 5, 2)->nullable();
            $t->decimal('commercial_score', 5, 2)->nullable();
            $t->text('recommendation')->nullable();
            $t->string('status', 20)->default('submitted')->index();
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['rfq_id', 'vendor_id']);
        });
        Schema::create('quotation_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('vendor_quotation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 20, 4);
            $t->decimal('unit_price', 20, 2);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['quotation_items', 'vendor_quotations', 'rfq_vendors', 'rfq_items', 'rfqs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
