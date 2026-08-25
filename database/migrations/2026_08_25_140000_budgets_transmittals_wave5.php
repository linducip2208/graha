<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Backlog F/G gelombang 5: budget per akun per periode fiskal + transmittal distribusi dokumen (ADR-067..068). */
    public function up(): void
    {
        Schema::create('account_budgets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('account_id')->constrained()->restrictOnDelete();
            $t->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $t->decimal('amount', 20, 2);
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'account_id', 'fiscal_period_id']);
        });
        Schema::create('document_transmittals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('number');
            $t->string('recipient'); // pihak eksternal/internal
            $t->string('purpose', 255)->nullable();
            $t->date('transmit_date');
            $t->string('method', 40)->default('email'); // email|courier|hand|portal
            $t->string('status', 20)->default('sent')->index(); // sent|acknowledged
            $t->timestamp('acknowledged_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
        });
        Schema::create('document_transmittal_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_transmittal_id')->constrained()->cascadeOnDelete();
            $t->foreignId('document_version_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('copies')->default(1);
            $t->text('note')->nullable();
            $t->timestamps();
            $t->unique(['document_transmittal_id', 'document_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_transmittal_items');
        Schema::dropIfExists('document_transmittals');
        Schema::dropIfExists('account_budgets');
    }
};
