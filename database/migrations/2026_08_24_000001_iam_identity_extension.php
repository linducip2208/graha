<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IAM extension — SEMUA additive, tidak mengubah arsitektur RBAC existing:
 * User → company_user → company_user_role → Role → permission_role → Permission
 *
 * - users: status lifecycle (invited/active/suspended/inactive), profil,
 *   preferences JSON, MFA (secret & recovery codes terenkripsi).
 * - company_user: data scope per membership (all_company/branch/department/projects).
 * - user_invitations: token hashed, single-use, expire.
 * - user_login_histories: jejak login sukses/gagal/logout.
 * - project_user_access: project-level scope dengan access level.
 * - approval_authorities: authority limit per user/role per document type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone', 30)->nullable()->after('name');
            $t->string('avatar_path')->nullable()->after('phone');
            $t->string('status', 20)->default('active')->index()->after('is_active'); // invited|active|suspended|inactive
            $t->timestamp('invited_at')->nullable()->after('status');
            $t->timestamp('password_changed_at')->nullable();
            $t->json('preferences')->nullable();
            $t->text('mfa_secret')->nullable();          // encrypted cast di model
            $t->text('mfa_recovery_codes')->nullable();  // encrypted cast (json)
            $t->timestamp('mfa_enabled_at')->nullable();
        });

        Schema::table('company_user', function (Blueprint $t) {
            $t->string('data_scope', 20)->default('all_company')->after('department_id'); // all_company|branch|department|projects
            $t->foreignId('scope_branch_id')->nullable()->after('data_scope')->constrained('branches')->nullOnDelete();
            $t->foreignId('scope_department_id')->nullable()->after('scope_branch_id')->constrained('departments')->nullOnDelete();
            $t->index(['user_id', 'is_active']);
        });

        Schema::create('user_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('email')->index();
            $t->string('name');
            $t->char('token_hash', 64);              // sha256(token) — raw token tidak disimpan
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->json('role_ids');
            $t->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'email']);
        });

        Schema::create('user_login_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('event', 30)->index();         // login_success|login_failed|mfa_failed|logout
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('project_user_access', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('access_level', 20)->default('view'); // view|contributor|manager
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['project_id', 'user_id']);
            $t->index(['user_id']);
        });

        Schema::create('approval_authorities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('role_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('document_type', 80)->index();
            $t->decimal('min_amount', 20, 2)->default(0);
            $t->decimal('max_amount', 20, 2);
            $t->char('currency', 3)->default('IDR');
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('requires_mfa')->default(false);
            $t->date('effective_from')->default(now());
            $t->date('effective_until')->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['company_id', 'document_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_authorities');
        Schema::dropIfExists('project_user_access');
        Schema::dropIfExists('user_login_histories');
        Schema::dropIfExists('user_invitations');

        Schema::table('company_user', function (Blueprint $t) {
            $t->dropConstrainedForeignId('scope_department_id');
            $t->dropConstrainedForeignId('scope_branch_id');
            $t->dropColumn('data_scope');
            $t->dropIndex(['user_id', 'is_active']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn([
                'phone', 'avatar_path', 'status', 'invited_at', 'password_changed_at',
                'preferences', 'mfa_secret', 'mfa_recovery_codes', 'mfa_enabled_at',
            ]);
        });
    }
};
