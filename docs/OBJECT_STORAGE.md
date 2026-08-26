# Object Storage — Operations Maturity

Status per 2026-08-26 (ADR-078). Fondasi: ADR-048 (`StoredFile` metadata +
`ObjectStorageService` abstraksi provider-agnostic).

## Storage Dashboard — DONE

`GET /admin/storage` (permission `document.view`) —
`resources/views/storage/dashboard.blade.php`.

- Total objek + bytes, per kategori (photo/document/as_built/dossier/handover),
  per status lifecycle, per disk, foto per fase (top), dan per proyek.
- Filter per proyek via dropdown.
- **Semua agregasi dari metadata DB** (`stored_files`) dengan group-by — tidak
  ada bucket scan per request.

## Retention Policy — DONE

Metadata states: `ready → archived → pending_delete → deleted`
(kolom `archived_at`, `retention_due_at`). `StorageRetentionService`:

- `archive()` / `restore()` — teraudit.
- `markPendingDelete()` — hanya bila company mengaktifkan
  `delete_after_archive_days`; default OFF = **tidak ada penghapusan otomatis**.
- `physicalDelete()` — butuh permission baru **`storage.manage`**, file harus
  berstatus pending_delete, dan kategori **as_built / dossier / handover
  DIBLOKIR** (dokumen historis/legal tidak boleh dihapus senyap). Setiap aksi
  masuk audit log hash-chained.
- `archiveCandidates(project)` — daftar kandidat saat proyek closed > N hari
  (`archive_after_project_closed_days`, default OFF). Hanya menghasilkan
  daftar; eksekusi tetap eksplisit.

## Presigned / Direct Upload — DONE

`DirectUploadService::requestUpload()`:

- Disk S3-compatible + adapter mendukung `temporaryUploadUrl` → mode
  `presigned` (URL berbatas waktu 30 menit).
- Selain itu → mode `server` fallback (jalur upload server existing).
- Metadata divalidasi di awal; StoredFile dibuat berstatus `uploading` dengan
  `upload_id` UUID dari client.

## Upload Queue — DONE

`POST /admin/storage/finalize-upload` {upload_id, sha256?, size?}:

- **Idempotent**: finalize ulang dengan upload_id sama mengembalikan hasil yang
  sama tanpa duplikasi (file sudah READY → langsung dikembalikan).
- Verifikasi ukuran fisik object di disk vs yang dilaporkan client.
- Checksum SHA-256 client diterima dan terekam pada registry.
- Status UI queue (pending/uploading/success/failed) dipetakan dari status
  StoredFile; retry aman karena idempotensi di atas.

Catatan jujur: belum ada service-worker offline PWA penuh — antrean lokal di
sisi browser adalah pekerjaan lanjutan bila arsitektur frontend mendukung.
