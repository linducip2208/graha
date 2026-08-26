<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\CompanyExperience;
use App\Models\FieldEvidence;
use App\Models\Nonconformity;
use App\Models\PileTest;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Generator PDF pile (as-built & acceptance dossier).
 * Foto di-embed dari SALINAN preview beresolusi terkendali (bukan original
 * full-size) agar ukuran PDF tetap kecil dan readable — original tetap utuh
 * di object storage sebagai evidence (ADR-057).
 */
class PilePdfService
{
    public function __construct(
        private BoredPileGenealogyService $genealogy,
        private ObjectStorageService $objectStorage,
    ) {}

    /** Section siap render untuk satu pile (kompatibel template pile-as-built). */
    public function section(BoredPile $pile): array
    {
        $data = $this->genealogy->build($pile);
        $data['evidences'] = $this->embedEvidencePreviews($data['evidences'], 8);

        return $data;
    }

    /**
     * Embed salinan preview foto sebagai data-URI untuk DomPDF.
     * Prioritas: varian preview StoredFile → original StoredFile → legacy local file.
     */
    public function embedEvidencePreviews(Collection $evidences, int $limit = 8): Collection
    {
        return $evidences->take($limit)->map(function (FieldEvidence $ev) {
            try {
                $stored = $ev->storedFile;
                $candidate = $stored?->variant('preview') ?? $stored;
                if ($candidate !== null && $this->objectStorage->existsFile($candidate)) {
                    $ev->setAttribute('src', 'data:'.$candidate->mime_type.';base64,'.base64_encode($this->objectStorage->getFile($candidate)));

                    return $ev;
                }
                $diskName = filled($ev->disk) ? $ev->disk : 'local';
                if ($diskName === 'local' && Storage::disk($diskName)->exists($ev->disk_path)) {
                    $ev->setAttribute('src', 'data:'.$ev->mime.';base64,'.base64_encode(Storage::disk($diskName)->get($ev->disk_path)));
                }
            } catch (\Throwable) {
                // Foto gagal di-embed tidak boleh menggagalkan dokumen.
            }

            return $ev;
        });
    }

    /** NCR yang tertaut nyata ke pile ini lewat kolom ncr_number pada hasil uji. */
    public function linkedNonconformities(BoredPile $pile): Collection
    {
        $numbers = PileTest::where('bored_pile_id', $pile->id)
            ->whereNotNull('ncr_number')
            ->pluck('ncr_number')
            ->unique();

        return Nonconformity::where('company_id', $pile->project->company_id)
            ->where('project_id', $pile->project_id)
            ->whereIn('number', $numbers)
            ->orderBy('number')
            ->get();
    }

    /**
     * White-label branding untuk PDF (ADR-058): logo/system name/footer dari
     * Experience company aktif — tidak ada hardcode nama aplikasi.
     */
    public function branding(int $companyId): array
    {
        $theme = app(ThemeService::class)->resolve($companyId);
        $brand = ['tokens' => $theme['tokens'], 'config' => $theme['config'], 'logo_data_uri' => null];
        try {
            $row = CompanyExperience::find($companyId);
            $logoPath = $row?->logo_path;
            if (filled($logoPath) && Storage::disk('local')->exists($logoPath)) {
                $mime = match (strtolower(pathinfo($logoPath, PATHINFO_EXTENSION))) {
                    'png' => 'image/png',
                    'svg' => 'image/svg+xml',
                    default => 'image/jpeg',
                };
                // DomPDF hanya stabil dengan raster; SVG dilewati.
                if ($mime !== 'image/svg+xml') {
                    $brand['logo_data_uri'] = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($logoPath));
                }
            }
        } catch (\Throwable) {
        }

        return $brand;
    }
}
