<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Object Storage — konfigurasi terpusat (ADR-048)
    |--------------------------------------------------------------------------
    |
    | Semua file fisik (foto evidence, PDF as-built, dossier, handover)
    | disimpan melalui disk Laravel yang S3-compatible. Database hanya
    | menyimpan metadata + checksum SHA-256.
    |
    | Provider apapun yang mendukung S3 API dapat dipakai tanpa mengubah
    | kode bisnis: AWS S3, Cloudflare R2, MinIO, Wasabi, Backblaze B2,
    | DigitalOcean Spaces — cukup set env FILESYSTEM_DISK / EVIDENCE_DISK
    | beserta kredensialnya di config/filesystems.php.
    |
    */

    // Disk evidence/foto lapangan. Default mengikuti EVIDENCE_DISK lalu FILESYSTEM_DISK.
    'evidence_disk' => env('EVIDENCE_DISK', env('OBJECT_STORAGE_DISK', env('FILESYSTEM_DISK', 'local'))),

    // Disk registry dokumen (DocumentVersionService). Default local agar perilaku existing tak berubah.
    'document_disk' => env('DOCUMENT_DISK', env('OBJECT_STORAGE_DISK', 'local')),

    // Umur temporary URL (menit) untuk disk non-local.
    'temporary_url_minutes' => (int) env('OBJECT_STORAGE_TEMP_URL_MINUTES', 15),

    // Trusted-admin escape hatch for private MinIO endpoints; keep false in production.
    'allow_private_endpoints' => (bool) env('OBJECT_STORAGE_ALLOW_PRIVATE_ENDPOINTS', false),

    // Batas ukuran upload (bytes) — configurable, jangan hardcode di controller.
    'max_size_image' => (int) env('OBJECT_STORAGE_MAX_IMAGE_MB', 5) * 1024 * 1024,
    'max_size_pdf' => (int) env('OBJECT_STORAGE_MAX_PDF_MB', 50) * 1024 * 1024,

    /*
    | Whitelist MIME konten (divalidasi dari isi byte, bukan sekadar nama file).
    | SVG sengaja tidak diizinkan untuk evidence lapangan.
    */
    'allowed_image_mime' => ['image/jpeg', 'image/png', 'image/webp'],
    'allowed_pdf_mime' => ['application/pdf'],

];
