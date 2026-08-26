---
title: Object Storage, Demo Data & Backup
description: Disk evidence bisnis vs screenshot docs lokal, seeder demo aman, dan cadangan.
order: 4
role_tags: Admin
feature_route: /admin/settings
keywords: storage, backup, demo
---

- Business evidence ? S3/R2 (`EVIDENCE_DISK`).
- Screenshot dokumentasi ? disk `docs` lokal (`storage/app/docs`), regenerabel via `php artisan docs:capture`.
- Demo data hanya ter-seed di local/demo (`SEED_DEMO_DATA=true`) — production tidak pernah otomatis.
- Audit trail hash-chain tidak memerlukan backup khusus selain dump database rutin.