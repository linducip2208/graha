<?php

namespace Tests\Feature\Foundation;

use App\Models\Company;
use App\Models\Document;
use App\Models\SignatureProvider;
use App\Models\User;
use App\Services\DocumentSignatureService;
use App\Services\DocumentVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_signature_is_bound_to_version_hash_and_locks_it(): void
    {
        [$company, $user, $version] = $this->fixture();
        $signature = app(DocumentSignatureService::class)->signInternal($version, $user, 'Direktur', '127.0.0.1', 'PHPUnit');
        $this->assertSame($version->sha256, $signature->signed_hash);
        $this->assertTrue($version->refresh()->is_signed);
        $this->assertSame('fully_signed', $version->document->refresh()->signature_status);
        $this->expectException(ValidationException::class);
        app(DocumentSignatureService::class)->signInternal($version->refresh(), $user, 'Direktur');
    }

    public function test_external_webhook_is_verified_idempotent_and_replay_limited(): void
    {
        [$company, , $version] = $this->fixture();
        $provider = SignatureProvider::create(['company_id' => $company->id, 'name' => 'Provider Pengujian', 'api_format' => 'rest_hmac', 'webhook_secret_encrypted' => 'secret']);
        app(DocumentSignatureService::class)->createExternalRequest($version, $provider, 'REQ-1', 'Signer', 'Direktur');
        $timestamp = now()->timestamp;
        $raw = json_encode(['event' => 'signature.completed', 'request_id' => 'REQ-1']);
        $signature = hash_hmac('sha256', $timestamp.'.'.$raw, 'secret');
        $receipt = app(DocumentSignatureService::class)->handleWebhook($provider, 'EVT-1', $timestamp, $raw, $signature);
        $same = app(DocumentSignatureService::class)->handleWebhook($provider, 'EVT-1', $timestamp, $raw, $signature);
        $this->assertSame($receipt->id, $same->id);
        $this->assertSame('processed', $receipt->status);
        $this->assertTrue($version->refresh()->is_signed);
        $this->expectException(ValidationException::class);
        app(DocumentSignatureService::class)->handleWebhook($provider, 'EVT-2', $timestamp - 600, $raw, hash_hmac('sha256', ($timestamp - 600).'.'.$raw, 'secret'));
    }

    private function fixture(): array
    {
        Storage::fake('local');
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $document = Document::create(['company_id' => $company->id, 'document_type' => 'contract', 'number' => fake()->unique()->numerify('DOC-####'), 'title' => 'Kontrak', 'owner_id' => $user->id]);
        $file = UploadedFile::fake()->createWithContent('contract.pdf', '%PDF-1.4 signed content');
        $version = app(DocumentVersionService::class)->add($document, $file, $user, 'Initial');

        return [$company, $user, $version];
    }
}
