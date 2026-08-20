# Deployment

Gunakan PHP 8.3+, MySQL 8.4, Composer, Node 24+, Nginx, worker dan scheduler. Salin `.env.example`, isi secret di environment, lalu jalankan `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`, `php artisan migrate --force`, dan cache konfigurasi. Production wajib HTTPS, `APP_DEBUG=false`, cookie secure, least-privilege DB, backup terenkripsi dan restore test.

## Prosedur produksi

1. Buat database dan user MySQL khusus aplikasi dengan hak minimum pada satu schema.
2. Isi `APP_KEY`, `APP_URL`, database, mail, queue dan storage melalui secret manager/server environment.
3. Jalankan `php artisan migrate --force`, `php artisan db:seed --force`, `php artisan storage:link`, lalu `php artisan optimize`.
4. Aktifkan worker dari `deploy/supervisor.conf` dan cron `* * * * * cd /var/www/grahapondasi && php artisan schedule:run >> /dev/null 2>&1`.
5. Pastikan document root Nginx menunjuk hanya ke `public/`, HTTPS/HSTS aktif, dan upload sensitif tidak disajikan langsung.
6. Backup MySQL dan file dokumen setiap hari ke lokasi terenkripsi berbeda; lakukan restore drill minimal tiap kuartal.
7. Sebelum rilis jalankan `php artisan test`, `vendor/bin/pint --test`, `npm run build`, dan `php artisan about --only=environment`.

Scheduler menjalankan monitoring SLA approval per jam dan pembaruan status evidence QMS kedaluwarsa setiap hari pukul 01:30. Gunakan timezone `Asia/Jakarta` atau timezone perusahaan yang telah disepakati.
