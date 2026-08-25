<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\NumberSequence;
use App\Models\PileAcceptance;
use App\Models\User;
use App\Services\Storage\EvidenceStorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Registrasi dokumen pile ke Document Registry existing (ADR-050 — tidak ada
 * subsystem dokumen kedua): generate PDF → SHA-256 → object storage →
 * Document + DocumentVersion. Regenerasi = versi baru, TIDAK overwrite.
 * Penomoran memakai engine NumberSequence (format configurable per company).
 */
class PileDocumentService
{
    public function __construct(
        private DocumentVersionService $versions,
        private EvidenceStorageService $evidenceStorage,
        private PilePdfService $pdf,
        private AuditTrail $audit,
    ) {}

    /** Simpan as-built pile sebagai document versioned + StoredFile di object storage. */
    public function storeAsBuilt(BoredPile $pile, User $actor): DocumentVersion
    {
        $bytes = Pdf::loadView('pdf.pile-as-built', [
            'sections' => collect([$this->pdf->section($pile)]),
            'batch' => false,
        ])->setPaper('a4', 'portrait')->output();

        $document = $this->resolveDocument($pile->project->company_id, 'pile_as_built', 'ASB', "As-Built Pile {$pile->pile_number} · {$pile->project->code}", $actor, (int) $pile->project->id);

        return $this->register($pile, $document, 'as-built-'.$pile->pile_number.'.pdf', $bytes, 'as_built', 'asbuilt_generated', $actor);
    }

    /** Simpan acceptance dossier pile (dokumen penerimaan komprehensif). */
    public function storeAcceptanceDossier(BoredPile $pile, User $actor): DocumentVersion
    {
        $data = $this->pdf->section($pile);
        $data['nonconformities'] = $this->pdf->linkedNonconformities($pile);
        $data['acceptance'] = PileAcceptance::where('bored_pile_id', $pile->id)->latest()->first();
        $bytes = Pdf::loadView('pdf.pile-dossier', ['d' => $data])->setPaper('a4', 'portrait')->output();

        $document = $this->resolveDocument($pile->project->company_id, 'pile_acceptance_dossier', 'DOSS', "Acceptance Dossier Pile {$pile->pile_number} · {$pile->project->code}", $actor, (int) $pile->project->id);

        return $this->register($pile, $document, 'dossier-'.$pile->pile_number.'.pdf', $bytes, 'dossier', 'acceptance_dossier_generated', $actor);
    }

    private function resolveDocument(int $companyId, string $type, string $prefix, string $title, User $actor, ?int $projectId = null): Document
    {
        return DB::transaction(function () use ($companyId, $type, $prefix, $title, $actor, $projectId) {
            NumberSequence::firstOrCreate(
                ['company_id' => $companyId, 'document_type' => $type],
                ['prefix' => $prefix, 'padding' => 4, 'last_reset_year' => now()->year]
            );

            return Document::firstOrCreate(
                ['company_id' => $companyId, 'document_type' => $type, 'title' => $title],
                [
                    'number' => app(NumberSequenceService::class)->next($companyId, $type),
                    'owner_id' => $actor->id,
                    'project_id' => $projectId,
                ]
            );
        }, 3);
    }

    private function register(BoredPile $pile, Document $document, string $filename, string $bytes, string $category, string $auditEvent, User $actor): DocumentVersion
    {
        return DB::transaction(function () use ($pile, $document, $filename, $bytes, $category, $auditEvent, $actor) {
            // Versi baru setiap generasi — as-built v1 tidak pernah ditimpa v2.
            $version = $this->versions->addFromContents(
                $document, $bytes, $filename, 'application/pdf',
                $actor, "Generated dari sistem untuk pile {$pile->pile_number}"
            );
            $this->evidenceStorage->storePdfBytes($pile, $category === 'as_built' ? 'as-built' : 'handover', $filename, $bytes, $actor, [
                'category' => $category,
                'document_id' => $document->id,
                'document_version_id' => $version->id,
                'audit_event' => $auditEvent,
            ]);

            return $version;
        }, 3);
    }
}
