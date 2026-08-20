<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number');
            $table->char('currency', 3)->default('IDR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });
        Schema::create('customer_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('progress_billing_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->date('receipt_date');
            $table->decimal('amount', 20, 2);
            $table->string('reference', 120);
            $table->string('status', 30)->default('posted');
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 120);
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'idempotency_key']);
        });
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->date('payment_date');
            $table->decimal('amount', 20, 2);
            $table->string('reference', 120);
            $table->string('status', 30)->default('posted');
            $table->foreignId('journal_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 120);
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->unique(['company_id', 'idempotency_key']);
        });
        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->date('transaction_date');
            $table->string('reference', 120);
            $table->text('description')->nullable();
            $table->decimal('amount', 20, 2);
            $table->string('status', 30)->default('unreconciled')->index();
            $table->nullableMorphs('matched_transaction', 'bank_stmt_match_idx');
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
            $table->unique(['bank_account_id', 'transaction_date', 'reference', 'amount'], 'bank_statement_line_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('customer_receipts');
        Schema::dropIfExists('bank_accounts');
    }
};
