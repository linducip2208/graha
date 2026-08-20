# Deployment

Gunakan PHP 8.3+, MySQL 8.4, Composer, Node 24+, Nginx, worker dan scheduler. Salin `.env.example`, isi secret di environment, lalu jalankan `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`, `php artisan migrate --force`, dan cache konfigurasi. Production wajib HTTPS, `APP_DEBUG=false`, cookie secure, least-privilege DB, backup terenkripsi dan restore test.
