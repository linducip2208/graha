# Admin Runbook

## Deploy

1. Pastikan commit/tag teridentifikasi dan review `php artisan migrate --pretend`.
2. Buat serta verify backup database; jangan lanjut bila verification gagal.
3. Aktifkan maintenance, deploy code/dependency lock, jalankan migration, cache config/routes/views.
4. Restart queue worker melalui Supervisor/systemd dan nonaktifkan maintenance.
5. Jalankan `production:check`, `accounting:verify`, `inventory:verify`, `foundation:verify`, smoke test login/project/evidence/report.

## Queue dan scheduler

Supervisor template berada di `deploy/supervisor.conf`. Worker harus memiliki restart policy, bounded timeout/tries, dan log rotation. Cron production menjalankan `php artisan schedule:run` setiap menit. Heartbeat maksimal berumur 10 menit; stale heartbeat adalah incident.

## Backup dan restore

Gunakan Backup Center atau `backup:database`, lalu `backup:verify`. Restore hanya dari `BackupRecord` terdaftar dan dilakukan pada staging rehearsal terlebih dahulu. Ikuti `DISASTER_RECOVERY.md`; jangan memasukkan password DB pada command line atau log.

## Storage, mail, cache, logs

Rotasi credential melalui Storage & File lalu Test Connection. Historical locator tidak dipindahkan otomatis. Test Email dari System Health. Cache clear memakai Artisan setelah deploy. Production memakai `APP_DEBUG=false`, structured sanitized logs, OS-level log rotation, dan correlation/reference ID pada incident.

## Incident procedure

Catat waktu, environment, correlation/reference, impact, dan operator. Bekukan destructive action; ambil backup bila database konsisten. Periksa System Health, queue, scheduler, storage, dan logs. Jangan menyatakan rollback database sukses tanpa verifikasi checksum dan reconciliation.
