<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 80);
            $table->string('type', 40)->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->decimal('amount', 20, 2)->default(0);
            $table->integer('days_extension')->default(0);
            $table->date('effective_date')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('idempotency_key', 120);
            $table->timestamps();
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_changes');
    }
};
