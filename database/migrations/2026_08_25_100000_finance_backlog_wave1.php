<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Backlog F gelombang 1: kategori arus kas akun, disposal aset, credit note & write-off AR (ADR-055..057). */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $t) {
            $t->boolean('is_cash')->default(false)->after('is_postable');
            $t->string('cash_flow_category', 20)->nullable()->after('is_cash')->index(); // operating|investing|financing
        });
        Schema::table('fixed_assets', function (Blueprint $t) {
            $t->date('disposed_at')->nullable()->after('status');
            $t->decimal('disposal_proceeds', 20, 2)->default(0)->after('disposed_at');
            $t->foreignId('disposal_journal_id')->nullable()->constrained('journals')->nullOnDelete()->after('disposal_proceeds');
        });
        Schema::create('ar_credit_notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('progress_billing_id')->constrained()->restrictOnDelete();
            $t->string('number');
            $t->date('note_date');
            $t->decimal('amount', 20, 2);
            $t->string('reason');
            $t->string('status', 20)->default('posted')->index();
            $t->foreignId('journal_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('idempotency_key', 120);
            $t->timestamps();
            $t->unique(['company_id', 'number']);
            $t->unique(['company_id', 'idempotency_key']);
        });
        Schema::create('ar_write_offs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('progress_billing_id')->constrained()->restrictOnDelete();
            $t->string('number');
            $t->date('request_date');
            $t->decimal('amount', 20, 2);
            $t->string('reason');
            $t->string('status', 30)->default('pending_approval')->index(); // pending_approval|approved|rejected
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->text('decision_notes')->nullable();
            $t->foreignId('final_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $t->string('idempotency_key', 120);
            $t->timestamps();
            $t->unique(['company_id', 'number']);
            $t->unique(['company_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_write_offs');
        Schema::dropIfExists('ar_credit_notes');
        Schema::table('fixed_assets', function (Blueprint $t) {
            $t->dropConstrainedForeignId('disposal_journal_id');
            $t->dropColumn(['disposed_at', 'disposal_proceeds']);
        });
        Schema::table('accounts', function (Blueprint $t) {
            $t->dropColumn(['is_cash', 'cash_flow_category']);
        });
    }
};
