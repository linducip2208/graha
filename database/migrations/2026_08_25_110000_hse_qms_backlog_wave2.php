<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Backlog G gelombang 2: observasi keselamatan, kalibrasi, ITP, PPE, exposure log KPI (ADR-058..061). */
    public function up(): void
    {
        Schema::create('safety_observations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number');
            $t->string('category', 30)->index(); // unsafe_act|unsafe_condition|near_miss
            $t->timestamp('observed_at');
            $t->string('location');
            $t->text('description');
            $t->text('immediate_action')->nullable();
            $t->string('status', 20)->default('open')->index(); // open|resolved|dismissed
            $t->text('resolution_notes')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
        });
        Schema::create('calibration_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $t->string('instrument_name');
            $t->string('serial_number', 120)->nullable();
            $t->date('calibrated_at');
            $t->date('next_due_at')->index();
            $t->string('certificate_no', 120)->nullable();
            $t->string('provider', 150)->nullable();
            $t->string('result', 20)->default('pass'); // pass|adjust|fail
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('inspection_test_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number');
            $t->string('title');
            $t->string('status', 20)->default('active')->index(); // active|closed
            $t->text('notes')->nullable();
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
        });
        Schema::create('itp_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('inspection_test_plan_id')->constrained()->cascadeOnDelete();
            $t->string('stage');
            $t->string('method', 150);
            $t->text('acceptance_criteria');
            $t->string('checkpoint_type', 20)->default('witness'); // hold|witness|review
            $t->string('frequency', 80)->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('itp_inspections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('itp_item_id')->constrained()->cascadeOnDelete();
            $t->date('performed_at');
            $t->string('result', 20); // pass|fail|pending
            $t->string('measured_value', 150)->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('inspector_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['itp_item_id', 'performed_at']);
        });
        Schema::create('ppe_issuances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('item_name');
            $t->string('size', 30)->nullable();
            $t->unsignedInteger('quantity')->default(1);
            $t->date('issued_at');
            $t->date('returned_at')->nullable();
            $t->string('condition_out', 20)->default('good'); // good|worn|damaged
            $t->string('condition_in', 20)->nullable();
            $t->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'issued_at']);
        });
        Schema::create('hse_exposure_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->date('period_month'); // hari pertama bulan
            $t->decimal('man_hours', 14, 2);
            $t->unsignedInteger('avg_headcount')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'period_month']);
        });
        Schema::table('hse_incidents', function (Blueprint $t) {
            $t->boolean('is_lost_time')->default(false)->after('severity');
            $t->unsignedInteger('lost_days')->default(0)->after('is_lost_time');
        });
    }

    public function down(): void
    {
        Schema::table('hse_incidents', function (Blueprint $t) {
            $t->dropColumn(['is_lost_time', 'lost_days']);
        });
        foreach (['hse_exposure_logs', 'ppe_issuances', 'itp_inspections', 'itp_items', 'inspection_test_plans', 'calibration_records', 'safety_observations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
