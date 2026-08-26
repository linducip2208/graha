<?php

namespace App\Http\Controllers;

use App\Models\StoredFile;
use App\Services\AuditTrail;
use App\Services\Storage\ObjectStorageService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

/**
 * Pintu akses tunggal semua file privat (ADR-048/ADR-051):
 * auth → company → record scope → permission → temporary URL / streaming.
 * Tidak ada jalur publik langsung ke object storage.
 */
class StoredFileController extends Controller
{
    public function __construct(
        private ObjectStorageService $storage,
        private AuditTrail $audit,
    ) {}

    public function preview(Request $request, StoredFile $file, CurrentCompany $current): mixed
    {
        $file = $this->authorizeAccess($file, $current, $request);
        $variant = (string) $request->query('variant', '');
        if ($variant !== '' && $file->category === 'photo' && in_array($variant, StoredFile::VARIANT_TYPES, true)) {
            $resolved = $variant === 'original' ? ($file->variant_type === null ? $file : $file->originalFile) : $file->variant($variant) ?? $file;
            $file = $this->authorizeAccess($resolved, $current, $request, 'file_viewed');
        }
        if (! $this->storage->existsFile($file)) {
            abort(404);
        }

        return $this->respond($file, inline: true);
    }

    public function download(Request $request, StoredFile $file, CurrentCompany $current): mixed
    {
        $file = $this->authorizeAccess($file, $current, $request, 'file_downloaded');
        if (! $this->storage->existsFile($file)) {
            abort(404);
        }

        return $this->respond($file, inline: false);
    }

    /** Rantai otorisasi penuh; mengembalikan row yang sudah divalidasi. */
    private function authorizeAccess(StoredFile $file, CurrentCompany $current, Request $request, string $event = 'file_viewed'): StoredFile
    {
        $file = StoredFile::findOrFail($file->id);
        // Company isolation: 404 (bukan 403) agar tidak membocorkan keberadaan file.
        abort_unless((int) $file->company_id === (int) $current->id(), 404);
        abort_unless($request->user()->companies()
            ->whereKey($file->company_id)
            ->where('company_user.is_active', true)
            ->exists(), 404);
        abort_unless($request->user()->hasPermission('project.view', $file->company_id), 404);
        if (filled($file->project_id)) {
            // Project scope: project harus tetap milik company aktif.
            abort_unless($file->project?->company_id === $file->company_id, 404);
        }
        if ($file->status === 'quarantined') {
            abort(404);
        }
        $this->audit->record($file->company_id, $request->user()->id, $event, $file);

        return $file;
    }

    private function respond(StoredFile $file, bool $inline): mixed
    {
        $disposition = $inline ? 'inline' : 'attachment';
        $filename = $inline ? basename($file->object_key) : $file->downloadName();

        if (($url = $this->storage->temporaryUrlFor($file, options: [
            'ResponseContentDisposition' => "{$disposition}; filename=\"{$filename}\"",
            'ResponseContentType' => $file->mime_type,
        ])) !== null) {
            return redirect()->away($url);
        }

        $disk = $this->storage->diskFor($file);
        if ($inline) {
            return response($disk->get($file->object_key), 200, [
                'Content-Type' => $file->mime_type,
                'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        return $disk->download($file->object_key, $filename);
    }
}
