# Foundation Control Tower

Status per 2026-08-26. Route: `GET /admin/projects/{project}/foundation-control`
(ADR-055; diperluas ADR-076/077).

## Struktur halaman

1. **Status distribution cards** — 12 status pile + accepted + total.
2. **KPI Produksi & Kualitas** — mulai/selesai hari ini, meter, beton, rig
   aktif, overbreak, test pass rate, cycle time, NCR terbuka.
3. **Advanced Control Tower** (ADR-076) — 12 KPI klik-through:

| KPI | Sumber | Filter tabel |
|---|---|---|
| Ready to Drill | snapshot `PileReadinessCheck` terakhir | `?filter=ready_drill` |
| Ready to Cast | idem kind=cast | `?filter=ready_cast` |
| Pile Accepted / Belum Diterima | `pile_acceptances.status=accepted` vs completed tanpa acceptance | `accepted` / `not_accepted` |
| Critical Slurry | slurry_tests rejected/pending before_casting | `slurry` |
| Tremie Warning | tremie_logs flag warning/out_of_range | `tremie` |
| Concrete Interruption | risk reason concrete_interruption (gap antar-truk) | `interruption` |
| Geometry Warning | geometry verticality > 2% | `geometry` |
| Cost Total & Rework Cost | `PileCostService::projectSummary` | — |
| Produktivitas 7 hari | meters/day + piles selesai | — |
| Forecast Finish | `FoundationForecastService` + confidence | — |

Klik KPI → tabel pile ter-filter (indikator filter aktif + link hapus).

4. **Lookahead 3/7 Hari** — dua window berdampingan: pile, zona, rig,
   badge readiness, jumlah blocker/constraint.
5. **Risk Radar** — HEALTHY/WATCH/CRITICAL deterministik (`PileRiskService`,
   ADR-054) dengan daftar alasan per pile.
6. **Plan view / grid fallback** — posisi dinormalisasi lat/lng aktual.
7. **Daily production board**.

## Performance

- Risk dievaluasi sekali per render dengan eager loading zone/acceptance
  (limit 200 pile).
- Readiness latest diambil satu query + unique per (pile,kind).
- Cost summary memakai agregasi batch per kategori; dashboard storage murni
  group-by DB.
