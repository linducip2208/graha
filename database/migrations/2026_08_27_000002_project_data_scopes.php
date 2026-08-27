<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->string('data_scope', 30)->default('all_company')->after('is_active')->index();
            $table->foreignId('scope_branch_id')->nullable()->after('data_scope')->constrained('branches')->nullOnDelete();
            $table->foreignId('scope_department_id')->nullable()->after('scope_branch_id')->constrained('departments')->nullOnDelete();
            $table->unsignedBigInteger('scope_version')->default(1)->after('scope_department_id');
        });

        Schema::create('project_user_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('access_level', 20)->default('view');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
            $table->index(['company_id', 'user_id', 'ends_at'], 'project_access_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user_access');
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scope_department_id');
            $table->dropConstrainedForeignId('scope_branch_id');
            $table->dropIndex(['data_scope']);
            $table->dropColumn(['data_scope', 'scope_version']);
        });
    }
};
