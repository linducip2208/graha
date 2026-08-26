---
title: Backup & Recovery
description: Backup MySQL privat, checksum, verifikasi, retention, dan prosedur disaster recovery.
feature_route: /admin/settings/system/backups
keywords: backup, restore, mysql, recovery, checksum
---

# Backup & Recovery

Backup database adalah resource instance-global. `backup.view` mengizinkan read-only; create/verify/restore memerlukan `backup.manage`. Company `storage.manage` tidak memberi akses. Backup dibuat ke private local disk sebagai `.sql.gz`, memakai `MYSQL_PWD` pada environment proses agar password tidak masuk command line atau shell history.

Klik **Verify Backup** atau jalankan `php artisan backup:verify [id]`. Verifikasi memeriksa locator terdaftar, path traversal, keberadaan file, checksum, gzip readability, dan header SQL. Hasil gagal tidak pernah dilabeli aman untuk restore.

Retention menjaga beberapa backup sukses terbaru dan tidak menyalin `.env`, API secret, credential storage, maupun seluruh binary object storage. Ikuti `docs/DISASTER_RECOVERY.md` untuk drill pemulihan. Restore production langsung dari UI masih ditutup sampai seluruh high-risk preflight dan authorization tersedia.
