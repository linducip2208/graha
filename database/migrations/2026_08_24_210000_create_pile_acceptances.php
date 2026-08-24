<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Acceptance lifecycle pile (ADR-051): memisahkan "construction completed"
     * dari "accepted" dengan gate yang membaca data nyata.
     */
    public function up(): void
    {
        Schema::create('pile_acceptances', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->constrained()->restrictOnDelete();
            $t->string('status', 30)->default('pending')->index(); // pending|qa_review|engineer_review|accepted|rejected|conditional
            $t->json('gate_checks')->nullable();      // snapshot hasil evaluasi gate saat submit/keputusan
            $t->text('conditions')->nullable();       // syarat untuk acceptance conditional
            $t->text('rejection_reason')->nullable();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('requested_at')->nullable();
            $t->foreignId('qa_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('qa_reviewed_at')->nullable();
            $t->foreignId('engineer_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('engineer_reviewed_at')->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->string('idempotency_key', 120)->nullable();
            $t->timestamps();
            $t->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pile_acceptances');
    }
};
