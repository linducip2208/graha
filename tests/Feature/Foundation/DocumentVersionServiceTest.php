<?php

namespace Tests\Feature\Foundation;

use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_has_hash_and_signed_version_is_immutable(): void
    {
        Storage::fake('local');
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $document = Document::create(['company_id' => $company->id, 'document_type' => 'contract', 'number' => 'K-001', 'title' => 'Kontrak Uji', 'owner_id' => $user->id]);
        $file = UploadedFile::fake()->createWithContent('kontrak.pdf', '%PDF-1.4 isi kontrak');
        $service = app(DocumentVersionService::class);
        $version = $service->add($document, $file, $user, 'Versi awal');

        $this->assertSame(hash('sha256', '%PDF-1.4 isi kontrak'), $version->sha256);
        Storage::disk('local')->assertExists($version->path);
        $service->lockSigned($version);
        $this->expectException(\LogicException::class);
        $version->refresh()->update(['revision' => 'silent-replacement']);
    }
}
