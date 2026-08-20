<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_safety_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->string('activity');
            $table->string('location')->nullable();
            $table->json('hazards');
            $table->json('controls');
            $table->string('risk_level', 20);
            $table->string('status', 30)->default('draft')->index();
            $table->date('valid_from');
            $table->date('valid_until');
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });
        Schema::create('work_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_safety_analysis_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->string('permit_type', 50);
            $table->string('work_location');
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->string('status', 30)->default('issued')->index();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });
        Schema::create('toolbox_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->date('meeting_date');
            $table->string('topic');
            $table->text('notes')->nullable();
            $table->json('attendee_ids');
            $table->foreignId('conducted_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'meeting_date']);
        });
        Schema::create('hse_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->string('type', 30);
            $table->string('severity', 30);
            $table->timestamp('occurred_at');
            $table->string('location');
            $table->text('description');
            $table->text('immediate_action')->nullable();
            $table->text('root_cause')->nullable();
            $table->string('status', 30)->default('reported')->index();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });
        Schema::create('hse_incident_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hse_incident_id')->constrained()->cascadeOnDelete();
            $table->text('action');
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->date('due_at');
            $table->string('status', 30)->default('open');
            $table->text('evidence')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['hse_incident_actions', 'hse_incidents', 'toolbox_meetings', 'work_permits', 'job_safety_analyses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
