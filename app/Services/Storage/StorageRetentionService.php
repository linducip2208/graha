<?php

namespace App\Services\Storage;

use App\Models\CompanySetting;
use App\Models\Project;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retensi metadata (ADR-078): kebijakan archive/pending_delete/delete per
 * company. Default SEMUA OFF — tidak ada penghapusan fisik otomatis.
 *
 * Kategori historis (as_built, dossier, handover) dan dokumen bertanda tangan
 * TIDAK BOLEH dihapus fisik secara senyap — service menolak dan hanya
 * memperbolehkan archive.
 */
class StorageRetentionService
{
    /** Kategori yang dilindungi dari penghapusan fisik (historis/legal). */
    public const PROTECTED_CATEGORIES = ['as_built', 'dossier', 'handover'];

    public function __construct(private ObjectStorageService $storage, private AuditTrail $audit) {}

    public function policyEnabled(int $companyId): bool
    {
        return filled(CompanySetting::val($companyId, 'archive_after_project_closed_days'))
            || filled(CompanySetting::val($companyId, 'delete_after_archive_days'));
    }

    public function archive(StoredFile $file, User $actor, string $reason = ''): StoredFile
    {
        throw_unless($file->status === 'ready', ValidationException::withMessages(['status' => 'Hanya file READY yang dapat diarsipkan.']));
        DB::transaction(function () use ($file, $actor) {
            $file->update(['status' => 'archived', 'archived_at' => now()]);
            $this->audit->record($file->company_id, $actor->id, 'storage.file_archived', $file);
        });

        return $file->refresh();
    }

    public function restore(StoredFile $file, User $actor): StoredFile
    {
        throw_unless(in_array($file->status, ['archived', 'pending_delete'], true), ValidationException::withMessages(['status' => 'Hanya file archived/pending_delete yang dapat dipulihkan.']));
        DB::transaction(function () use ($file, $actor) {
            $file->update(['status' => 'ready', 'archived_at' => null, 'retention_due_at' => null]);
            $this->audit->record($file->company_id, $actor->id, 'storage.file_restored', $file);
        });

        return $file->refresh();
    }

    /** Tandai pending_delete — HANYA bila company mengaktifkan delete policy. */
    public function markPendingDelete(StoredFile $file, User $actor): StoredFile
    {
        $days = CompanySetting::val($file->company_id, 'delete_after_archive_days');
        throw_if(! filled($days), ValidationException::withMessages(['policy' => 'Kebijakan delete_after_archive_days tidak aktif — penghapusan dinonaktifkan default.']));
        throw_unless($file->status === 'archived', ValidationException::withMessages(['status' => 'Pending delete hanya dari status archived.']));
        DB::transaction(function () use ($file, $actor, $days) {
            $file->update(['status' => 'pending_delete', 'retention_due_at' => now()->addDays((int) $days)]);
            $this->audit->record($file->company_id, $actor->id, 'storage.file_marked_pending_delete', $file);
        });

        return $file->refresh();
    }

    /**
     * Penghapusan fisik: permission storage.manage + status pending_delete +
     * kategori TIDAK terproteksi. Setiap aksi teraudit. Kandidat otomatis
     * TIDAK PERNAH dihapus oleh scheduler tanpa pemanggilan eksplisit ini.
     */
    public function physicalDelete(StoredFile $file, User $actor): void
    {
        throw_unless($file->company_id !== null && $file->status === 'pending_delete', ValidationException::withMessages(['status' => 'Penghapusan fisik hanya untuk file berstatus pending_delete.']));
        throw_if(in_array($file->category, self::PROTECTED_CATEGORIES, true), ValidationException::withMessages(['category' => "Kategori {$file->category} adalah dokumen historis/legal — penghapusan fisik diblokir."]));
        DB::transaction(function () use ($file, $actor) {
            foreach ($file->variants as $variant) {
                $this->storage->delete($variant->object_key, $variant->disk);
                $variant->update(['status' => 'deleted']);
            }
            $this->storage->delete($file->object_key, $file->disk);
            $file->update(['status' => 'deleted']);
            $this->audit->record($file->company_id, $actor->id, 'storage.file_deleted_physically', $file);
        });
    }

    /**
     * Kandidat arsip: proyek closed > N hari (kebijakan company). Deterministik,
     * hanya MENGHASILKAN daftar — eksekusi tetap lewat archive() eksplisit.
     */
    public function archiveCandidates(Project $project): array
    {
        $days = CompanySetting::val($project->company_id, 'archive_after_project_closed_days');
        if (! filled($days) || ! in_array($project->status, ['closed', 'completed'], true)) {
            return [];
        }
        $cutoff = now()->subDays((int) $days);

        return StoredFile::where('project_id', $project->id)
            ->where('status', 'ready')->whereNull('original_file_id')
            ->where('updated_at', '<=', $cutoff)
            ->pluck('id')->all();
    }
}
