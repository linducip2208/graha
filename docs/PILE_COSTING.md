# Pile Costing & Productivity Intelligence

Status per 2026-08-26 (ADR-076/077).

## Per-Pile Cost — DONE

`App\Services\PileCostService::pileCost(pile)`. Sumber kebenaran = transaksi
nyata; komponen tanpa data dilaporkan `null`/untraced, tidak dikarang.

| Kategori | Sumber transaksi | Status |
|---|---|---|
| Concrete | `concrete_deliveries` (approved) × harga satuan rata-rata item PO tertaut (`purchase_order_items`) | DONE |
| Steel cage | `stock_movements` reference `cage-material:{cage_id}` (unit_cost × qty) | DONE |
| Material issue langsung | `stock_movements.bored_pile_id` (issue − return_in × unit_cost) | DONE |
| Equipment rig | jam drilling nyata per pile × cost/hour `EquipmentCostService` (BBM + maintenance + depresiasi aktual) | DONE |
| Fuel | proporsi komponen BBM dari total biaya equipment periode sama | DONE |
| Testing | kolom opsional `pile_tests.cost_amount` (invoice aktual, diinput user) | DONE |
| Budget if available | WBS budget bila pile tertaut; selain itu null | DONE |

### Rework Attribution — DONE

- **Extra concrete**: volume accepted di atas teoretis kumulatif pada pile
  overbreak → dinilai dengan harga PO yang sama → rework.
- **Redrilling**: drilling record kedua dst. pada pile yang sama → jam
  tambahan × rate rig → rework.
- Output: `normal_cost`, `rework_cost`, `rework_breakdown{extra_concrete,
  redrill_hours, redrill_equipment}` — manajemen melihat berapa biaya kegagalan
  mutu.

### Drill-down

`sources[]` mendokumentasikan jejak setiap kategori: cost line → transaksi
sumber → jurnal / stock movement / equipment cost summary.

### Anti N+1

`projectSummary(project)` mengagregasi total & rework untuk Control Tower;
per-pile loop memakai query terkumpul per kategori.

## Foundation Productivity — DONE

`App\Services\FoundationProductivityService::projectMetrics(project, from, to)`:

- meter bor/hari (lapisan terdalam per hari drilling), pile selesai/hari,
  avg cycle time (aktivitas pertama→terlesai), durasi fase drilling/casting.
- Agregasi by rig (`by_rig`: jam + pile per rig) dan by diameter (`by_diameter`).
- Tidak ada KPI hardcoded — semua dihitung dari data; driver-portable
  (durasi dihitung PHP, bukan fungsi DB spesifik MySQL).

## Rig Performance — DONE

`rigPerformance(rig, project)` 30 hari: productive hours (jam drilling nyata),
idle, utilization %, meters, meters/hour, fuel liter/jam & liter/meter
(dari `fuel_usages` nyata), cost/hour dari EquipmentCostService.

## Delay Reason Registry — DONE

`App\Services\DelayReason` — registry terpusat 15 alasan (rig_breakdown,
waiting_concrete, waiting_cage, waiting_inspector, client_hold, weather,
access, soil_condition, slurry, drawing, material, manpower, permit, safety,
other). Kolom additive `equipment_downtime_logs.delay_reason` menunjuk ke
registry ini; sistem downtime existing tidak diduplikasi.

## Completion Forecast — DONE

Lihat `FOUNDATION_CONTROL_TOWER.md` — KPI "Forecast Finish" memakai
`FoundationForecastService` (deterministik, confidence label berbasis
kecukupan data, tanpa AI).
