# Digital Pile Passport

Status per 2026-08-26 (fondasi ADR-049; diperluas ADR-073..075).

Route: `GET /admin/bored-piles/{pile}/passport` + entri publik QR
`/piles/{public_uuid}` (tetap ber-otorisasi per company).

## Ringkasan header (P24)

Identitas & desain: pile no, UUID QR, diameter, depth rencana/aktual, elevasi
platform/toe/cut-off, koordinat (x,y + easting/northing design vs actual),
grade, metode drilling, rig. Stat-card: beton teoretis/aktual, overbreak,
cage/casing.

## Ready to Drill / Ready to Cast — BARU

Dua kartu hijau/merah dengan badge status, "terakhir dicek" + oleh siapa,
daftar blocker (maks 6 + sisa), detail checklist lengkap (pass/fail/skip),
tombol cek ulang. Attestasi platform & booking beton satu-klik (teraudit).
Detail: `BORED_PILE_CONTROL_SYSTEM.md`.

## Bottom Cleaning Inspection — BARU

Daftar inspeksi cleaning + tombol Accept/Reject untuk QA
(`qms.verify`) + form record baru dari passport.

## Pour Curve / Geometry / Survey — BARU

- Kurva SVG teoretis (dash biru) vs aktual kumulatif (hijau) + tabel variance
  % per titik; flag merah bila melewati toleransi overbreak.
- Tabel geometry readings + import CSV (sumber manual/survey/caliper/telemetry).
- Kartu Survey Deviation: horizontal/elevasi/cutoff + status
  PASS/WARNING/OUT_OF_TOLERANCE vs toleransi company.

## Bagian existing yang dipertahankan

Timeline foto evidence per fase (object storage privat, thumb/preview),
dokumen as-built/dossier/handover + SHA-256, acceptance lifecycle
(request→QA→engineer→decide), soil/bore log, QR, linimasa aktivitas.

## Mobile

Layout passport memakai grid responsif existing (stat-card wrap, tabel
overflow-x). Tidak ada elemen fixed-width; touch target form ≥44px pada
tombol aksi utama.
