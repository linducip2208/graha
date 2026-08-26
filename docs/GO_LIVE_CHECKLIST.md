# Go-Live Checklist

- [ ] DNS dan HTTPS valid
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, APP_KEY dan APP_URL benar
- [ ] Secure/encrypted session cookie dan trusted proxy ditinjau
- [ ] `php artisan migrate --pretend` direview dan migration staging PASS
- [ ] Backup dibuat dan `backup:verify` PASS
- [ ] Storage profile/fallback diuji; evidence historical sampled
- [ ] Mail test PASS atau warning diterima resmi
- [ ] Queue worker dikelola Supervisor/systemd dan failed queue kosong/ditriase
- [ ] Cron scheduler terpasang; heartbeat HEALTHY
- [ ] Platform admin dan company roles direview
- [ ] Company, project scope, serta persona UAT selesai
- [ ] Opening balance, AR, AP, inventory, fixed asset, WIP, cash/bank direkonsiliasi dan sign-off
- [ ] Import counts/balances direkonsiliasi
- [ ] Seluruh critical scenario `UAT_SCENARIOS.md` PASS
- [ ] DR staging rehearsal `DR_REHEARSAL.md` PASS
- [ ] `production:check`, `inventory:verify`, `foundation:verify` PASS
- [ ] System Health tidak memiliki CRITICAL
- [ ] Release tag hanya dibuat setelah verdict PRODUCTION READY

## Rollback

Sebelum deploy simpan backup verified, commit/tag, dan migration notes. Bila gagal: aktifkan maintenance, rollback code ke release kompatibel, putuskan rollback/forward-fix database berdasarkan migration review, restart queue, jalankan health dan reconciliation. Jangan menjalankan destructive rollback schema tanpa rehearsal.
