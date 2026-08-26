---
title: Digital Pile Passport
description: Identitas digital satu pile � desain, evidence foto, uji, acceptance, QR publik.
order: 2
role_tags: Site Supervisor, QMS, PM
permission_tags: project.view
fixture_pile_number: BP-A01
feature_route: /admin/projects?tab=overview
keywords: passport, qr, pile
---

![docs:pile-passport](pile-passport)

## Bagian layar
| Bagian | Isi |
|---|---|
| Identitas & Desain | diameter, depth rencana vs aktual, koordinat survey |
| Ready to Drill / Cast | checklist engine + snapshot "terakhir dicek" |
| Pour Curve | kurva teoretis vs aktual + variance % |
| Timeline Foto | evidence per fase konstruksi |
| Acceptance | lifecycle pending ? QA ? engineer ? decided |
| QR | scan di lapangan menuju passport ini |

## Workflow acceptance
```workflow
Completed -> Request -> QA Review -> Engineer Review -> Accepted
```

## Kesalahan umum
- Gate merah saat ajukan acceptance: pastikan as-built tersimpan, survey terisi, dan tidak ada uji pending.