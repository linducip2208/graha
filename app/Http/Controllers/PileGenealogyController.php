<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\Project;
use App\Services\BoredPileGenealogyService;
use App\Support\Tenancy\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PileGenealogyController extends Controller
{
    /** Pile Genealogy: seluruh siklus hidup satu titik pile dalam satu halaman. */
    public function show(Request $request, BoredPile $pile, CurrentCompany $current, BoredPileGenealogyService $service)
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        $data = $service->build($pile);
        $service->recordViewed($pile, $request->user(), 'field.pile_genealogy_viewed');

        return view('projects.genealogy', $data);
    }

    /** As-built report PDF per pile (stream). */
    public function asBuilt(Request $request, BoredPile $pile, CurrentCompany $current, BoredPileGenealogyService $service)
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        $data = $service->build($pile);
        $service->recordViewed($pile, $request->user(), 'field.pile_asbuilt_exported');

        return Pdf::loadView('pdf.pile-as-built', [
            'sections' => collect([$this->decorateForPdf($data)]),
            'batch' => false,
        ])->setPaper('a4', 'portrait')->download('as-built-'.$pile->pile_number.'.pdf');
    }

    /** Batch export as-built: satu PDF berisi seluruh pile proyek. */
    public function batchAsBuilt(Request $request, Project $project, CurrentCompany $current, BoredPileGenealogyService $service)
    {
        abort_unless($project->company_id === $current->id(), 404);
        $piles = BoredPile::where('project_id', $project->id)->orderBy('pile_number')->get();
        abort_if($piles->isEmpty(), 404);
        $sections = $piles->map(fn (BoredPile $pile) => $this->decorateForPdf($service->build($pile)));
        $service->recordViewed($piles->first(), $request->user(), 'field.pile_asbuilt_batch_exported');

        return Pdf::loadView('pdf.pile-as-built', ['sections' => $sections, 'batch' => true])
            ->setPaper('a4', 'portrait')
            ->download('as-built-'.$project->code.'.pdf');
    }

    /** Foto evidence di-embed base64 agar DomPDF dapat menampilkannya tanpa sesi. */
    private function decorateForPdf(array $section): array
    {
        $section['evidences'] = $section['evidences']->take(8)->map(function ($ev) {
            $diskName = filled($ev->disk) ? $ev->disk : 'local';
            if ($diskName === 'local') {
                $disk = Storage::disk($diskName);
                if ($disk->exists($ev->disk_path)) {
                    $ev->setAttribute('src', 'data:'.$ev->mime.';base64,'.base64_encode($disk->get($ev->disk_path)));
                }
            }

            return $ev;
        });

        return $section;
    }
}
