<?php

namespace App\Services;

use App\Models\DocumentSignature;
use App\Models\DocumentVersion;
use App\Models\SignatureProvider;
use App\Models\SignatureWebhookReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentSignatureService
{
    public function __construct(private DocumentVersionService $versions, private AuditTrail $audit) {}

    public function signInternal(DocumentVersion $version, User $signer, string $position, ?string $ip = null, ?string $agent = null): DocumentSignature
    {
        return DB::transaction(function () use ($version, $signer, $position, $ip, $agent) {
            $version = DocumentVersion::with('document')->lockForUpdate()->findOrFail($version->id);
            $this->assertIntegrity($version);
            throw_if($version->is_signed, ValidationException::withMessages(['version' => 'Versi sudah ditandatangani.']));
            $signature = DocumentSignature::create(['company_id' => $version->document->company_id, 'document_version_id' => $version->id, 'signer_id' => $signer->id, 'signer_name' => $signer->name, 'signer_position' => $position, 'signature_type' => 'internal', 'status' => 'completed', 'signed_hash' => $version->sha256, 'ip_address' => $ip, 'user_agent' => $agent, 'signed_at' => now()]);
            $this->versions->lockSigned($version);
            $version->document->update(['signature_status' => 'fully_signed']);
            $this->audit->record($version->document->company_id, $signer->id, 'document.internal_signed', $signature);

            return $signature;
        }, 3);
    }

    public function createExternalRequest(DocumentVersion $version, SignatureProvider $provider, string $requestId, string $signerName, string $position): DocumentSignature
    {
        $this->assertIntegrity($version);
        $provider = SignatureProvider::findOrFail($provider->id);
        throw_unless($provider->company_id === $version->document->company_id && $provider->is_active, ValidationException::withMessages(['provider' => 'Provider tidak valid.']));

        return DocumentSignature::create(['company_id' => $provider->company_id, 'document_version_id' => $version->id, 'signature_provider_id' => $provider->id, 'signer_name' => $signerName, 'signer_position' => $position, 'signature_type' => 'external_certified', 'status' => 'pending', 'signed_hash' => $version->sha256, 'external_request_id' => $requestId]);
    }

    public function handleWebhook(SignatureProvider $provider, string $eventId, int $timestamp, string $raw, string $signature): SignatureWebhookReceipt
    {
        throw_if(abs(now()->timestamp - $timestamp) > 300, ValidationException::withMessages(['timestamp' => 'Webhook kedaluwarsa atau berpotensi replay.']));
        $expected = hash_hmac('sha256', $timestamp.'.'.$raw, (string) $provider->webhook_secret_encrypted);
        throw_unless(hash_equals($expected, $signature), ValidationException::withMessages(['signature' => 'Signature webhook tidak valid.']));

        return DB::transaction(function () use ($provider, $eventId, $timestamp, $raw) {
            if ($old = SignatureWebhookReceipt::where('signature_provider_id', $provider->id)->where('event_id', $eventId)->first()) {
                return $old;
            }$payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $receipt = SignatureWebhookReceipt::create(['signature_provider_id' => $provider->id, 'event_id' => $eventId, 'event_type' => $payload['event'] ?? 'unknown', 'payload_hash' => hash('sha256', $raw), 'provider_timestamp' => date('Y-m-d H:i:s', $timestamp)]);
            if (($payload['event'] ?? null) === 'signature.completed') {
                $record = DocumentSignature::with('version.document')->where('signature_provider_id', $provider->id)->where('external_request_id', $payload['request_id'] ?? '')->lockForUpdate()->firstOrFail();
                throw_unless(hash_equals($record->signed_hash, $record->version->sha256), ValidationException::withMessages(['hash' => 'Hash versi berubah.']));
                $record->update(['status' => 'completed', 'signed_at' => now()]);
                $this->versions->lockSigned($record->version);
                $record->version->document->update(['signature_status' => 'fully_signed']);
            }$receipt->update(['status' => 'processed', 'processed_at' => now()]);

            return $receipt->refresh();
        }, 3);
    }

    private function assertIntegrity(DocumentVersion $version): void
    {
        throw_unless(Storage::disk($version->disk)->exists($version->path), ValidationException::withMessages(['file' => 'File tidak tersedia.']));
        $actual = hash_file('sha256', Storage::disk($version->disk)->path($version->path));
        throw_unless(hash_equals($version->sha256, $actual), ValidationException::withMessages(['hash' => 'Integritas file gagal.']));
    }
}
