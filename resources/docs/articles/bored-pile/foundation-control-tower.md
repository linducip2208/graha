---
title: Foundation Control Tower
description: KPI produksi harian, Risk Radar deterministik, lookahead 3/7 hari, forecast selesai.
order: 1
role_tags: PM, Site Supervisor, Direktur
permission_tags: project.view
fixture_project_code: PRJ-2602
feature_route: /admin/projects/{project_id}/foundation-control
keywords: control tower, risk radar, kpi
---

![docs:foundation-control](foundation-control)

## Bagian layar
- **Advanced Control Tower** � klik KPI (Ready to Drill, Ready to Cast, Critical Slurry, Tremie Warning�) untuk memfilter tabel pile.
- **Lookahead 3/7 hari** � pile terencana + readiness snapshot + constraint aktif.
- **Risk Radar** � HEALTHY/WATCH/CRITICAL dari sinyal nyata: depth mismatch, overbreak, slump gagal, gap antar-truk, cage QC gagal, NCR terbuka, durasi abnormal.

## Forecast Finish
Ekstrapolasi linear produktivitas 7/14 hari � tanpa AI. Label confidence mengikuti kecukupan data.