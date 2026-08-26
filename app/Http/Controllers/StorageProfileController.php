<?php

namespace App\Http\Controllers;

use App\Models\CompanyStorageProfile;
use App\Models\StoredFile;
use App\Services\AuditTrail;
use App\Services\Storage\StorageConnectionTester;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StorageProfileController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        abort_unless($request->user()->hasPermission('storage.manage', $current->id()), 403);
        $profiles = CompanyStorageProfile::where('company_id', $current->id())->orderByDesc('is_active')->orderBy('name')->get();
        $base = StoredFile::where('company_id', $current->id())->whereNull('original_file_id');

        return view('settings.storage', ['profiles' => $profiles, 'objects' => (clone $base)->count(), 'bytes' => (int) (clone $base)->sum('size_bytes'),
            'evidenceProfile' => $profiles->first(fn ($p) => $p->is_active && $p->is_default_evidence),
            'documentProfile' => $profiles->first(fn ($p) => $p->is_active && $p->is_default_document)]);
    }

    public function store(Request $request, CurrentCompany $current, AuditTrail $audit)
    {
        $this->authorizeManage($request, $current);
        $data = $this->validated($request, null);
        $data += ['company_id' => $current->id(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id];
        $profile = CompanyStorageProfile::create($data);
        $audit->record($current->id(), $request->user()->id, 'storage_profile.created', $profile, $this->auditMetadata($profile));

        return back()->with('status', 'Profil storage disimpan sebagai draft. Jalankan Test Connection sebelum aktivasi.');
    }

    public function update(Request $request, CompanyStorageProfile $profile, CurrentCompany $current, AuditTrail $audit)
    {
        $this->owned($profile, $current);
        $this->authorizeManage($request, $current);
        $data = $this->validated($request, $profile);
        $rotated = filled($data['access_key_encrypted'] ?? null) || filled($data['secret_key_encrypted'] ?? null);
        foreach (['access_key_encrypted', 'secret_key_encrypted'] as $key) {
            if (! filled($data[$key] ?? null)) {
                unset($data[$key]);
            }
        }
        $data['updated_by'] = $request->user()->id;
        if ($rotated) {
            $data['credentials_updated_at'] = now();
        }
        $profile->update($data);
        $audit->record($current->id(), $request->user()->id, $rotated ? 'storage_profile.credentials_rotated' : 'storage_profile.updated', $profile, $this->auditMetadata($profile));

        return back()->with('status', 'Profil storage diperbarui.');
    }

    public function test(Request $request, CompanyStorageProfile $profile, CurrentCompany $current, StorageConnectionTester $tester, AuditTrail $audit)
    {
        $this->owned($profile, $current);
        $this->authorizeManage($request, $current);
        $result = $tester->test($profile);
        $profile->update(['last_tested_at' => now(), 'last_test_status' => $result['status'], 'last_test_message' => $result['message'], 'status' => $result['status'] === 'CONNECTED' ? 'connected' : 'failed', 'updated_by' => $request->user()->id]);
        $audit->record($current->id(), $request->user()->id, 'storage_profile.tested', $profile, $this->auditMetadata($profile, $result['status']));

        return back()->with('status', $result['message'])->with('connection_checks', $result['checks']);
    }

    public function activate(Request $request, CompanyStorageProfile $profile, CurrentCompany $current, AuditTrail $audit)
    {
        $this->owned($profile, $current);
        $this->authorizeManage($request, $current);
        if ($profile->driver === 's3' && $profile->last_test_status !== 'CONNECTED') {
            throw ValidationException::withMessages(['profile' => 'Storage remote wajib lulus Test Connection sebelum diaktifkan.']);
        }
        $usage = $request->validate(['evidence' => ['nullable', 'boolean'], 'document' => ['nullable', 'boolean']]);
        DB::transaction(function () use ($profile, $usage) {
            if (! empty($usage['evidence'])) {
                $profile->newQuery()->where('company_id', $profile->company_id)->whereKeyNot($profile->id)->update(['is_default_evidence' => false]);
            }
            if (! empty($usage['document'])) {
                $profile->newQuery()->where('company_id', $profile->company_id)->whereKeyNot($profile->id)->update(['is_default_document' => false]);
            }
            $profile->update(['is_active' => true, 'is_default_evidence' => ! empty($usage['evidence']), 'is_default_document' => ! empty($usage['document']), 'status' => 'connected']);
        });
        $audit->record($current->id(), $request->user()->id, 'storage_profile.activated', $profile, $this->auditMetadata($profile));

        return back()->with('status', 'Profil diaktifkan. File historis tetap memakai locator asalnya.');
    }

    public function disable(Request $request, CompanyStorageProfile $profile, CurrentCompany $current, AuditTrail $audit)
    {
        $this->owned($profile, $current);
        $this->authorizeManage($request, $current);
        $profile->update(['is_active' => false, 'is_default_evidence' => false, 'is_default_document' => false, 'status' => 'disabled', 'updated_by' => $request->user()->id]);
        $audit->record($current->id(), $request->user()->id, 'storage_profile.disabled', $profile, $this->auditMetadata($profile));

        return back()->with('status', 'Profil dinonaktifkan. File historis tidak dipindahkan atau dihapus.');
    }

    private function validated(Request $request, ?CompanyStorageProfile $profile): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('company_storage_profiles')->where('company_id', $profile?->company_id ?? app(CurrentCompany::class)->id())->ignore($profile)],
            'driver' => ['required', Rule::in(CompanyStorageProfile::DRIVERS)], 'provider_preset' => ['required', Rule::in(CompanyStorageProfile::PRESETS)],
            'endpoint' => ['nullable', 'string', 'max:500'], 'region' => ['nullable', 'string', 'max:100'], 'bucket' => ['nullable', 'string', 'max:255'],
            'access_key_encrypted' => [$profile ? 'nullable' : 'required_if:driver,s3', 'nullable', 'string', 'max:500'],
            'secret_key_encrypted' => [$profile ? 'nullable' : 'required_if:driver,s3', 'nullable', 'string', 'max:1000'],
            'use_path_style_endpoint' => ['nullable', 'boolean'], 'base_url' => ['nullable', 'url:http,https', 'max:500'], 'temporary_url_minutes' => ['required', 'integer', 'between:1,1440'],
        ]);
        $data['use_path_style_endpoint'] = $request->boolean('use_path_style_endpoint');
        if ($data['driver'] === 'local') {
            return array_merge($data, ['endpoint' => null, 'region' => null, 'bucket' => null, 'access_key_encrypted' => null, 'secret_key_encrypted' => null]);
        }
        $this->validateEndpoint($data['endpoint'] ?? null, $data['provider_preset']);

        return $data;
    }

    private function validateEndpoint(?string $endpoint, string $preset): void
    {
        if ($preset === 'aws-s3' && blank($endpoint)) {
            return;
        }
        if (blank($endpoint) || ! filter_var($endpoint, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages(['endpoint' => 'Endpoint S3 tidak valid.']);
        }
        $parts = parse_url($endpoint);
        $host = strtolower($parts['host'] ?? '');
        $scheme = strtolower($parts['scheme'] ?? '');
        $localAllowed = app()->environment(['local', 'testing']) || ($preset === 'minio' && config('objectstorage.allow_private_endpoints'));
        if ($scheme !== 'https' && ! $localAllowed) {
            throw ValidationException::withMessages(['endpoint' => 'Endpoint wajib HTTPS di production.']);
        }
        if (! $localAllowed && ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false && filter_var($host, FILTER_VALIDATE_IP))) {
            throw ValidationException::withMessages(['endpoint' => 'Endpoint private/localhost diblokir di production.']);
        }
    }

    private function owned(CompanyStorageProfile $profile, CurrentCompany $current): void
    {
        abort_unless($profile->company_id === $current->id(), 404);
    }

    private function authorizeManage(Request $request, CurrentCompany $current): void
    {
        abort_unless($request->user()->hasPermission('storage.manage', $current->id()), 403);
    }

    private function auditMetadata(CompanyStorageProfile $profile, ?string $result = null): array
    {
        return array_filter(['driver' => $profile->driver, 'provider_preset' => $profile->provider_preset, 'bucket' => $profile->bucket,
            'endpoint_hostname' => $profile->endpointHostname(), 'result' => $result]);
    }
}
