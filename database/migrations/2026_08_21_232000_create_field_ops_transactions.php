<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concrete_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->constrained()->restrictOnDelete();
            $t->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $t->string('batching_plant', 150)->nullable();
            $t->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('delivery_order_number', 80);
            $t->string('truck_number', 40);
            $t->string('driver_name', 120)->nullable();
            $t->timestamp('batch_time')->nullable();
            $t->timestamp('arrived_at')->nullable();
            $t->timestamp('pour_started_at')->nullable();
            $t->timestamp('pour_finished_at')->nullable();
            $t->string('grade', 30)->nullable();
            $t->decimal('ordered_volume_m3', 12, 4);
            $t->decimal('delivered_volume_m3', 12, 4);
            $t->decimal('accepted_volume_m3', 12, 4);
            $t->decimal('rejected_volume_m3', 12, 4)->default(0);
            $t->decimal('slump_cm', 6, 2)->nullable();
            $t->string('sample_number', 60)->nullable();
            $t->text('rejection_reason')->nullable();
            $t->string('status', 20)->default('draft')->index();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->string('idempotency_key', 120);
            $t->timestamps();
            $t->unique(['company_id', 'idempotency_key']);
            $t->unique(['company_id', 'delivery_order_number']);
            $t->index(['bored_pile_id', 'status']);
        });
        Schema::create('pile_tests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->constrained()->restrictOnDelete();
            $t->string('number', 80);
            $t->string('test_type', 20)->index();
            $t->string('provider_name', 150)->nullable();
            $t->date('scheduled_date');
            $t->date('tested_at')->nullable();
            $t->string('method', 100)->nullable();
            $t->string('acceptance_criteria', 200)->nullable();
            $t->string('result_status', 20)->default('scheduled')->index();
            $t->text('interpretation')->nullable();
            $t->string('report_number', 80)->nullable();
            $t->foreignId('consultant_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('consultant_approved_at')->nullable();
            $t->string('ncr_number', 80)->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
            $t->index(['bored_pile_id', 'result_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pile_tests');
        Schema::dropIfExists('concrete_deliveries');
    }
};
