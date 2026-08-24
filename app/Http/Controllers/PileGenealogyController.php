<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\Project;
use App\Services\BoredPileGenealogyService;
use App\Services\PilePdfService;
use App\Support\Tenancy\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PileGenealogyController extends Controller
{
    public function __construct(private PilePdfService $pdfService) {}

    /** Pile Genealogy: seluruh siklus hidup satu titik pile dalam satu halaman. */
    public function show(Request $request, BoredPile $pile, CurrentCompany $current, BoredPileGenealogyService $service)
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        $data = $service->build($pile);
        $service->recordViewed($pile, $request->user(), 'field.pile_genealogy_viewed');

        return view('projects.genealogy', $data);
    }

    /** As-built report PDF per pile (stream on-demand; versi tersimpan via PileDocumentService). */
    public function asBuilt(Request $request, BoredPile $pile, CurrentCompany $current, BoredPileGenealogyService $service)
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        $data = $service->build($pile);
        $service->recordViewed($pile, $request->user(), 'field.pile_asbuilt_exported');

        return Pdf::loadView('pdf.pile-as-built', [
            'sections' => collect([$this->pdfService->section($pile)]),
            'batch' => false,
            'experience' => $this->pdfService->branding($pile->project->company_id),
        ])->setPaper('a4', 'portrait')->download('as-built-'.$pile->pile_number.'.pdf');
    }

    /** Batch export as-built: satu PDF berisi seluruh pile proyek (stream). */
    public function batchAsBuilt(Request $request, Project $project, CurrentCompany $current, BoredPileGenealogyService $service)
    {
        abort_unless($project->company_id === $current->id(), 404);
        $piles = BoredPile::where('project_id', $project->id)->orderBy('pile_number')->get();
        abort_if($piles->isEmpty(), 404);
        // Batch besar dibatasi per request agar memori aman; gunakan filter zona untuk proyek sangat besar.
        abort_if($piles->count() > 120, 422, 'Batch terlalu besar — pecah per zona.');
        $sections = $piles->map(fn (BoredPile $pile) => $this->pdfService->section($pile));
        $service->recordViewed($piles->first(), $request->user(), 'field.pile_asbuilt_batch_exported');

        return Pdf::loadView('pdf.pile-as-built', [
            'sections' => $sections,
            'batch' => true,
            'experience' => $this->pdfService->branding($project->company_id),
        ])
            ->setPaper('a4', 'portrait')
            ->download('as-built-'.$project->code.'.pdf');
    }
}
