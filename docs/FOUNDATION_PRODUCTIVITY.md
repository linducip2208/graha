# Foundation Productivity & Forecast

Status per 2026-08-26 (ADR-077). Semua angka dari data nyata — tanpa KPI
hardcoded dan tanpa AI.

## Metrik

| Metrik | Sumber | Lokasi UI |
|---|---|---|
| Meter bor/hari | MAX(depth_to_m) lapisan per hari drilling | Control Tower (Produktivitas 7 Hari) |
| Pile selesai/hari | aktivitas to_status=completed dalam window | Control Tower + forecast |
| Avg cycle time | aktivitas pertama → terlesai per pile | Control Tower |
| Durasi drilling / casting | bored_pile_drillings; pour intervals | projectMetrics.phase_hours |
| Breakdown per rig | join drillings × equipment | projectMetrics.by_rig |
| Breakdown diameter | bored_piles aktual | projectMetrics.by_diameter |
| Utilisasi rig, meter/jam, liter/meter | jam drilling + fuel_usages nyata | rigPerformance(rig) |

## Lookahead 3/7 Hari — DONE

Deterministik: pile berstatus planned/hold/setting_out (dengan/tanpa
planned_date dalam window), snapshot readiness terakhir per pile, jumlah
constraint aktif. Perencanaan manual lewat endpoint
`POST /admin/bored-piles/{pile}/planned-date` (permission `project.manage`,
teraudit).

## Completion Forecast — DONE

`FoundationForecastService::forecast(project)`:

1. Remaining = pile belum completed/rejected.
2. Rate kandidat: weighted 7d(2×)+14d → fallback 7d → 14d.
3. Tanpa riwayat penyelesaian → `insufficient_history`, tanggal null.
4. Confidence: high ≥10 pile selesai di window, medium ≥3, selain itu low.

Tidak ada AI/scheduling kompleks — hanya ekstrapolasi linear yang bisa
diaudit dari data yang sama.

## Performance Notes

- Durasi dihitung PHP-side (portable lintas MySQL/SQLite).
- Query agregasi group-by; tidak ada N+1 per pile pada Control Tower.
