<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->date('billing_date');
            $table->decimal('progress_percent', 9, 4);
            $table->decimal('gross_amount', 20, 2);
            $table->decimal('retention_percent', 9, 4)->default(0);
            $table->decimal('retention_amount', 20, 2)->default(0);
            $table->decimal('advance_recovery', 20, 2)->default(0);
            $table->decimal('net_receivable', 20, 2);
            $table->string('status', 30)->default('draft')->index();
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('journal_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 120);
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'idempotency_key']);
        });
        Schema::create('retention_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->date('release_date');
            $table->decimal('amount', 20, 2);
            $table->string('status', 30)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('journal_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_releases');
        Schema::dropIfExists('progress_billings');
    }
};
