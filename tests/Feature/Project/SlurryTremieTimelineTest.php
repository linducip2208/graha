<?php

namespace Tests\Feature\Project;

use App\Models\BoredPile;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\ConcreteDelivery;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\PileTremieLog;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\Role;
use App\Models\User;
use App\Services\PileReadinessService;
use App\Services\SlurryControlService;
use App\Services\TremieLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SlurryTremieTimelineTest extends TestCase
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
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'st-gp'], ['name' => 'ST GP']);
        foreach (['project.view', 'project.manage', 'qms.verify'] as $perm) {
            $permission = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C1', 'name' => 'Client']);
        $this->project = Project::create([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'code' => 'P-ST', 'name' => 'Proyek Slurry Tremie',
            'status' => 'in_progress', 'overbreak_tolerance_percent' => '10',
        ]);
    }

    private function makePile(string $number, array $overrides = []): BoredPile
    {
        return BoredPile::create([
            'project_id' => $this->project->id,
            'project_zone_id' => ProjectZone::firstOrCreate(['project_id' => $this->project->id, 'code' => 'A'], ['name' => 'Zona A'])->id,
            'pile_number' => $number, 'diameter_mm' => '1000', 'planned_depth_m' => '20',
            'created_by' => $this->user->id,
            ...$overrides,
        ]);
    }

    public function test_slurry_policy_disabled_is_record_only_no_gate(): void
    {
        // Default: policy OFF.
        $pile = $this->makePile('BP-SL1', ['slurry_type' => 'bentonite']);
        $service = app(SlurryControlService::class);
        $this->assertFalse($service->policyEnabled($this->company->id));

        $test = $service->record($pile, [
            'phase' => 'before_drilling', 'type' => 'bentonite', 'tested_at' => now(),
            'density' => '1.5', // di luar standar umum — tapi tanpa kebijakan tidak apa-apa
        ], $this->user);

        $this->assertSame([], $service->violations($test)); // tanpa policy = tanpa pelanggaran
        // Readiness tetap skip slurry.
        $result = app(PileReadinessService::class)->drillReadiness($pile);
        $slurryCheck = collect($result['checklist'])->firstWhere('key', 'slurry_ready');
        $this->assertSame('skip', $slurryCheck['state']);
    }

    public function test_slurry_violations_detected_when_policy_enabled_and_gate_in_readiness(): void
    {
        CompanySetting::put($this->company->id, [
            'slurry_policy_enabled' => '1',
            'slurry_density_min' => '1.05', 'slurry_density_max' => '1.2',
            'slurry_sand_content_max' => '6',
        ]);
        $pile = $this->makePile('BP-SL2', ['slurry_type' => 'bentonite']);
        $service = app(SlurryControlService::class);

        $test = $service->record($pile, [
            'phase' => 'before_drilling', 'type' => 'bentonite', 'tested_at' => now(),
            'density' => '1.35', 'sand_content_percent' => '9',
        ], $this->user);
        $codes = collect($service->violations($test))->pluck('code');
        $this->assertContains('density_max', $codes);
        $this->assertContains('sand_content_max', $codes);
        $this->assertNotContains('density_min', $codes); // limit min kosong? tidak — terisi dan nilai di atasnya
        $this->assertNotContains('ph_max', $codes); // nilai null → dilewati

        // Keputusan QA.
        $decided = $service->decide($test, 'accepted', $this->user);
        $this->assertSame('accepted', $decided->status);
        $this->assertTrue($service->preDrillAccepted($pile));
        $this->assertFalse($service->preCastAccepted($pile));

        // Readiness drill lolos slurry setelah accepted.
        $result = app(PileReadinessService::class)->drillReadiness($pile);
        $slurryCheck = collect($result['checklist'])->firstWhere('key', 'slurry_ready');
        $this->assertSame('pass', $slurryCheck['state']);
    }

    public function test_tremie_embedment_calculated_deterministically_with_flags(): void
    {
        CompanySetting::put($this->company->id, [
            'tremie_log_enabled' => '1',
            'tremie_min_embedment_m' => '2.0', 'tremie_max_embedment_m' => '6.0',
        ]);
        $pile = $this->makePile('BP-TR');
        $service = app(TremieLogService::class);

        $normal = $service->record($pile, ['recorded_at' => now(), 'tremie_total_length_m' => '24', 'tremie_tip_depth_m' => '20'], $this->user);
        $this->assertSame('4.00', (string) $normal->embedment_m); // 24 - 20
        $this->assertSame('normal', $normal->flag);

        $shallow = $service->record($pile, ['recorded_at' => now()->addMinutes(30), 'tremie_total_length_m' => '21.2', 'tremie_tip_depth_m' => '20'], $this->user);
        $this->assertSame('1.20', (string) $shallow->embedment_m);
        $this->assertSame('out_of_range', $shallow->flag); // < min 2.0

        $deep = $service->record($pile, ['recorded_at' => now()->addMinutes(60), 'tremie_total_length_m' => '26.5', 'tremie_tip_depth_m' => '19'], $this->user);
        $this->assertSame('7.50', (string) $deep->embedment_m);
        $this->assertSame('out_of_range', $deep->flag); // > max + 1

        // Flag hanya indikator — status pile TIDAK berubah otomatis.
        $this->assertSame('planned', $pile->refresh()->status);

        // Sequence berurutan per pile.
        $this->assertSame([1, 2, 3], PileTremieLog::where('bored_pile_id', $pile->id)->orderBy('sequence')->pluck('sequence')->all());

        // Readiness cast: tremie pass karena log ada.
        $result = app(PileReadinessService::class)->castReadiness($pile);
        $tremieCheck = collect($result['checklist'])->firstWhere('key', 'tremie_ready');
        $this->assertSame('pass', $tremieCheck['state']);
    }

    public function test_concrete_delivery_sequence_and_gap_computation(): void
    {
        $pile = $this->makePile('BP-TL');
        foreach ([['DO-A', '-3 hours'], ['DO-B', '-1 hour']] as $i => [$do, $when]) {
            ConcreteDelivery::create([
                'company_id' => $this->company->id, 'project_id' => $this->project->id, 'bored_pile_id' => $pile->id,
                'delivery_order_number' => $do, 'truck_number' => 'T-'.$do, 'grade' => "fc'25",
                'ordered_volume_m3' => '30', 'delivered_volume_m3' => '30', 'accepted_volume_m3' => '30',
                'rejected_volume_m3' => '0', 'status' => 'approved', 'sequence' => $i + 1,
                'batch_time' => now()->modify($when)->subMinutes(40),
                'arrived_at' => now()->modify($when),
                'pour_started_at' => now()->modify($when)->addMinutes(5),
                'pour_finished_at' => now()->modify($when)->addMinutes(35),
                'recorded_by' => $this->user->id, 'idempotency_key' => 'tl-'.$do,
            ]);
        }

        [$first, $second] = ConcreteDelivery::where('bored_pile_id', $pile->id)->orderBy('arrived_at')->get();
        // Sequence diisi oleh FieldOpsService saat entri HTTP; data manual di test memakai urutan eksplisit.
        $this->assertSame(1, (int) $first->sequence);

        // Waiting: batch -40 menit → tiba = 40 menit.
        $this->assertSame(40, $second->waitingMinutes());
        // Discharge: 5→35 = 30 menit.
        $this->assertSame(30, $first->dischargeMinutes());
        // Gap DO-A selesai (-2h25m) → DO-B mulai (-0h55m) = 90 menit.
        $gap = $second->gapFromPreviousMinutes();
        $this->assertNotNull($gap);
        $this->assertEqualsWithDelta(90, $gap, 2);
    }

    public function test_http_endpoints_record_slurry_and_tremie(): void
    {
        $pile = $this->makePile('BP-HTTP');

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post('/admin/projects/field-ops/slurry', [
                'bored_pile_id' => $pile->id, 'phase' => 'before_casting', 'type' => 'polymer',
                'tested_at' => now()->toDateTimeString(), 'density' => '1.08', 'ph' => '9',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('slurry_tests', ['bored_pile_id' => $pile->id, 'phase' => 'before_casting']);

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post('/admin/projects/field-ops/tremie', [
                'bored_pile_id' => $pile->id, 'recorded_at' => now()->toDateTimeString(),
                'tremie_total_length_m' => '22', 'tremie_tip_depth_m' => '18.4',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('pile_tremie_logs', ['bored_pile_id' => $pile->id, 'embedment_m' => '3.60']);

        // Halaman field-ops merender section baru tanpa error.
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get('/admin/projects/field-ops?project='.$this->project->id)
            ->assertOk()
            ->assertSee('Slurry Control')
            ->assertSee('Tremie Log');
    }
}
