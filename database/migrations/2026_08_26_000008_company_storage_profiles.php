<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_storage_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('driver', 20);
            $table->string('provider_preset', 40)->default('custom');
            $table->string('endpoint', 500)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('bucket', 255)->nullable();
            $table->text('access_key_encrypted')->nullable();
            $table->text('secret_key_encrypted')->nullable();
            $table->boolean('use_path_style_endpoint')->default(false);
            $table->string('base_url', 500)->nullable();
            $table->unsignedSmallInteger('temporary_url_minutes')->default(15);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default_evidence')->default(false);
            $table->boolean('is_default_document')->default(false);
            $table->string('status', 20)->default('draft');
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamp('credentials_updated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active', 'is_default_evidence']);
            $table->index(['company_id', 'is_active', 'is_default_document']);
        });

        Schema::table('stored_files', function (Blueprint $table) {
            $table->foreignId('storage_profile_id')->nullable()->after('disk')->constrained('company_storage_profiles')->nullOnDelete();
            $table->json('storage_locator')->nullable()->after('object_key');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->foreignId('storage_profile_id')->nullable()->constrained('company_storage_profiles')->nullOnDelete();
            $table->json('storage_locator')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_profile_id');
            $table->dropColumn('storage_locator');
        });
        Schema::table('stored_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_profile_id');
            $table->dropColumn('storage_locator');
        });
        Schema::dropIfExists('company_storage_profiles');
    }
};
