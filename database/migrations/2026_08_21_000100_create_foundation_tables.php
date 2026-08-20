<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('legal_name')->nullable();
            $t->char('base_currency', 3)->default('IDR');
            $t->string('timezone')->default('Asia/Jakarta');
            $t->json('branding')->nullable();
            $t->boolean('is_active')->default(true)->index();
            $t->timestamps();
        });
        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('code', 80);
            $t->string('name');
            $t->boolean('is_system')->default(false);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('permissions', function (Blueprint $t) {
            $t->id();
            $t->string('code', 120)->unique();
            $t->string('name');
            $t->string('module', 60)->index();
            $t->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $t) {
            $t->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['permission_id', 'role_id']);
        });
        Schema::create('company_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'user_id']);
        });
        Schema::create('company_user_role', function (Blueprint $t) {
            $t->foreignId('company_user_id')->constrained('company_user')->cascadeOnDelete();
            $t->foreignId('role_id')->constrained()->cascadeOnDelete();
            $t->primary(['company_user_id', 'role_id']);
        });
        Schema::create('number_sequences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('document_type', 80);
            $t->string('prefix', 30)->nullable();
            $t->string('format')->default('{PREFIX}/{YYYY}/{SEQ}');
            $t->unsignedBigInteger('next_number')->default(1);
            $t->unsignedTinyInteger('padding')->default(5);
            $t->boolean('reset_yearly')->default(true);
            $t->unsignedSmallInteger('last_reset_year')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'document_type']);
        });
    }

    public function down(): void
    {
        foreach (['number_sequences', 'company_user_role', 'company_user', 'permission_role', 'permissions', 'roles', 'departments', 'branches', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
