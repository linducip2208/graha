<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Budget baseline versi (ADR-053): snapshot JSON immutable per versi; approval menetapkan baseline aktif. */
    public function up(): void
    {
        Schema::create('budget_baselines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->string('status', 20)->default('draft')->index(); // draft|approved|superseded
            $t->json('lines'); // [{code,name,quantity,unit_cost,amount}]
            $t->decimal('total_budget', 20, 2)->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['project_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_baselines');
    }
};
