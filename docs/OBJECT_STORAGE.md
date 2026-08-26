# Object Storage Architecture

Graha memahami dua driver: **local** dan **S3-compatible**. Cloudflare R2, AWS S3, Wasabi, MinIO, Backblaze B2, DigitalOcean Spaces, dan endpoint custom hanyalah pilihan deployment/preset UI.

## Resolution precedence

1. Profil company aktif untuk usage Evidence atau Document.
2. Disk legacy dari `EVIDENCE_DISK` / `DOCUMENT_DISK` (generic `OBJECT_STORAGE_DISK` menjadi default baru bila legacy variable tidak diisi).
3. Private local disk.

Disk `docs` selalu lokal di `storage/app/docs` dan tidak masuk resolution profile.

Credential profil memakai encrypted cast Laravel, hidden dari serialization, dimasking pada UI, dan tidak dimasukkan ke audit/event/error. `StoredFile.storage_profile_id` bersifat nullable agar file legacy tetap memakai kolom `disk`. Snapshot locator menyimpan driver, endpoint, region, bucket, base URL, dan Path Style tanpa secret agar perubahan default tidak mengubah lokasi historis.

## Connection and capabilities

Connection test membuat object kecil di `.healthcheck/{company_uuid}/{uuid}.txt`, membaca dan memverifikasi isinya, menghapus object, lalu mencoba temporary URL. Cleanup selalu dicoba. Presigned PUT dan temporary URL dipilih berdasarkan capability adapter, bukan nama provider; bila tidak tersedia, server upload dan authorized streaming tetap digunakan.

## Migration backlog

Command `storage:migrate` dan secondary replication belum diaktifkan pada fase ini. Implementasi kelak wajib copy → checksum verify → register locator baru, tidak menghapus sumber kecuali `--delete-source` diberikan secara eksplisit.

## Storage Dashboard — DONE

`GET /admin/storage` (permission `document.view`) mengagregasi total object/bytes, kategori, lifecycle, disk/profile, fase foto, dan proyek hanya dari metadata `stored_files`; tidak ada bucket scan per request. Dashboard kini juga menampilkan target Evidence dan Documents aktif tanpa credential.

## Retention Policy — DONE

Lifecycle existing `ready → archived → pending_delete → deleted` tetap ditangani `StorageRetentionService`. Policy delete default OFF, seluruh aksi teraudit, dan kategori as-built/dossier/handover diblokir dari physical delete. `archiveCandidates()` hanya menghasilkan kandidat; eksekusi tetap eksplisit.

## Presigned / Direct Upload — DONE

`DirectUploadService::requestUpload()` memilih presigned PUT bila adapter aktif memiliki capability `temporaryUploadUrl`; selain itu mengembalikan mode server. Finalize tetap idempotent memakai `upload_id`, memverifikasi ukuran object, dan mencatat SHA-256. Pemilihan ini tidak memeriksa nama provider.

Catatan: service-worker offline PWA penuh belum tersedia dan tetap menjadi pekerjaan lanjutan bila frontend mengadopsi antrean lokal.
