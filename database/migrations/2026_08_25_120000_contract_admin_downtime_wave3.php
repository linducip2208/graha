<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Backlog E/F gelombang 3: milestone & asuransi kontrak, downtime terstruktur equipment (ADR-062..064). */
    public function up(): void
    {
        Schema::create('contract_milestones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_award_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->date('planned_date')->nullable();
            $t->date('actual_date')->nullable();
            $t->decimal('weight_percent', 6, 3)->default(0); // bobot progres kontrak
            $t->decimal('amount', 20, 2)->default(0);
            $t->string('status', 20)->default('pending')->index(); // pending|achieved
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('contract_insurances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_award_id')->constrained()->restrictOnDelete();
            $t->string('policy_number');
            $t->string('provider');
            $t->string('coverage_type', 50); // car|ear|tpl|surety|other
            $t->decimal('insured_amount', 20, 2);
            $t->decimal('premium', 20, 2)->default(0);
            $t->date('start_date');
            $t->date('end_date');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('equipment_downtime_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->string('reason', 40); // breakdown|maintenance|changeover|waiting_material|weather|other
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['equipment_id', 'started_at']);
        });
    }

    public function down(): void
    {
        foreach (['equipment_downtime_logs', 'contract_insurances', 'contract_milestones'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
