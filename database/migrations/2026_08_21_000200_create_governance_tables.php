<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('document_type', 80)->index();
            $t->string('number');
            $t->string('title');
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->string('workflow_status', 30)->default('draft')->index();
            $t->string('signature_status', 30)->default('unsigned');
            $t->timestamps();
            $t->unique(['company_id', 'number']);
        });
        Schema::create('document_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('revision', 20)->default('0');
            $t->string('disk', 40)->default('local');
            $t->string('path');
            $t->char('sha256', 64);
            $t->unsignedBigInteger('size_bytes');
            $t->string('mime_type', 120);
            $t->boolean('is_signed')->default(false);
            $t->timestamp('locked_at')->nullable();
            $t->text('change_reason')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['document_id', 'version']);
        });
        Schema::create('approval_workflows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('document_type', 80)->index();
            $t->string('mode', 30)->default('sequential');
            $t->decimal('min_amount', 20, 2)->nullable();
            $t->decimal('max_amount', 20, 2)->nullable();
            $t->char('currency', 3)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('approval_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('sequence');
            $t->string('action', 30)->default('approve');
            $t->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $t->unsignedInteger('sla_hours')->nullable();
            $t->timestamps();
            $t->unique(['approval_workflow_id', 'sequence']);
        });
        Schema::create('approval_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('approval_workflow_id')->constrained()->restrictOnDelete();
            $t->morphs('approvable');
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->string('status', 30)->default('pending')->index();
            $t->unsignedSmallInteger('current_sequence')->default(1);
            $t->decimal('amount', 20, 2)->nullable();
            $t->char('currency', 3)->nullable();
            $t->string('idempotency_key', 100);
            $t->timestamp('submitted_at');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'idempotency_key']);
        });
        Schema::create('approval_decisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('approval_request_id')->constrained()->restrictOnDelete();
            $t->foreignId('approval_step_id')->constrained()->restrictOnDelete();
            $t->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $t->string('decision', 30);
            $t->text('comment')->nullable();
            $t->timestamp('decided_at');
            $t->timestamps();
            $t->unique(['approval_request_id', 'approval_step_id', 'decided_by']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('event', 80)->index();
            $t->nullableMorphs('auditable');
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->char('previous_hash', 64)->nullable();
            $t->char('entry_hash', 64)->unique();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'approval_decisions', 'approval_requests', 'approval_steps', 'approval_workflows', 'document_versions', 'documents'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
