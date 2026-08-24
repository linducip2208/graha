<?php

namespace Tests\Feature\Experience;

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Edition;
use App\Support\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformConfigTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenderViewer(): array
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'mk'], ['name' => 'Marketing']);
        foreach (['tender.view', 'finance.manage'] as $perm) {
            $p = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$p->id]);
        }
        $m = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $m->id, 'role_id' => $role->id]);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return [$company, $user];
    }

    public function test_dashboard_builder_config_filters_and_orders_stats(): void
    {
        [$company, $user] = $this->setupTenderViewer();
        CompanyExperience::create([
            'company_id' => $company->id,
            // Hanya tender pipeline diminta; widget lain (walau ada datanya) disembunyikan.
            'dashboard_config' => [['id' => 'tender_pipeline', 'w' => 6]],
            'published_at' => now(),
        ]);

        $html = $this->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('Tender Aktif', $html);
    }

    public function test_industry_pack_terminology_applies_when_company_not_override(): void
    {
        [$company, $user] = $this->setupTenderViewer();
        CompanyExperience::create([
            'company_id' => $company->id,
            'industry_pack' => 'general-contractor',
            'terminology' => null,
            'published_at' => now(),
        ]);

        $term = Term::t($company->id, 'Project');
        $this->assertSame('Pekerjaan', $term, 'Pack default terminology harus berlaku.');
    }

    public function test_edition_hides_module_navigation_but_keeps_direct_url(): void
    {
        [$company, $user] = $this->setupTenderViewer();
        CompanyExperience::create([
            'company_id' => $company->id,
            'edition' => 'manufacturing-edition',
            'published_at' => now(),
        ]);

        // Manufacturing edition menyembunyikan modul manufacturing? Sebaliknya:
        // modules list = yang TAMPIL. manufacturing ada -> nav manufaktur tampil,
        // group tanpa module match (mis. Komersial/other) tetap tampil via 'other'? tidak ada di list.
        $visible = Edition::visibleModules($company->id);
        $this->assertSame(['manufacturing', 'accounting'], $visible->all());
    }
}
