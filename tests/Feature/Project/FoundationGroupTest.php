<?php

namespace Tests\Feature\Project;

use App\Models\BoredPile;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FoundationGroup;
use App\Models\Nonconformity;
use App\Models\Permission;
use App\Models\PileTest;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\Role;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\FoundationGroupService;
use App\Services\PileAcceptanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FoundationGroupTest extends TestCase
{
    use RefreshDatabase;

    private $company;

    private $user;

    private $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['code' => 'GP', 'name' => 'GP']);
        $this->user = User::factory()->create();
        $this->user->companies()->attach($this->company->id, ['is_default' => true, 'is_active' => true]);
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'fg-gp'], ['name' => 'FG GP']);
        foreach (['project.view', 'project.manage', 'qms.verify', 'approval.decide'] as $perm) {
            $permission = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C1', 'name' => 'Client']);
        $this->project = Project::create([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'code' => 'P-FG', 'name' => 'Proyek Grup Pondasi',
            'status' => 'in_progress', 'overbreak_tolerance_percent' => '8',
        ]);
    }

    private function makePile(string $number, array $overrides = []): BoredPile
    {
        return BoredPile::create([
            'project_id' => $this->project->id,
            'project_zone_id' => ProjectZone::firstOrCreate(['project_id' => $this->project->id, 'code' => 'A'], ['name' => 'Zona A'])->id,
            'pile_number' => $number, 'diameter_mm' => '1000', 'planned_depth_m' => '20',
            'design_easting' => '500.0000', 'design_northing' => '1200.0000',
            'actual_easting' => '500.0000', 'actual_northing' => '1200.0000',
            'created_by' => $this->user->id,
            ...$overrides,
        ]);
    }

    private function completePile(BoredPile $pile): void
    {
        $pile->update(['status' => 'completed', 'actual_depth_m' => '20.100', 'actual_toe_level' => '-19.900']);
        PileTest::create([
            'company_id' => $this->company->id, 'project_id' => $this->project->id, 'bored_pile_id' => $pile->id,
            'number' => 'PIT-'.$pile->pile_number, 'test_type' => 'PIT', 'scheduled_date' => now()->toDateString(),
            'result_status' => 'passed', 'recorded_by' => $this->user->id,
        ]);
        // Gate acceptance: as-built teregistrasi di registry.
        StoredFile::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company->id, 'project_id' => $this->project->id, 'bored_pile_id' => $pile->id,
            'category' => 'as_built', 'disk' => 'local', 'object_key' => uniqid('test/').'.pdf',
            'original_name' => 'asbuilt.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 1024, 'sha256' => str_repeat('c', 64), 'status' => 'ready',
        ]);
    }

    private function accept(BoredPile $pile): void
    {
        $service = app(PileAcceptanceService::class);
        $pending = $service->request($pile, $this->user);
        $service->reviewQa($pending->refresh(), $this->user);
        $service->reviewEngineer($pending->refresh(), $this->user);
        $service->decide($pending->refresh(), 'accepted', $this->user);
    }

    public function test_group_is_ready_when_all_members_complete_accepted_survey_settled(): void
    {
        $group = FoundationGroup::create(['company_id' => $this->company->id, 'project_id' => $this->project->id, 'name' => 'PC-01', 'type' => 'pile_cap']);
        foreach (['BP-G1', 'BP-G2'] as $number) {
            $pile = $this->makePile($number);
            $this->completePile($pile);
            $this->accept($pile);
            app(FoundationGroupService::class)->attachPile($group, $pile);
        }

        $result = app(FoundationGroupService::class)->readiness($group);
        $this->assertSame('READY', $result['status']);
        $this->assertSame([], $result['exceptions']);
        foreach ($result['checks'] as $key => $ok) {
            $this->assertTrue($ok, "Check {$key} harus lolos.");
        }
    }

    public function test_not_ready_reports_specific_exceptions_per_member(): void
    {
        $group = FoundationGroup::create(['company_id' => $this->company->id, 'project_id' => $this->project->id, 'name' => 'PC-02', 'type' => 'pile_cap']);
        // Anggota lengkap.
        $done = $this->makePile('BP-N1');
        $this->completePile($done);
        $this->accept($done);
        app(FoundationGroupService::class)->attachPile($group, $done);
        // Anggota bermasalah: masih testing, tanpa acceptance & survey.
        $lagging = $this->makePile('BP-N2', ['status' => 'testing', 'actual_easting' => null, 'actual_northing' => null]);
        PileTest::create([
            'company_id' => $this->company->id, 'project_id' => $this->project->id, 'bored_pile_id' => $lagging->id,
            'number' => 'PIT-PEND', 'test_type' => 'PIT', 'scheduled_date' => now()->toDateString(),
            'result_status' => 'scheduled', 'recorded_by' => $this->user->id,
        ]);
        app(FoundationGroupService::class)->attachPile($group->refresh(), $lagging);

        $result = app(FoundationGroupService::class)->readiness($group->refresh());
        $this->assertSame('NOT_READY', $result['status']);
        $this->assertFalse($result['checks']['all_completed']);
        $this->assertFalse($result['checks']['all_accepted']);
        $this->assertFalse($result['checks']['tests_settled']);

        $laggingReport = collect($result['piles'])->first(fn ($row) => $row['pile']->id === $lagging->id);
        $this->assertContains('status testing (belum completed)', $laggingReport['issues']);
        $this->assertContains('acceptance belum diajukan', $laggingReport['issues']);
        $this->assertContains('data survey aktual belum ada', $laggingReport['issues']);
        $this->assertContains('1 uji menunggu hasil', $laggingReport['issues']);
    }

    public function test_open_critical_ncr_linked_via_test_blocks_readiness(): void
    {
        $group = FoundationGroup::create(['company_id' => $this->company->id, 'project_id' => $this->project->id, 'name' => 'PC-03', 'type' => 'pile_cap']);
        $pile = $this->makePile('BP-C1');
        $this->completePile($pile);
        $this->accept($pile);
        $ncr = Nonconformity::create([
            'company_id' => $this->company->id, 'number' => 'NCR-FG-1', 'source_type' => 'testing',
            'severity' => 'critical', 'project_id' => $this->project->id,
            'description' => 'Anomali integritas.', 'reported_by' => $this->user->id,
            'due_at' => now()->addDays(7)->toDateString(), 'status' => 'open',
        ]);
        PileTest::where('bored_pile_id', $pile->id)->update(['ncr_number' => $ncr->number]);
        app(FoundationGroupService::class)->attachPile($group, $pile);

        $result = app(FoundationGroupService::class)->readiness($group->refresh());
        $this->assertSame('NOT_READY', $result['status']);
        $this->assertFalse($result['checks']['no_critical_ncr']);

        // NCR ditutup → READY.
        $ncr->update(['status' => 'closed']);
        $this->assertSame('READY', app(FoundationGroupService::class)->readiness($group->refresh())['status']);
    }

    public function test_http_lifecycle_and_cross_project_pile_rejected(): void
    {
        $session = ['company_id' => $this->company->id];

        $this->actingAs($this->user)->withSession($session)
            ->post('/admin/projects/'.$this->project->id.'/foundation-groups', ['name' => 'PC-WEB', 'type' => 'zone'])
            ->assertRedirect();
        $group = FoundationGroup::where('name', 'PC-WEB')->firstOrFail();

        $pile = $this->makePile('BP-W1');
        $this->actingAs($this->user)->withSession($session)
            ->post("/admin/foundation-groups/{$group->id}/piles", ['bored_pile_id' => $pile->id])
            ->assertRedirect();
        $this->assertDatabaseHas('foundation_group_piles', ['foundation_group_id' => $group->id, 'bored_pile_id' => $pile->id]);

        // Pile dari proyek lain → ditolak validasi.
        $otherProject = Project::create([
            'company_id' => $this->company->id, 'customer_id' => $this->project->customer_id, 'code' => 'P-OTHER',
            'name' => 'Proyek Lain', 'status' => 'in_progress', 'overbreak_tolerance_percent' => '8',
        ]);
        $foreignPile = BoredPile::create([
            'project_id' => $otherProject->id,
            'project_zone_id' => ProjectZone::firstOrCreate(['project_id' => $otherProject->id, 'code' => 'X'], ['name' => 'Zona X'])->id,
            'pile_number' => 'BP-X1', 'diameter_mm' => '800', 'planned_depth_m' => '15', 'created_by' => $this->user->id,
        ]);
        $this->actingAs($this->user)->withSession($session)
            ->post("/admin/foundation-groups/{$group->id}/piles", ['bored_pile_id' => $foreignPile->id])
            ->assertSessionHasErrors('bored_pile_id');

        // Detach lalu hapus grup — pile tetap aman.
        $this->actingAs($this->user)->withSession($session)
            ->post("/admin/foundation-groups/{$group->id}/piles/{$pile->id}/detach")->assertRedirect();
        $this->assertDatabaseMissing('foundation_group_piles', ['foundation_group_id' => $group->id, 'bored_pile_id' => $pile->id]);
        $this->actingAs($this->user)->withSession($session)
            ->post("/admin/foundation-groups/{$group->id}/delete")->assertRedirect();
        $this->assertDatabaseMissing('foundation_groups', ['id' => $group->id]);
        $this->assertDatabaseHas('bored_piles', ['id' => $pile->id]); // pile tidak terhapus

        // Tab piles merender section grup.
        $this->actingAs($this->user)->withSession($session)
            ->get('/admin/projects/'.$this->project->id.'?tab=piles')
            ->assertOk()
            ->assertSee('Grup Pondasi / Pile Cap');
    }
}
