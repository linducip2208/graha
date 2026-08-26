# Disaster Recovery

## Prinsip

Database backup disimpan sebagai gzip pada private local disk dan diregistrasikan dengan SHA-256. Backup tidak menyertakan `.env`, API secret, credential storage, atau seluruh binary object storage. Bucket/object storage harus dipulihkan atau tetap tersedia secara terpisah; metadata database saja tidak menggantikan object binary.

## Verifikasi dan pemulihan database

1. Jalankan `php artisan backup:verify [id]` dan hentikan proses bila hasil bukan PASS.
2. Salin backup terverifikasi ke host recovery melalui kanal terenkripsi.
3. Ambil automatic pre-restore backup dari database target.
4. Aktifkan maintenance mode dan hentikan queue worker.
5. Verifikasi versi MySQL, nama database target, ruang disk, serta hak akses.
6. Restore ke database staging/temporary lebih dahulu; jalankan migration status dan smoke test.
7. Restore production hanya dengan persetujuan Super Admin dan catatan change request.
8. Jalankan `php artisan up`, restart queue, lalu periksa System Health dan audit.

UI sengaja tidak menerima arbitrary filesystem path. Restore production otomatis tetap ditutup sampai seluruh preflight dan authorization diuji.

## Dependency object storage

Pastikan profile/disk historis tetap dapat dijangkau, credential telah dipasang ulang, dan sampled SHA-256 `StoredFile` cocok. Jangan membuat bucket publik. Authorized streaming tetap menjadi fallback bila temporary URL tidak tersedia.

## Queue, scheduler, dan mail

Kosongkan failed job hanya setelah payload dipahami. Restart worker dengan mekanisme deployment, pasang cron `php artisan schedule:run` setiap menit, tunggu heartbeat maksimal 10 menit, lalu kirim Test Email. SMTP password tidak berasal dari backup database kecuali integrasi terenkripsi memang dikonfigurasi di database.

## Dokumentasi dan rollback

Screenshot docs diregenerasi dengan `php artisan docs:capture`; sumbernya lokal `storage/app/docs`, bukan object storage company. Rollback aplikasi memakai release/code sebelumnya, dependency lock yang sama, dan database backup pre-restore. Jangan rollback schema destruktif tanpa migration rehearsal.
