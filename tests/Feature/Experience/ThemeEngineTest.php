<?php

namespace Tests\Feature\Experience;

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ThemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ThemeEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_without_row_gets_safe_default_preset(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $resolved = app(ThemeService::class)->resolve($company->id);

        $this->assertSame('executive-navy', $resolved['config']['preset']);
        $this->assertSame('#0f2a52', $resolved['tokens']['--brand-primary']);
    }

    public function test_two_companies_resolve_different_branding(): void
    {
        $a = Company::create(['code' => 'GA', 'name' => 'A']);
        $b = Company::create(['code' => 'GB', 'name' => 'B']);
        CompanyExperience::create(['company_id' => $a->id, 'admin_theme' => 'modern-indigo', 'primary_color' => '#4f46e5', 'system_name' => 'ERP A', 'published_at' => now()]);
        CompanyExperience::create(['company_id' => $b->id, 'admin_theme' => 'industrial-amber', 'primary_color' => '#b45309', 'system_name' => 'ERP B', 'published_at' => now()]);
        $service = app(ThemeService::class);

        $ra = $service->resolve($a->id);
        $rb = $service->resolve($b->id);

        $this->assertSame('ERP A', $ra['config']['system_name']);
        $this->assertSame('ERP B', $rb['config']['system_name']);
        $this->assertNotSame($ra['tokens']['--brand-primary'], $rb['tokens']['--brand-primary']);
    }

    public function test_invalid_hex_is_rejected_by_sanitizer(): void
    {
        $service = app(ThemeService::class);
        $this->assertSame('0F2A52', $service->sanitizeHex('#0f2a52'));
        $this->assertNull($service->sanitizeHex('red; background:url(x)'));
        $this->assertNull($service->sanitizeHex('#12345'));
    }

    public function test_studio_save_persists_and_flushes_cache(): void
    {
        $company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['is_default' => true, 'is_active' => true]);
        // permission finance.manage via role
        $role = Role::firstOrCreate(['company_id' => $company->id, 'code' => 'fin'], ['name' => 'Finance']);
        $p = Permission::firstOrCreate(['code' => 'finance.manage'], ['name' => 'finance.manage', 'module' => 'finance']);
        $role->permissions()->syncWithoutDetaching([$p->id]);
        $m = DB::table('company_user')->where('company_id', $company->id)->where('user_id', $user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $m->id, 'role_id' => $role->id]);

        // warm cache lalu pastikan save mem-flush (nilai baru terlihat).
        app(ThemeService::class)->resolve($company->id);
        $this->actingAs($user)->withSession(['company_id' => $company->id])
            ->post('/admin/experience', [
                'admin_theme' => 'corporate-teal',
                'primary_color' => '#0e7490',
                'font_ui' => 'Inter',
                'system_name' => 'Tebal Corp ERP',
            ])->assertRedirect();

        ThemeService::flush($company->id);
        $resolved = app(ThemeService::class)->resolve($company->id);
        $this->assertSame('corporate-teal', $resolved['config']['preset']);
        $this->assertSame('Tebal Corp ERP', $resolved['config']['system_name']);
        $this->assertSame('#0E7490', $resolved['tokens']['--brand-primary']);

        // Injection: hex invalid ditolak validasi.
        $this->actingAs($user)->withSession(['company_id' => $company->id])
            ->from('/admin/experience')
            ->post('/admin/experience', ['admin_theme' => 'minimal-light', 'primary_color' => 'red;background:url(j)'])
            ->assertSessionHasErrors('primary_color');
    }
}
