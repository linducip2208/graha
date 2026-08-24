<?php

namespace App\Http\Controllers;

use App\Models\BoredPile;
use App\Models\StoredFile;
use App\Services\AuditTrail;
use App\Services\BoredPileGenealogyService;
use App\Services\PileDocumentService;
use App\Services\PileQrService;
use App\Services\Storage\EvidenceStorageService;
use App\Support\Tenancy\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Digital Pile Passport (ADR-049): rangkuman satu titik pile dari design
 * hingga handover — identitas, fase konstruksi, evidence terkategorisasi,
 * dokumen, QR, dan status penerimaan.
 */
class PilePassportController extends Controller
{
    public function __construct(
        private AuditTrail $audit,
        private PileQrService $qr,
    ) {}

    /** Urutan bucket fase untuk timeline visual (Phase 12). */
    public const TIMELINE = [
        'setting_out' => ['label' => 'Setting Out', 'categories' => ['setting_out']],
        'drilling' => ['label' => 'Drilling & Bore Log', 'categories' => ['drilling', 'bore_log']],
        'cleaning' => ['label' => 'Cleaning & Inspection', 'categories' => ['bottom_cleaning', 'inspection']],
        'cage' => ['label' => 'Cage & Casing', 'categories' => ['cage', 'casing']],
        'casting' => ['label' => 'Tremie & Casting', 'categories' => ['tremie', 'concrete', 'slump']],
        'testing' => ['label' => 'Testing', 'categories' => ['testing']],
        'completion' => ['label' => 'Completed', 'categories' => ['completion']],
        'other' => ['label' => 'Lainnya', 'categories' => ['ncr', 'other']],
    ];

    public function show(Request $request, BoredPile $pile, CurrentCompany $current, BoredPileGenealogyService $service)
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        $data = $service->build($pile);
        $photos = StoredFile::where('bored_pile_id', $pile->id)
            ->where('category', 'photo')
            ->whereNull('original_file_id')
            ->with(['variants', 'uploader'])
            ->orderBy('captured_at')->orderBy('created_at')->get();
        $documents = StoredFile::where('bored_pile_id', $pile->id)
            ->whereIn('category', ['as_built', 'dossier', 'handover', 'document'])
            ->whereNull('original_file_id')
            ->with(['documentVersion.document'])
            ->latest()->get();
        $timeline = collect(self::TIMELINE)
            ->map(fn ($phase, $key) => ['key' => $key] + $phase + ['photos' => $photos->whereIn('sub_category', $phase['categories'])->values()])
            ->values();
        $this->audit->record($pile->project->company_id, $request->user()->id, 'pile_passport_viewed', $pile);

        return view('projects.passport', [
            ...$data,
            'photos' => $photos,
            'documents' => $documents,
            'timeline' => $timeline,
            'qrSvg' => $this->qr->svgForPileUrl(route('piles.public', $pile->public_uuid)),
        ]);
    }

    /** Upload foto evidence terkategorisasi langsung dari halaman passport. */
    public function uploadPhoto(Request $request, BoredPile $pile, CurrentCompany $current, EvidenceStorageService $storage)
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        abort_unless($request->user()->hasPermission('project.manage', $current->id()), 403);
        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', array_keys(StoredFile::PHOTO_CATEGORIES))],
            'file' => ['required', 'file'],
            'caption' => ['nullable', 'string', 'max:300'],
            'captured_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        try {
            $stored = $storage->storePilePhoto($pile, $data['category'], $data['file'], $request->user(), [
                'caption' => $data['caption'] ?? null,
                'captured_at' => filled($data['captured_at'] ?? null) ? Carbon::parse($data['captured_at']) : null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', "Foto {$stored->original_name} tersimpan di object storage (SHA-256 terekam).");
    }

    /**
     * Entri publik via QR (Phase 9): guest → login dengan redirect kembali;
     * member aktif → pile passport. Otorisasi company tetap berlaku.
     */
    public function publicEntry(Request $request, string $publicUuid)
    {
        $pile = BoredPile::where('public_uuid', strtolower($publicUuid))->first();
        abort_if($pile === null, 404);
        if ($request->user() === null) {
            session(['url.intended' => route('piles.passport', $pile)]);

            return redirect()->route('login');
        }
        abort_unless($request->user()->companies()
            ->whereKey($pile->project->company_id)
            ->where('company_user.is_active', true)->exists(), 404);

        return redirect()->route('piles.passport', $pile);
    }

    /** Simpan As-Built sebagai dokumen berversi di registry + object storage. */
    public function storeAsBuilt(Request $request, BoredPile $pile, CurrentCompany $current, PileDocumentService $documents)
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        abort_unless($request->user()->hasPermission('project.manage', $current->id()), 403);
        $version = $documents->storeAsBuilt($pile, $request->user())->loadMissing('document');

        return back()->with('status', "As-Built tersimpan: {$version->document->number} v{$version->version} · SHA-256 ".substr($version->sha256, 0, 16).'…');
    }

    /** Buat Acceptance Dossier PDF dan daftarkan ke registry. */
    public function storeDossier(Request $request, BoredPile $pile, CurrentCompany $current, PileDocumentService $documents)
    {
        abort_unless($pile->project()->where('company_id', $current->id())->exists(), 404);
        abort_unless($request->user()->hasPermission('project.manage', $current->id()), 403);
        $version = $documents->storeAcceptanceDossier($pile, $request->user())->loadMissing('document');

        return back()->with('status', "Acceptance Dossier tersimpan: {$version->document->number} v{$version->version} · SHA-256 ".substr($version->sha256, 0, 16).'…');
    }
}
