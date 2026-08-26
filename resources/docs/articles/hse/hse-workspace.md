---
title: HSE Workspace
description: JSA, work permit, insiden, observasi keselamatan, PPE, dan KPI keselamatan FR/SR/TRIR.
order: 1
role_tags: HSE
permission_tags: hse.view
feature_route: /admin/hse
keywords: jsa, insiden, k3, safety
---

![docs:hse](hse)

## Ringkasan
Kelola seluruh aspek keselamatan kerja: analisa bahaya sebelum pekerjaan (JSA), izin kerja, pencatatan insiden, observasi unsafe act/condition, penerbitan APD, dan exposure manhours untuk KPI.

## Bagian layar
| Bagian | Fungsi |
|---|---|
| JSA | Identifikasi bahaya & kontrol per aktivitas, status aktif per periode |
| Incidents | Near-miss/accident dengan investigasi dan tindakan |
| Observations | Unsafe condition/act beserta resolusi |
| PPE | Penerbitan & pengembalian APD per personel |
| Exposure | Manhours bulanan untuk FR/SR/TRIR |

## Workflow insiden
```workflow
Incident -> Immediate Action -> Investigation -> Actions -> Verified -> Closed
```

## FAQ
**FR/SR/TRIR dari mana?** Dihitung otomatis dari insiden tercatat dibagi exposure manhours periode berjalan.
