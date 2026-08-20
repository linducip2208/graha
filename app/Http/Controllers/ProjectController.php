<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\Project;
use App\Models\ProjectZone;
use App\Services\BoredPileService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(CurrentCompany $current)
    {
        return view('projects.index', ['projects' => Project::where('company_id', $current->id())->withCount('boredPiles')->latest()->get(), 'zones' => ProjectZone::whereHas('project', fn ($q) => $q->where('company_id', $current->id()))->with('project')->get(), 'piles' => BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $current->id()))->with(['project', 'zone'])->latest()->paginate(30)]);
    }

    public function zone(Request $r, CurrentCompany $current)
    {
        $d = $r->validate(['project_id' => ['required', 'exists:projects,id'], 'code' => ['required', 'max:30'], 'name' => ['required', 'max:150']]);
        abort_unless(Project::where('company_id', $current->id())->whereKey($d['project_id'])->exists(), 422);
        ProjectZone::create($d);

        return back()->with('status', 'Zona ditambahkan.');
    }

    public function pile(Request $r, CurrentCompany $current)
    {
        $d = $r->validate(['project_id' => ['required', 'exists:projects,id'], 'project_zone_id' => ['required', 'exists:project_zones,id'], 'pile_number' => ['required', 'max:60'], 'diameter_mm' => ['required', 'decimal:0,2', 'gt:0'], 'planned_depth_m' => ['required', 'decimal:0,3', 'gt:0']]);
        $zone = ProjectZone::where('project_id', $d['project_id'])->whereKey($d['project_zone_id'])->exists();
        abort_unless($zone && Project::where('company_id', $current->id())->whereKey($d['project_id'])->exists(), 422);
        BoredPile::create([...$d, 'created_by' => $r->user()->id]);

        return back()->with('status', 'Titik bored pile ditambahkan.');
    }

    public function transition(Request $r, BoredPile $pile, CurrentCompany $current, BoredPileService $service)
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        $d = $r->validate(['status' => ['required', 'string'], 'notes' => ['nullable', 'max:1000']]);
        $service->transition($pile, $d['status'], $r->user(), $d['notes'] ?? null);

        return back()->with('status', 'Status diperbarui.');
    }

    public function concrete(Request $r, BoredPile $pile, CurrentCompany $current, BoredPileService $service)
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        $d = $r->validate(['actual_depth_m' => ['required', 'decimal:0,3', 'gt:0'], 'actual_concrete_m3' => ['required', 'decimal:0,4', 'gte:0']]);
        $service->recordConcrete($pile, $d['actual_depth_m'], $d['actual_concrete_m3'], $r->user());

        return back()->with('status','Data beton diperbarui.');
    }
}
