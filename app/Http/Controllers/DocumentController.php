<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentTransmittal;
use App\Models\DocumentVersion;
use App\Models\NumberSequence;
use App\Services\AuditTrail;
use App\Services\DocumentVersionService;
use App\Services\NumberSequenceService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $query = Document::where('company_id', $companyId)->with(['owner', 'versions']);
        $search = trim((string) $request->query('q'));
        if ($search !== '') {
            $query->where(function ($w) use ($search) {
                $w->where('title', 'like', "%{$search}%")->orWhere('number', 'like', "%{$search}%");
            });
        }
        if ($type = (string) $request->query('type')) {
            $query->where('document_type', $type);
        }
        if ($status = (string) $request->query('status')) {
            $query->where('workflow_status', $status);
        }
        $documents = $query->latest()->paginate(20)->withQueryString();

        // KPI dari data riil (bukan metrik palsu): workflow_status hanya
        // 'draft' | 'approved'; signature_status 'unsigned' | 'fully_signed'.
        $base = Document::where('company_id', $companyId);
        $stats = [
            'total' => (clone $base)->count(),
            'approved' => (clone $base)->where('workflow_status', 'approved')->count(),
            'draft' => (clone $base)->where('workflow_status', 'draft')->count(),
            'signed' => (clone $base)->where('signature_status', 'fully_signed')->count(),
        ];
        $types = Document::where('company_id', $companyId)->distinct()->orderBy('document_type')->pluck('document_type');

        return view('documents.index', ['documents' => $documents, 'stats' => $stats, 'types' => $types]);
    }

    public function show(Document $document, CurrentCompany $current)
    {
        abort_unless($document->company_id === $current->id(), 404);
        $document->load(['owner', 'versions.creator']);
        // Tab Approval & Activity hanya dirender bila datanya benar-benar ada
        // (tidak ada modul approval/distribusi palsu).
        $approvals = $document->approvalRequests()->with(['decisions.actor', 'workflow'])->get();
        $activity = AuditLog::where('company_id', $current->id())
            ->where(function ($q) use ($document) {
                $q->where(function ($w) use ($document) {
                    $w->where('auditable_type', Document::class)->where('auditable_id', $document->id);
                })->orWhere(function ($w) use ($document) {
                    $w->where('auditable_type', DocumentVersion::class)->whereIn('auditable_id', $document->versions->modelKeys());
                });
            })
            ->with('actor:id,name')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        return view('documents.show', ['document' => $document, 'approvals' => $approvals, 'activity' => $activity]);
    }

    public function store(Request $request, CurrentCompany $current, NumberSequenceService $numbers, DocumentVersionService $versions)
    {
        $data = $request->validate(['document_type' => ['required', 'string', 'max:80'], 'title' => ['required', 'string', 'max:200'], 'file' => ['required', 'file', 'max:20480'], 'change_reason' => ['required', 'string', 'max:500']]);
        $document = Document::create(['company_id' => $current->id(), 'document_type' => $data['document_type'], 'number' => $numbers->next($current->id(), 'generic'), 'title' => $data['title'], 'owner_id' => $request->user()->id]);
        try {
            $versions->add($document, $data['file'], $request->user(), $data['change_reason']);
        } catch (\Throwable $exception) {
            $document->delete();
            throw $exception;
        }

        return back()->with('status', 'Dokumen berhasil didaftarkan.');
    }

    public function download(DocumentVersion $version, CurrentCompany $current)
    {
        abort_unless($version->document()->where('company_id', $current->id())->exists(), 404);
        abort_unless(Storage::disk($version->disk)->exists($version->path), 404);
        // Nomor dokumen bisa memuat "/" dari format sekuens — header Content-Disposition
        // tidak boleh memuat slash, jadi fallback name disanitasi.
        $filename = str_replace(['/', '\\'], '-', $version->document->number.'-v'.$version->version.'.'.pathinfo($version->path, PATHINFO_EXTENSION));

        return Storage::disk($version->disk)->download($version->path, $filename);
    }

    public function transmittals(CurrentCompany $current)
    {
        $transmittals = DocumentTransmittal::where('company_id', $current->id())->with('items.version.document')->orderByDesc('transmit_date')->limit(60)->get();

        return view('documents.transmittals', [
            'transmittals' => $transmittals,
            'versions' => DocumentVersion::whereHas('document', fn ($q) => $q->where('company_id', $current->id()))->with('document')->orderByDesc('created_at')->limit(200)->get(),
            'stats' => ['total' => $transmittals->count(), 'sent' => $transmittals->where('status', 'sent')->count(), 'acknowledged' => $transmittals->where('status', 'acknowledged')->count()],
        ]);
    }

    public function storeTransmittal(Request $request, CurrentCompany $current, AuditTrail $audit)
    {
        $data = $request->validate(['recipient' => ['required', 'max:200'], 'purpose' => ['nullable', 'max:255'], 'transmit_date' => ['required', 'date'], 'method' => ['required', 'in:email,courier,hand,portal'], 'notes' => ['nullable', 'max:1000'], 'version_ids' => ['required', 'array', 'min:1'], 'version_ids.*' => ['integer']]);
        foreach ($data['version_ids'] as $versionId) {
            abort_unless(DocumentVersion::whereHas('document', fn ($q) => $q->where('company_id', $current->id()))->whereKey($versionId)->exists(), 422);
        }
        NumberSequence::firstOrCreate(['company_id' => $current->id(), 'document_type' => 'transmittal'], ['prefix' => 'TRM', 'padding' => 4, 'last_reset_year' => now()->year]);
        $transmittal = DocumentTransmittal::create([...collect($data)->except('version_ids')->all(), 'company_id' => $current->id(), 'number' => app(NumberSequenceService::class)->next($current->id(), 'transmittal'), 'created_by' => $request->user()->id]);
        foreach (array_unique($data['version_ids']) as $versionId) {
            $transmittal->items()->create(['document_version_id' => $versionId]);
        }
        $audit->record($current->id(), $request->user()->id, 'document.transmittal_sent', $transmittal);

        return back()->with('status', "Transmittal {$transmittal->number} tercatat (".count($data['version_ids']).' dokumen).');
    }

    public function acknowledgeTransmittal(DocumentTransmittal $transmittal, CurrentCompany $current, AuditTrail $audit)
    {
        abort_unless($transmittal->company_id === $current->id(), 404);
        throw_unless($transmittal->status === 'sent', ValidationException::withMessages(['status' => 'Transmittal sudah diakui.']));
        $transmittal->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
        $audit->record($current->id(), auth()->id(), 'document.transmittal_acknowledged', $transmittal);

        return back()->with('status', "Transmittal {$transmittal->number} ditandai diterima penerima.");
    }
}
