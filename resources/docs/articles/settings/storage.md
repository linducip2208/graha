---
title: Storage & File
description: Konfigurasi penyimpanan privat Local dan S3-compatible per perusahaan.
feature_route: /admin/settings/storage
keywords: storage, s3, r2, wasabi, minio, b2
---

# Storage & File

Menu **Pengaturan → Storage & File** mengatur target Evidence dan Documents tanpa mengubah `.env`. Profil perusahaan selalu diprioritaskan; bila belum ada profil aktif, aplikasi memakai `EVIDENCE_DISK` / `DOCUMENT_DISK`, lalu disk lokal sebagai fallback aman.

## Local Storage

Pilih **Local Server**, beri nama profil, simpan, uji koneksi, lalu aktifkan. Root lokal bersifat read-only dari UI dan selalu memakai private root yang dikonfigurasi aplikasi. Browser tidak dapat memasukkan arbitrary filesystem path.

## S3-Compatible

Pilih preset untuk mengisi default UX, lalu masukkan endpoint, region, bucket, Access Key, dan Secret Key. Core hanya memakai kontrak S3; preset tidak mengubah business logic. Klik **Test Connection** untuk menguji akses bucket, write, read, checksum content, delete, cleanup, dan temporary URL. Storage remote wajib berstatus CONNECTED sebelum aktivasi.

### Cloudflare R2

1. Buat bucket di dashboard Cloudflare.
2. Buat S3 API token dengan akses bucket yang diperlukan.
3. Salin Access Key ID.
4. Salin Secret Access Key.
5. Salin endpoint `https://ACCOUNT_ID.r2.cloudflarestorage.com`.
6. Masukkan nama bucket.
7. Isi region `auto` dan aktifkan Path Style.
8. Klik **Test Connection**.
9. Aktifkan untuk Evidence, Documents, atau keduanya.

Tidak perlu mengedit `.env` bila profil UI digunakan.

### AWS S3, Wasabi, MinIO, B2, Spaces, dan Custom

AWS membutuhkan region dan endpoint boleh kosong. MinIO membutuhkan endpoint serta Path Style; HTTP/private hostname hanya diizinkan pada local/testing atau bila trusted admin mengaktifkan konfigurasi private endpoint. Wasabi, Backblaze B2, DigitalOcean Spaces, dan Custom menerima endpoint/region deployment masing-masing. Production menolak scheme selain HTTPS serta endpoint localhost/private secara default.

## Rotasi credential dan health

Credential lama hanya tampil termasking dan tidak pernah dikirim kembali ke browser. Isi field credential baru untuk merotasi; field kosong mempertahankan nilai lama. Setelah rotasi, jalankan Test Connection lagi. Dashboard menampilkan hostname endpoint, bucket, region, status, dan waktu test terakhir—tidak pernah Access Key atau Secret.

Mengganti default tidak memindahkan objek lama. Setiap `StoredFile` mempertahankan disk/profile dan snapshot locator asal sehingga tetap dapat dibaca. Replikasi secondary storage dan tool migrasi lintas-provider dicatat sebagai backlog; tidak ada replikasi palsu atau penghapusan sumber otomatis.
