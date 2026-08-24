<?php

namespace Tests\Feature\Experience;

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NavigationComposerTest extends TestCase
{
    use RefreshDatabase;

    private function setupManager(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'fin-nc'], ['name' => 'Fin NC']);
        foreach (['finance.manage', 'project.view', 'tender.view'] as $perm) {
            $p = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$p->id]);
        }
        $m = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $m->id, 'role_id' => $role->id]);

        return [$company, $user];
    }

    public function test_nav_rename_and_terminology_apply_to_sidebar(): void
    {
        [$company, $user] = $this->setupManager();
        CompanyExperience::create([
            'company_id' => $company->id,
            'nav_config' => ['labels' => ['1' => 'Pengadaan & Gudang'], 'hidden' => []],
            'terminology' => ['Tender & Pelanggan' => 'Lelang & Klien'],
            'published_at' => now(),
        ]);

        $html = $this->actingAs($user)->withSession(['company_id' => $company->id])
            ->get('/dashboard')->assertOk()->getContent();

        // Rename workspace + terminology diterapkan server-side.
        $this->assertStringContainsString('Pengadaan &amp; Gudang', $html);
        $this->assertStringContainsString('Lelang &amp; Klien', $html);
        $this->assertStringNotContainsString('>Tender &amp; Pelanggan<', $html);
    }

    public function test_hidden_workspace_still_allows_direct_url(): void
    {
        [$company, $user] = $this->setupManager();
        CompanyExperience::create([
            'company_id' => $company->id,
            'nav_config' => ['hidden' => [1]], // sembunyikan Komersial (index 1)
            'published_at' => now(),
        ]);

        $html = $this->actingAs($user)->withSession(['company_id' => $company->id])
            ->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('Komersial', $html);

        // Hidden menu TIDAK mencabut permission: direct URL tetap 200.
        $this->get('/admin/tenders')->assertOk();
    }
}
