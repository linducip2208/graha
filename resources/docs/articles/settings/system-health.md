---
title: System Health
description: Memeriksa database, cache, queue, scheduler, mail, storage, disk, runtime, dan backup.
feature_route: /admin/settings/system/health
keywords: health, queue, scheduler, mail, failed jobs
---

# System Health

Buka **Pengaturan → System Health** menggunakan akun berizin tinggi. Status HEALTHY, WARNING, CRITICAL, atau UNKNOWN berasal dari pemeriksaan aktual yang cepat—bukan asumsi bahwa cron sehat karena halaman web hidup.

Scheduler menulis heartbeat setiap lima menit. Status menjadi CRITICAL bila heartbeat lebih tua dari sepuluh menit. Queue menampilkan driver, pending job bila backend dapat diukur, failed jobs, dan waktu kegagalan tertua. Retry/delete failed job selalu membutuhkan permission tinggi dan masuk audit.

Test Email mengirim pesan aman tanpa data bisnis. UI hanya menyimpan waktu, status, serta pesan yang sudah disanitasi; password SMTP tidak pernah ditampilkan.

Object Storage hanya membaca metadata profil aktif dan connection status terakhir. Halaman ini tidak melakukan bucket scan.
