<?php

namespace Tests\Feature\Foundation;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentDownloadIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_from_other_company_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        $allowed = Company::create(['code' => 'A', 'name' => 'A']);
        $blocked = Company::create(['code' => 'B', 'name' => 'B']);
        $user = User::factory()->create();
        $allowed->users()->attach($user, ['is_default' => true, 'is_active' => true]);
        $role = Role::create(['company_id' => $allowed->id, 'code' => 'reader', 'name' => 'Reader']);
        $permission = Permission::create(['code' => 'document.view', 'name' => 'View Document', 'module' => 'document']);
        $role->permissions()->attach($permission);
        $membership = DB::table('company_user')->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insert(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        $document = Document::create(['company_id' => $blocked->id, 'document_type' => 'contract', 'number' => 'B/1', 'title' => 'Rahasia', 'owner_id' => $user->id]);
        $version = DocumentVersion::create(['document_id' => $document->id, 'version' => 1, 'path' => 'secret.pdf', 'sha256' => str_repeat('a', 64), 'size_bytes' => 10, 'mime_type' => 'application/pdf', 'created_by' => $user->id]);
        $this->actingAs($user)->withSession(['company_id' => $allowed->id])->get("/admin/document-versions/{$version->id}/download")->assertNotFound();
    }
}
