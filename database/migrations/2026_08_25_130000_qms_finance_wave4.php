<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Backlog G/F gelombang 4: keluhan pelanggan, NCR supplier, recurring journal (ADR-065..067). */
    public function up(): void
    {
        Schema::table('nonconformities', function (Blueprint $t) {
            $t->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete()->after('project_id');
        });
        Schema::create('customer_complaints', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number');
            $t->date('complaint_date');
            $t->string('channel', 30)->default('other'); // email|phone|visit|other
            $t->string('subject', 200);
            $t->text('description');
            $t->string('severity', 20)->default('minor'); // minor|major
            $t->string('status', 20)->default('open')->index(); // open|investigating|resolved
            $t->text('resolution_notes')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->foreignId('ncr_id')->nullable()->constrained('nonconformities')->nullOnDelete();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
        });
        Schema::create('recurring_journals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('description', 255);
            $t->json('lines'); // [{account_id,debit,credit,project_id?}]
            $t->unsignedTinyInteger('day_of_month'); // 1-28
            $t->date('next_run_at')->index();
            $t->date('last_posted_at')->nullable();
            $t->string('status', 20)->default('active')->index(); // active|paused
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_journals');
        Schema::dropIfExists('customer_complaints');
        Schema::table('nonconformities', function (Blueprint $t) {
            $t->dropConstrainedForeignId('vendor_id');
        });
    }
};
