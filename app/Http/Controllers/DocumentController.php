<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentVersionService;
use App\Services\NumberSequenceService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(CurrentCompany $current)
    {
        return view('documents.index', ['documents' => Document::where('company_id', $current->id())->with(['owner', 'versions'])->latest()->paginate(20)]);
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

        return Storage::disk($version->disk)->download($version->path, $version->document->number.'-v'.$version->version.'.'.pathinfo($version->path, PATHINFO_EXTENSION));
    }
}
