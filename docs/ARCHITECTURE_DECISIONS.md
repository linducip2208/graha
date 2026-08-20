# Architecture Decisions

- ADR-001 Accepted: modular monolith Laravel untuk konsistensi transaksi lintas domain.
- ADR-002 Accepted: shared database dengan explicit company context dari membership aktif; bukan public SaaS.
- ADR-003 Accepted: RBAC di-assign per membership perusahaan, bukan hard-coded pada user.
- ADR-004 Accepted: audit append-only dengan hash chain; privilege DB diperketat saat deployment.
- ADR-005 Accepted: approval polymorphic, sequential foundation, idempotent, dan melarang self-approval.
- ADR-006 Accepted: standard/edition/amendment/clause ISO kelak configurable; tidak menyalin standar dan tidak mengklaim sertifikasi.
- ADR-007 Pending: BPM Level 0 wajib direkonsiliasi setelah workbook tersedia.
- ADR-008 Accepted: accounting posting terpusat, idempotent, period-controlled, memakai decimal/BCMath dan menolak jurnal tidak seimbang.
- ADR-009 Accepted: stock movement adalah ledger immutable; mutasi saldo memakai transaksi serta row lock dan stok negatif ditolak tanpa override berwenang.
- ADR-010 Accepted: pemilik corrective action tidak boleh menjadi verifier efektivitas, dan auditor tidak boleh sama dengan auditee.
- ADR-011 Accepted: revisi PO menyimpan snapshot versi lama dan men-supersede approval aktif; receipt dibatasi kuantitas PO dan invoice memakai three-way matching.
- ADR-012 Accepted: posting procurement memakai event accounting mapping configurable; invoice exception tidak boleh masuk AP.
