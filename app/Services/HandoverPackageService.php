<?php

namespace App\Services;

use App\Models\BoredPile;
use App\Models\Document;
use App\Models\NumberSequence;
use App\Models\Project;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Storage\EvidenceStorageService;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Handover Package (ADR-053): paket ZIP berisi as-built + dossier terpilih,
 * manifest CSV. Readiness: seluruh pile scope harus ACCEPTED — jika belum,
 * lempar exception berisi daftar pile bermasalah (tanpa membuat paket).
 */
class HandoverPackageService
{
    public function __construct(
        private EvidenceStorageService $evidenceStorage,
        private ObjectStorageService $storage,
        private DocumentVersionService $versions,
        private AuditTrail $audit,
    ) {}

    /**
     * @param  array<int>|null  $pileIds  null = semua pile accepted dalam proyek
     * @return array{stored: StoredFile, piles: Collection}
     *
     * @throws ValidationException bila readiness gagal
     */
    public function build(Project $project, ?array $pileIds, User $actor): array
    {
        [$piles, $exceptions] = $this->scope($project, $pileIds);
        if ($exceptions->isNotEmpty()) {
            throw ValidationException::withMessages([
                'handover' => 'Belum siap handover — pile belum accepted: '.$exceptions->pluck('pile_number')->implode(', ').'.',
            ]);
        }

        return DB::transaction(function () use ($project, $piles, $actor) {
            $zipPath = tempnam(sys_get_temp_dir(), 'handover_');
            $zip = new ZipArchive;
            throw_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, \RuntimeException::class, 'Gagal membuat arsip ZIP.');

            $manifest = "pile_number,public_uuid,pile_status,acceptance_status,as_built_sha256,dossier_sha256\n";
            foreach ($piles as $pile) {
                $asBuiltSha = '';
                $dossierSha = '';
                foreach (['as_built' => 'as-built', 'dossier' => 'dossier'] as $category => $folder) {
                    $file = StoredFile::where('bored_pile_id', $pile->id)->where('category', $category)->latest()->first();
                    if ($file !== null && $this->storage->existsFile($file)) {
                        $zip->addFromString("{$folder}/{$pile->pile_number}-{$folder}.pdf", $this->storage->getFile($file));
                        if ($category === 'as_built') {
                            $asBuiltSha = $file->sha256;
                        } else {
                            $dossierSha = $file->sha256;
                        }
                    }
                }
                $manifest .= "{$pile->pile_number},{$pile->public_uuid},{$pile->status},".($pile->acceptance?->status ?? '-').",{$asBuiltSha},{$dossierSha}\n";
            }
            $zip->addFromString('MANIFEST.csv', $manifest);
            $zip->close();

            // Daftarkan paket ke document registry existing (versioned).
            $contents = (string) file_get_contents($zipPath);
            @unlink($zipPath);
            $document = DB::transaction(function () use ($project, $actor) {
                NumberSequence::firstOrCreate(
                    ['company_id' => $project->company_id, 'document_type' => 'pile_handover_package'],
                    ['prefix' => 'HND', 'padding' => 4, 'last_reset_year' => now()->year]
                );

                return Document::create([
                    'company_id' => $project->company_id,
                    'document_type' => 'pile_handover_package',
                    'number' => app(NumberSequenceService::class)->next($project->company_id, 'pile_handover_package'),
                    'title' => 'Handover Package · '.$project->code.' · '.now()->format('Ymd-Hi'),
                    'owner_id' => $actor->id,
                ]);
            }, 3);
            $version = $this->versions->addFromContents(
                $document, $contents, 'handover-'.$project->code.'.zip', 'application/zip',
                $actor, 'Handover package '.$piles->count().' pile', allowedMimes: ['application/zip']
            );

            $stored = $this->evidenceStorage->storeProjectArchive($project, 'handover', 'handover-'.$project->code.'.zip', $contents, $actor, [
                'document_id' => $document->id,
                'document_version_id' => $version->id,
                'audit_event' => 'handover_package_generated',
            ]);

            return ['stored' => $stored, 'piles' => $piles];
        }, 3);
    }

    /** Scope pile + daftar exception (belum accepted). */
    public function scope(Project $project, ?array $pileIds): array
    {
        $query = BoredPile::where('project_id', $project->id)->with('acceptance');
        if ($pileIds !== null && $pileIds !== []) {
            $query->whereIn('id', $pileIds);
        } else {
            // Default: hanya pile yang sudah accepted.
            $query->whereHas('acceptance', fn ($q) => $q->where('status', 'accepted'));
        }
        $piles = $query->orderBy('pile_number')->get();

        return [
            $piles,
            $piles->filter(fn (BoredPile $pile) => $pile->acceptance?->status !== 'accepted'),
        ];
    }
}
