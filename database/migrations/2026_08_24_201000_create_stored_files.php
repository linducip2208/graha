<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registry metadata file (ADR-048): database HANYA menyimpan metadata/checksum,
     * binary fisik disimpan di object storage S3-compatible melalui disk Laravel.
     */
    public function up(): void
    {
        Schema::create('stored_files', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('document_version_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('category', 40)->index();
            $t->string('sub_category', 60)->nullable()->index();
            $t->string('disk', 40);
            $t->string('object_key', 500);
            $t->string('original_name', 255);
            $t->string('extension', 20)->nullable();
            $t->string('mime_type', 120);
            $t->unsignedBigInteger('size_bytes');
            $t->char('sha256', 64)->index();
            $t->string('status', 20)->default('ready')->index();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('uploaded_at')->nullable();
            $t->timestamp('captured_at')->nullable();
            $t->decimal('latitude', 11, 8)->nullable();
            $t->decimal('longitude', 11, 8)->nullable();
            $t->text('caption')->nullable();
            $t->json('metadata')->nullable();
            $t->foreignId('original_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
            $t->string('variant_type', 20)->nullable()->index();
            $t->timestamps();
            $t->unique(['disk', 'object_key']);
            $t->index(['bored_pile_id', 'category', 'sub_category']);
        });

        Schema::table('field_evidences', function (Blueprint $t) {
            $t->foreignId('stored_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
        });
        Schema::table('document_versions', function (Blueprint $t) {
            $t->foreignId('stored_file_id')->nullable()->constrained('stored_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('field_evidences', function (Blueprint $t) {
            $t->dropConstrainedForeignId('stored_file_id');
        });
        Schema::table('document_versions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('stored_file_id');
        });
        Schema::dropIfExists('stored_files');
    }
};
