<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qms_standards', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40);
            $t->string('name');
            $t->string('edition', 30);
            $t->string('amendment')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['code', 'edition', 'amendment'], 'qms_standard_edition_unique');
        });
        Schema::create('qms_clauses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('qms_standard_id')->constrained()->cascadeOnDelete();
            $t->string('code', 30);
            $t->string('title');
            $t->text('internal_requirement_summary');
            $t->timestamps();
            $t->unique(['qms_standard_id', 'code']);
        });
        Schema::create('qms_compliance_mappings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('qms_clause_id')->constrained()->restrictOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('owner_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('process_code', 60);
            $t->string('status', 30)->default('awaiting_verification')->index();
            $t->string('sop_reference')->nullable();
            $t->string('evidence_reference')->nullable();
            $t->date('evidence_expires_at')->nullable();
            $t->text('gap')->nullable();
            $t->text('action')->nullable();
            $t->date('next_review_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'qms_clause_id', 'process_code'], 'qms_mapping_unique');
        });
        Schema::create('risk_opportunities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('code', 50);
            $t->string('type', 20);
            $t->string('title');
            $t->text('description');
            $t->unsignedTinyInteger('likelihood');
            $t->unsignedTinyInteger('impact');
            $t->unsignedSmallInteger('inherent_score');
            $t->text('controls')->nullable();
            $t->unsignedTinyInteger('residual_likelihood')->nullable();
            $t->unsignedTinyInteger('residual_impact')->nullable();
            $t->unsignedSmallInteger('residual_score')->nullable();
            $t->string('status', 30)->default('open')->index();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->date('review_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('nonconformities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('number');
            $t->string('source_type', 40);
            $t->string('severity', 20);
            $t->text('description');
            $t->text('containment')->nullable();
            $t->text('root_cause')->nullable();
            $t->string('status', 30)->default('open')->index();
            $t->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $t->date('due_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
        });
        Schema::create('corrective_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('nonconformity_id')->constrained()->restrictOnDelete();
            $t->text('action');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->date('due_at');
            $t->string('status', 30)->default('open')->index();
            $t->text('evidence')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->text('effectiveness_notes')->nullable();
            $t->timestamps();
        });
        Schema::create('internal_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('number');
            $t->string('scope');
            $t->string('criteria');
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->foreignId('auditor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('auditee_id')->constrained('users')->restrictOnDelete();
            $t->date('scheduled_at');
            $t->string('status', 30)->default('planned')->index();
            $t->text('report')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
        });
        Schema::create('management_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('number');
            $t->date('meeting_date');
            $t->string('status', 30)->default('draft');
            $t->json('inputs_snapshot')->nullable();
            $t->text('decisions')->nullable();
            $t->foreignId('chairperson_id')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
        });
        Schema::create('management_review_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('management_review_id')->constrained()->cascadeOnDelete();
            $t->text('action');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->date('due_at');
            $t->string('status', 20)->default('open');
            $t->text('evidence')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['management_review_actions', 'management_reviews', 'internal_audits', 'corrective_actions', 'nonconformities', 'risk_opportunities', 'qms_compliance_mappings', 'qms_clauses', 'qms_standards'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
