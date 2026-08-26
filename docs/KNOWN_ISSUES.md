# Known Issues Register

| ID | Severity | Status | Issue | Safe disposition |
|---|---|---|---|---|
| PR-B01 | P0 | OPEN | Staging DR restore rehearsal belum dilakukan. | Release blocker; ikuti `DR_REHEARSAL.md`, jangan restore ke database live. |
| PR-B02 | P0 | OPEN | Delapan critical UAT scenario belum ditandatangani. | Release blocker; catat tester, environment, hasil, dan issue reference. |
| PR-B03 | P1 | OPEN | Target deployment belum membuktikan HTTPS, queue worker, cron heartbeat, mail, serta fresh verified backup. | Release blocker sampai `production:check` PASS pada target. |
| PR-B04 | P1 | OPEN | Migration rehearsal dengan salinan data perusahaan yang realistis belum dilakukan. | Jalankan staging clone, `migrate --pretend`, migration, verifier, dan reconciliation. |
| PR-B05 | P1 | OPEN | Baseline kinerja 100+ project/10.000+ pile/10.000+ dokumen belum direkam. | Load test staging dan simpan percentile/query evidence. |
| PR-B06 | P1 | OPEN | Opening balance, AR/AP, inventory, fixed asset, WIP, cash/bank belum direkonsiliasi untuk perusahaan go-live. | Memerlukan sign-off Finance/Accounting perusahaan. |
| PR-B07 | P0 | OPEN | Database lokal yang diaudit masih memiliki akun pada namespace demo yang dikenal. | Jangan membawa akun/data demo ke production; buat admin produksi eksplisit dan pastikan `production:check` PASS. |
| PR-L01 | P2 | BLOCKED | Vendor-specific caliper import belum tersedia. | Generic CSV tetap tersedia; menunggu spesifikasi vendor nyata. |
| PR-L02 | P2 | BLOCKED | Unrealized FX revaluation belum tersedia. | Realized FX didukung; menunggu keputusan kebijakan accounting. |
| PR-L03 | P2 | PARTIAL | Offline field hanya draft/retry, bukan full offline-first. | Jangan menjanjikan semua workflow berjalan offline. |
| PR-L04 | P2 | EXTERNAL | Integrasi pajak/e-Bupot tergantung provider dan regulasi eksternal. | Validasi per deployment dan kontrak provider. |

Tidak ada item OPEN boleh disembunyikan dengan mengubah status dokumentasi tanpa bukti pelaksanaan.
