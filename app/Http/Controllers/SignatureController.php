<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\DocumentVersion;
use App\Models\SignatureProvider;
use App\Services\DocumentSignatureService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class SignatureController extends Controller
{
    public function index(CurrentCompany $current)
    {
        return view('signatures.index', ['providers' => SignatureProvider::where('company_id', $current->id())->orderBy('name')->get(), 'documents' => Document::where('company_id', $current->id())->with('versions')->latest()->get(), 'signatures' => DocumentSignature::where('company_id', $current->id())->with(['version.document', 'provider'])->latest()->limit(100)->get()]);
    }

    public function provider(Request $request, CurrentCompany $current)
    {
        $data = $request->validate(['name' => ['required', 'max:255'], 'api_format' => ['required', 'in:rest_hmac,redirect,embedded'], 'base_url' => ['nullable', 'url', 'max:500'], 'api_key' => ['nullable', 'max:4000'], 'webhook_secret' => ['nullable', 'max:4000']]);
        SignatureProvider::updateOrCreate(['company_id' => $current->id(), 'name' => $data['name']], ['api_format' => $data['api_format'], 'base_url' => $data['base_url'] ?? null, 'api_key_encrypted' => filled($data['api_key'] ?? null) ? $data['api_key'] : null, 'webhook_secret_encrypted' => filled($data['webhook_secret'] ?? null) ? $data['webhook_secret'] : null, 'is_active' => true]);

        return back()->with('status', 'Provider signature disimpan terenkripsi.');
    }

    public function internal(Request $request, DocumentVersion $version, CurrentCompany $current, DocumentSignatureService $service)
    {
        $this->owned($version, $current);
        $data = $request->validate(['position' => ['required', 'max:150']]);
        $service->signInternal($version, $request->user(), $data['position'], $request->ip(), $request->userAgent());

        return back()->with('status', 'Versi ditandatangani dan dikunci.');
    }

    public function external(Request $request, DocumentVersion $version, CurrentCompany $current, DocumentSignatureService $service)
    {
        $this->owned($version, $current);
        $data = $request->validate(['provider_id' => ['required', 'exists:signature_providers,id'], 'external_request_id' => ['required', 'max:120'], 'signer_name' => ['required', 'max:255'], 'signer_position' => ['required', 'max:150']]);
        $provider = SignatureProvider::where('company_id', $current->id())->findOrFail($data['provider_id']);
        $service->createExternalRequest($version, $provider, $data['external_request_id'], $data['signer_name'], $data['signer_position']);

        return back()->with('status', 'External signature request terikat versi/hash.');
    }

    public function webhook(Request $request, SignatureProvider $provider, DocumentSignatureService $service)
    {
        $eventId = $request->header('X-Signature-Event-Id');
        $timestamp = $request->header('X-Signature-Timestamp');
        $signature = $request->header('X-Signature-Hmac');
        abort_unless(filled($eventId) && ctype_digit((string) $timestamp) && filled($signature), 400);
        $service->handleWebhook($provider, $eventId, (int) $timestamp, $request->getContent(), $signature);

        return response()->json(['received' => true]);
    }

    private function owned(DocumentVersion $version, CurrentCompany $current): void
    {
        abort_unless($version->document()->where('company_id', $current->id())->exists(), 404);
    }
}
