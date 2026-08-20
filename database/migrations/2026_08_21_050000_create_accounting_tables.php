<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->date('starts_at');
            $t->date('ends_at');
            $t->string('status', 20)->default('open')->index();
            $t->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'starts_at', 'ends_at'], 'fiscal_period_range_unique');
        });
        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->string('type', 20);
            $t->string('normal_balance', 10);
            $t->boolean('is_postable')->default(true);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('accounting_mappings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('event_type', 80);
            $t->string('entry_side', 10);
            $t->foreignId('account_id')->constrained()->restrictOnDelete();
            $t->date('effective_from')->nullable();
            $t->date('effective_until')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'event_type', 'entry_side'], 'account_mapping_unique');
        });
        Schema::create('journals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $t->string('number');
            $t->date('journal_date');
            $t->string('source_type', 80);
            $t->string('source_id', 80);
            $t->string('description');
            $t->string('status', 20)->default('draft')->index();
            $t->string('idempotency_key', 120);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
            $t->unique(['company_id', 'idempotency_key']);
        });
        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('journal_id')->constrained()->restrictOnDelete();
            $t->foreignId('account_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('project_cost_code_id')->nullable()->constrained()->restrictOnDelete();
            $t->decimal('debit', 20, 2)->default(0);
            $t->decimal('credit', 20, 2)->default(0);
            $t->string('memo')->nullable();
            $t->timestamps();
            $t->index(['account_id', 'project_id']);
        });
        Schema::create('project_cost_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_cost_code_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $t->string('cost_type', 40);
            $t->decimal('amount', 20, 2);
            $t->date('cost_date');
            $t->timestamps();
            $t->unique('journal_entry_id');
        });
    }

    public function down(): void
    {
        foreach (['project_cost_ledger', 'journal_entries', 'journals', 'accounting_mappings', 'accounts', 'fiscal_periods'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
