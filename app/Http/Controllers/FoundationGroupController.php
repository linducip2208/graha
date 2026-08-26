<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\FoundationGroup;
use App\Models\Project;
use App\Services\AuditTrail;
use App\Services\FoundationGroupService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * P11 — CRUD grup pondasi (pile cap / zona / grup kustom).
 * Readiness ditampilkan di tab Bored Pile halaman proyek.
 */
class FoundationGroupController extends Controller
{
    public function __construct(
        private AuditTrail $audit,
        private FoundationGroupService $groups,
    ) {}

    private function authorizeProject(Request $request, Project $project, CurrentCompany $current, string $permission = 'project.manage'): void
    {
        abort_unless($project->company_id === $current->id(), 404);
        abort_unless($request->user()->hasPermission($permission, $current->id()), 403);
    }

    public function store(Request $request, Project $project, CurrentCompany $current)
    {
        $this->authorizeProject($request, $project, $current);
        $data = $request->validate([
            'name' => ['required', 'max:120'],
            'type' => ['required', 'in:'.implode(',', FoundationGroup::TYPES)],
            'notes' => ['nullable', 'max:1000'],
        ]);
        $group = FoundationGroup::create([...$data, 'company_id' => $project->company_id, 'project_id' => $project->id]);
        $this->audit->record($project->company_id, $request->user()->id, 'foundation_group_created', $group);

        return back()->with('status', "Grup '{$group->name}' dibuat — tambahkan pile anggota untuk melihat readiness.");
    }

    public function attach(Request $request, FoundationGroup $group, CurrentCompany $current)
    {
        abort_unless($group->company_id === $current->id(), 404);
        abort_unless($request->user()->hasPermission('project.manage', $current->id()), 403);
        $pile = BoredPile::whereHas('project', fn ($q) => $q->where('company_id', $current->id()))
            ->findOrFail((int) $request->input('bored_pile_id'));
        try {
            $this->groups->attachPile($group, $pile);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', "Pile {$pile->pile_number} ditambahkan ke grup {$group->name}.");
    }

    public function detach(Request $request, FoundationGroup $group, BoredPile $pile, CurrentCompany $current)
    {
        abort_unless($group->company_id === $current->id() && $pile->project_id === $group->project_id, 404);
        abort_unless($request->user()->hasPermission('project.manage', $current->id()), 403);
        $this->groups->detachPile($group, $pile);

        return back()->with('status', "Pile {$pile->pile_number} dikeluarkan dari grup {$group->name}.");
    }

    public function destroy(Request $request, FoundationGroup $group, CurrentCompany $current)
    {
        abort_unless($group->company_id === $current->id(), 404);
        abort_unless($request->user()->hasPermission('project.manage', $current->id()), 403);
        // Keanggotaan ikut terhapus (cascade); pile itu sendiri TIDAK terhapus.
        $this->audit->record($group->company_id, $request->user()->id, 'foundation_group_deleted', $group);
        $group->delete();

        return back()->with('status', 'Grup dihapus — pile tidak terpengaruh.');
    }
}
