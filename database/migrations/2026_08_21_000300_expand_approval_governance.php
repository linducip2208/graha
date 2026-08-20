<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->string('mode', 20)->default('all')->after('action');
            $table->unsignedSmallInteger('quorum')->nullable()->after('role_id');
        });
        Schema::table('approval_requests', fn (Blueprint $table) => $table->timestamp('due_at')->nullable()->after('submitted_at')->index());
        Schema::table('approval_decisions', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('comment');
            $table->text('user_agent')->nullable()->after('ip_address');
        });
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('delegator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'delegate_id', 'starts_at', 'ends_at'], 'approval_delegations_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
        Schema::table('approval_decisions', fn (Blueprint $table) => $table->dropColumn(['ip_address', 'user_agent']));
        Schema::table('approval_requests', fn (Blueprint $table) => $table->dropColumn('due_at'));
        Schema::table('approval_steps', fn (Blueprint $table) => $table->dropColumn(['mode', 'quorum']));
    }
};
