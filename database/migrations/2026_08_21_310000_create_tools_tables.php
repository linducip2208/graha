<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code', 40);
            $t->string('name');
            $t->string('category', 60)->nullable();
            $t->decimal('purchase_cost', 20, 2)->default(0);
            $t->string('status', 20)->default('available')->index();
            $t->foreignId('checked_out_to')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('checked_out_at')->nullable();
            $t->timestamp('expected_return_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('tool_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tool_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20)->index();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('occurred_at');
            $t->timestamp('expected_return_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_movements');
        Schema::dropIfExists('tools');
    }
};
