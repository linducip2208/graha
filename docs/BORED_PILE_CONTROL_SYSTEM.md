# Bored Pile Control System — Advanced Engineering

Status per 2026-08-26 (ADR-073..076). Workspace: Proyek → Project Detail →
Foundation / Bored Pile. Tidak ada sidebar baru; semua fitur hidup di dalam
workspace existing.

## Ready-to-Drill / Ready-to-Cast — DONE

`App\Services\PileReadinessService` — engine deterministik tanpa AI.

- `drillReadiness(pile)` → `READY | NOT_READY` + blockers + checklist 12 item
  (drawing disetujui, setting-out, koordinat, platform, rig, crew, casing,
  slurry, JSA, ITP pre-drill, material constraint, hold).
- `castReadiness(pile)` → `READY_TO_CAST | BLOCKED` + checklist 12+ item.
- Snapshot tersimpan immutable di `pile_readiness_checks`; kartu passport
  menampilkan status + "terakhir dicek" + detail checklist per item.
- **Fitur opsional hanya aktif bila company menghidupkannya** (settings):
  evidence rules, ITP hold point (`require_itp_hold_points_passed`),
  cage QC (`require_cage_passed`), cleaning gate
  (`require_cleaning_inspection`), JSA (`require_jsa_active`), slurry policy
  (`slurry_policy_enabled`), tremie log (`tremie_log_enabled`). Item non-aktif
  = state `skip`, bukan blocker.
- Engine HANYA melapor — transisi status pile tetap lewat
  `BoredPileService::transition()`; disposisi engineering tetap manusia.

## Bottom Cleaning Inspection — DONE

Tabel `pile_bottom_cleaning_inspections` (method, sediment_thickness_mm,
inspected_at, inspector/witness, status pending|accepted|rejected).
Gate inspeksi wajib hanya bila `require_cleaning_inspection=1`; default OFF
(data tetap terekam). Rejection tidak otomatis me-rollback pile.

## Slurry Control — DONE

Tabel `slurry_tests` (phase before_drilling/during_drilling/before_cage/
before_casting; type bentonite/polymer/water/other; density/viscosity/pH/
sand/temperature).

- Limit acceptance dari settings company: `slurry_density_min/max`,
  `slurry_viscosity_min/max`, `slurry_ph_min/max`, `slurry_sand_content_max`.
- **Tanpa standar nilai hardcoded** — default semua kosong/OFF; tanpa kebijakan
  = record only, bukan gate.
- Keputusan accept/reject oleh QA (permission `qms.verify`);
  `SlurryControlService::violations()` hanya melaporkan pelanggaran limit.

## Tremie Log — DONE

Tabel `pile_tremie_logs`: sequence per pile, panjang total, kedalaman ujung,
level beton, embedment. Embedment = panjang − ujung (deterministik bila kosong).
Flag `normal|warning|out_of_range` dari `tremie_min_embedment_m` /
`tremie_max_embedment_m` — **indikator saja, tidak pernah auto-reject pile**.

## Concrete Truck Timeline — DONE

`concrete_deliveries` diperluas kolom `sequence` (auto per pile) dan model
memiliki accessor: `waitingMinutes()` (batch→tiba), `dischargeMinutes()`
(mulai→selesai pour), `gapFromPreviousMinutes()` (jeda antar-truk).
Risk engine memakai gap yang sama untuk deteksi interupsi beton
(`concrete_max_gap_minutes`).

## Pour Curve — DONE

Tabel `pile_concrete_pour_intervals` (depth_or_level_m, incremental_volume,
cumulative otomatis). `PourCurveService::curve()` menghitung teoretis kumulatif
(πr²×depth) vs aktual kumulatif + variance % + flag overconsumption terhadap
toleransi overbreak proyek. Grafik SVG di pile passport digambar dari interval
tercatat saja — **tanpa interpolasi fiktif**; kosong = pesan "belum ada data".

## Hole Geometry / Caliper — DONE

Tabel `pile_geometry_readings` (depth, diameter ukur, dev_x/y, verticality,
source manual|survey|caliper_import|telemetry). Import CSV aman
(depth,diameter,dev_x,dev_y,verticality), header dilewati, baris invalid
dilaporkan. Hasil turunan tidak pernah dilabeli certified survey kecuali sumber
`survey`.

## Survey Control — DONE

Kolom additive bored_piles: design/actual easting-northing, top elevation,
cutoff level. `PileSurveyService::deviation()` menghitung deviasi horizontal
(hypot), elevasi, cutoff vs toleransi `survey_tolerance_m` → status
PASS/WARNING/OUT_OF_TOLERANCE (indikator; keputusan tetap engineer).

## Lookahead & Forecast — DONE

- `FoundationLookaheadService::build(project, 3|7)`: pile terencana +
  snapshot readiness + constraint aktif. Rencana manual via tanggal
  `planned_date` (endpoint `piles.planned-date.update`).
- `FoundationForecastService::forecast(project)`: ekstrapolasi produktivitas
  7/14 hari (weighted) dengan label confidence high/medium/low berdasarkan
  kecukupan data; riwayat kosong → `insufficient_history`. Tanpa AI.
