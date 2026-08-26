<?php

namespace Tests\Feature\Project;

use App\Models\BoredPile;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\PileGeometryReading;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Models\Role;
use App\Models\User;
use App\Services\PileSurveyService;
use App\Services\PourCurveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PourCurveGeometrySurveyTest extends TestCase
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
        $role = Role::firstOrCreate(['company_id' => $this->company->id, 'code' => 'pc-gp'], ['name' => 'PC GP']);
        foreach (['project.view', 'project.manage'] as $perm) {
            $permission = Permission::firstOrCreate(['code' => $perm], ['name' => $perm, 'module' => str($perm)->before('.')]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $membership = DB::table('company_user')->where('company_id', $this->company->id)->where('user_id', $this->user->id)->first();
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $membership->id, 'role_id' => $role->id]);
        $customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C1', 'name' => 'Client']);
        $this->project = Project::create([
            'company_id' => $this->company->id, 'customer_id' => $customer->id, 'code' => 'P-PC', 'name' => 'Proyek Pour Curve',
            'status' => 'in_progress', 'overbreak_tolerance_percent' => '8',
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

    public function test_pour_curve_computes_theoretical_vs_actual_with_variance(): void
    {
        // Diameter 1 m → luas 0.7854 m². Teoretis pada 10 m = 7.854 m³.
        $pile = $this->makePile('BP-PC');
        $service = app(PourCurveService::class);
        foreach ([['5', '4.2'], ['10', '4.3'], ['20', '6.5']] as [$depth, $volume]) {
            $service->recordInterval($pile, [
                'recorded_at' => now(), 'depth_or_level_m' => $depth, 'incremental_volume_m3' => $volume,
            ], $this->user);
        }

        $curve = $service->curve($pile);
        $this->assertCount(3, $curve['points']);
        [$p5, $p10, $p20] = $curve['points'];

        // Kumulatif aktual 5 m: 4.2 vs teoretis 3.927 → +6.95% (dalam toleransi).
        $this->assertSame(3.927, $p5['theoretical']);
        $this->assertSame(4.2, $p5['actual']);
        $this->assertEqualsWithDelta(6.95, $p5['variance_percent'], 0.05);
        // 10 m: kumulatif 8.5 vs teoretis 7.854 → +8.22% > toleransi 8% → overconsumed.
        $this->assertTrue($p10['overconsumed']);
        // 20 m: kumulatif 15.0 vs teoretis 15.708 → -4.5% → tidak overconsumed.
        $this->assertFalse($p20['overconsumed']);
        $this->assertSame('15.0000', (string) $curve['total_actual']);
    }

    public function test_geometry_csv_import_parses_rows_safely(): void
    {
        $pile = $this->makePile('BP-GEO');
        $service = app(PourCurveService::class);
        $csv = "depth,diameter_mm,dev_x,dev_y,vert\n2,1010.5,5,-3,0.12\n6,1008,,,\n";

        $count = $service->importGeometryCsv($pile, $csv, 'caliper_import', $this->user);

        $this->assertSame(2, $count); // header dilewati
        $rows = PileGeometryReading::where('bored_pile_id', $pile->id)->orderBy('depth_m')->get();
        $this->assertSame('1010.50', (string) $rows[0]->measured_diameter_mm);
        $this->assertNull($rows[1]->deviation_x_mm);
        $this->assertSame('caliper_import', $rows[0]->source);
    }

    public function test_survey_deviation_status_thresholds(): void
    {
        CompanySetting::put($this->company->id, ['survey_tolerance_m' => '0.05']);
        $service = app(PileSurveyService::class);

        // Tanpa data → NO_DATA.
        $noData = $service->deviation($this->makePile('BP-SV0'));
        $this->assertSame('NO_DATA', $noData['status']);

        // Horizontal 0.03 m → PASS.
        $pass = $service->deviation($this->makePile('BP-SV1', [
            'design_easting' => '500.0000', 'design_northing' => '1200.0000',
            'actual_easting' => '500.0240', 'actual_northing' => '1199.9950',
        ]));
        $this->assertSame('PASS', $pass['status']);

        // Horizontal ~0.08 m → WARNING (antara 1x dan 2x toleransi).
        $warning = $service->deviation($this->makePile('BP-SV2', [
            'design_easting' => '500.0000', 'design_northing' => '1200.0000',
            'actual_easting' => '500.0800', 'actual_northing' => '1200.0000',
        ]));
        $this->assertEqualsWithDelta(0.08, $warning['horizontal_deviation_m'], 0.001);
        $this->assertSame('WARNING', $warning['status']);

        // Elevasi 0.25 m → OUT_OF_TOLERANCE (> 2x).
        $out = $service->deviation($this->makePile('BP-SV3', [
            'design_top_elevation' => '12.000', 'actual_top_elevation' => '11.750',
        ]));
        $this->assertSame('OUT_OF_TOLERANCE', $out['status']);
    }

    public function test_http_endpoints_and_passport_rendering(): void
    {
        $pile = $this->makePile('BP-HTTP');
        $session = ['company_id' => $this->company->id];

        $this->actingAs($this->user)->withSession($session)
            ->post('/admin/projects/field-ops/pour-intervals', [
                'recorded_at' => now()->toDateTimeString(), 'depth_or_level_m' => '5', 'incremental_volume_m3' => '4.1',
                'bored_pile_id' => $pile->id,
            ])->assertRedirect();
        $this->assertDatabaseHas('pile_concrete_pour_intervals', ['bored_pile_id' => $pile->id, 'cumulative_volume_m3' => '4.1000']);

        $this->actingAs($this->user)->withSession($session)
            ->post('/admin/projects/field-ops/geometry-import', [
                'source' => 'survey', 'bored_pile_id' => $pile->id,
                'csv' => "3,1005,2,1,0.09\n7,1012,4,-2,0.15",
            ])->assertRedirect();
        $this->assertDatabaseCount('pile_geometry_readings', 2);

        $this->actingAs($this->user)->withSession($session)
            ->get("/admin/bored-piles/{$pile->id}/passport")
            ->assertOk()
            ->assertSee('Concrete Pour Curve')
            ->assertSee('Hole Geometry / Caliper')
            ->assertSee('Survey Deviation');
    }
}
