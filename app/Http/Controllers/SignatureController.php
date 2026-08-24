<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\DocumentVersion;
use App\Models\SignatureProvider;
use App\Services\DocumentSignatureService;
use App\Services\PileQrService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    /** Tandatangani banyak versi sekaligus (batch). Per versi: skip bila sudah signed. */
    public function batchInternal(Request $request, CurrentCompany $current, DocumentSignatureService $service)
    {
        $data = $request->validate(['version_ids' => ['required', 'array', 'min:1', 'max:50'], 'version_ids.*' => ['integer'], 'position' => ['required', 'max:150']]);
        $versions = DocumentVersion::whereIn('id', $data['version_ids'])
            ->whereHas('document', fn ($q) => $q->where('company_id', $current->id()))
            ->with('document')
            ->get();
        $signed = $skipped = 0;
        foreach ($versions as $version) {
            if ($version->is_signed) {
                $skipped++;

                continue;
            }
            try {
                $service->signInternal($version, $request->user(), $data['position'], $request->ip(), $request->userAgent());
                $signed++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        return back()->with('status', "Batch selesai: {$signed} ditandatangani, {$skipped} dilewati (sudah signed / gagal integritas).");
    }

    /**
     * Halaman verifikasi publik (ADR-075): tanpa login, menampilkan status
     * kriptografis signature — hash terikat versi + keutuhan file pada storage.
     * Token = id-HMAC sehingga tidak dapat ditebak; data sensitif tidak disingkap.
     */
    public function verify(Request $request, string $token)
    {
        $signature = DocumentSignature::findByVerificationToken((string) $token);
        abort_unless($signature !== null, 404);

        $result = $signature->verificationResult();
        $verifyUrl = url('/verify/'.$token);
        $qrSvg = app(PileQrService::class)->svgForPileUrl($verifyUrl);

        return view('verify', ['signature' => $signature->load('version.document'), 'result' => $result, 'qrSvg' => $qrSvg, 'token' => $token]);
    }

    private function owned(DocumentVersion $version, CurrentCompany $current): void
    {
        abort_unless($version->document()->where('company_id', $current->id())->exists(), 404);
    }
}
