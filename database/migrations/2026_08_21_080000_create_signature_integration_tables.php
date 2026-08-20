<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('api_format', 40);
            $table->string('base_url')->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->text('webhook_secret_encrypted')->nullable();
            $table->json('extra_headers')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('signature_provider_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('signer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('signer_name');
            $table->string('signer_position')->nullable();
            $table->string('signature_type', 30);
            $table->string('status', 30)->default('pending')->index();
            $table->char('signed_hash', 64);
            $table->string('external_request_id')->nullable();
            $table->string('signed_disk', 40)->nullable();
            $table->string('signed_path')->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
            $table->unique(['document_version_id', 'signer_name', 'signature_type'], 'document_signer_type_unique');
            $table->unique(['signature_provider_id', 'external_request_id'], 'external_signature_request_unique');
        });
        Schema::create('signature_webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('signature_provider_id')->constrained()->restrictOnDelete();
            $table->string('event_id', 120);
            $table->string('event_type', 80);
            $table->char('payload_hash', 64);
            $table->timestamp('provider_timestamp');
            $table->timestamp('processed_at')->nullable();
            $table->string('status', 30)->default('received');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['signature_provider_id', 'event_id'], 'signature_webhook_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_webhook_receipts');
        Schema::dropIfExists('document_signatures');
        Schema::dropIfExists('signature_providers');
    }
};
