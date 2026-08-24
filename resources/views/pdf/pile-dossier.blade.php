@php($brand = $experience ?? null)
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Acceptance Dossier — {{ $d['pile']->pile_number }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
    .cover { text-align: center; padding: 48px 0 24px; border-bottom: 3px solid {{ $brand['tokens']['--brand-primary'] ?? '#0f172a' }}; margin-bottom: 18px; }
    .cover h1 { font-size: 22px; margin: 8px 0 2px; letter-spacing: .5px; }
    h2 { font-size: 12.5px; background: #f1f5f9; padding: 5px 8px; margin: 16px 0 6px; border-left: 4px solid {{ $brand['tokens']['--brand-primary'] ?? '#0f172a' }}; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; vertical-align: top; }
    th { background: #f8fafc; font-size: 9px; text-transform: uppercase; }
    .kv td:first-child { width: 32%; background: #f8fafc; font-weight: bold; }
    .photos { width: 100%; }
    .photos td { border: none; text-align: center; font-size: 8px; padding: 4px; }
    .photos img { width: 100%; height: 78px; object-fit: cover; border: 1px solid #cbd5e1; border-radius: 4px; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #e2e8f0; font-weight: bold; font-size: 9px; }
    .sign { margin-top: 26px; width: 100%; }
    .sign td { border: none; text-align: center; padding-top: 34px; }
    .sign .line { border-top: 1px solid #475569; margin: 0 24px; padding-top: 4px; font-size: 9px; }
    footer { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 8px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 4px; }
    .page-break { page-break-after: always; }
</style>
</head>
<body>

<div class="cover">
    @if(!empty($brand['logo_data_uri']))
        <img src="{{ $brand['logo_data_uri'] }}" style="height:52px;object-fit:contain">
    @endif
    <h1>ACCEPTANCE DOSSIER PILE</h1>
    <p style="font-size:14px;font-weight:bold">{{ $d['pile']->pile_number }} · {{ $d['pile']->project->code }}</p>
    <p>{{ $d['pile']->project->customer?->name }} — {{ $d['pile']->project->name }}</p>
    <p class="badge">Status Pile: {{ strtoupper(str_replace('_',' ',$d['pile']->status)) }}</p>
    @if($d['acceptance'])
        <p class="badge" style="background:#dcfce7">Acceptance: {{ strtoupper($d['acceptance']->status) }}</p>
    @endif
    <p style="font-size:8.5px;color:#64748b;margin-top:6px">Diterbitkan {{ now()->translatedFormat('d F Y') }} · SHA-256 terekam pada registry dokumen</p>
</div>

<h2>1. Identitas & Data Desain</h2>
<table class="kv">
    <tr><td>Nomor Pile / UUID Publik</td><td>{{ $d['pile']->pile_number }} · <span style="font-family:monospace">{{ $d['pile']->public_uuid }}</span></td></tr>
    <tr><td>Zona / Grid</td><td>{{ $d['pile']->zone?->name ?? '-' }} / {{ $d['pile']->grid_reference ?? '-' }}</td></tr>
    <tr><td>Koordinat Rencana (X, Y)</td><td>{{ $d['pile']->coordinate_x ?? '-' }}, {{ $d['pile']->coordinate_y ?? '-' }}</td></tr>
    <tr><td>Diameter</td><td>Ø{{ $d['pile']->diameter_mm }} mm</td></tr>
    <tr><td>Kedalaman Rencana → Aktual</td><td>{{ $d['pile']->planned_depth_m }} m → {{ $d['pile']->actual_depth_m ?? '-' }} m</td></tr>
    <tr><td>Elevasi Platform / Toe (design→actual) / Cut-off</td><td>{{ $d['pile']->platform_elevation ?? '-' }} m / {{ $d['pile']->design_toe_level ?? '-' }} → {{ $d['pile']->actual_toe_level ?? '-' }} m / {{ $d['pile']->cut_off_level ?? '-' }} m</td></tr>
    <tr><td>Grade Beton / Metode Drilling</td><td>{{ $d['pile']->concrete_grade ?? '-' }} / {{ $d['pile']->drilling_method ?? '-' }}</td></tr>
    <tr><td>Beton Teoretis → Aktual (Overbreak)</td><td>{{ $d['pile']->theoretical_concrete_m3 ?? '-' }} m³ → {{ $d['pile']->actual_concrete_m3 ?? '-' }} m³ ({{ $d['pile']->overbreak_percent ?? 0 }}%)</td></tr>
</table>

<h2>2. Bore Log & Lapisan Tanah</h2>
@foreach($d['drillings'] as $drilling)
    <p style="margin:2px 0"><strong>Mulai {{ optional($drilling->drilling_started_at)->format('d/m/Y H:i') }}</strong> — status {{ strtoupper($drilling->status) }} · groundwater {{ $drilling->groundwater_level_m ?? '-' }} m · pembersihan akhir {{ $drilling->final_cleaning_minutes ?? '-' }} menit, sediment {{ $drilling->sediment_depth_mm ?? '-' }} mm</p>
    <table>
        <thead><tr><th style="width:15%">Dari (m)</th><th style="width:15%">Ke (m)</th><th>Deskripsi Tanah</th></tr></thead>
        <tbody>@foreach($drilling->layers as $layer)<tr><td>{{ $layer->depth_from_m }}</td><td>{{ $layer->depth_to_m }}</td><td>{{ $layer->soil_description }}</td></tr>@endforeach</tbody>
    </table>
@endforeach
@if($d['drillings']->isEmpty())<p>Belum ada data bore log.</p>@endif

<h2>3. Cage Tulangan & Casing</h2>
<table>
    <thead><tr><th>Cage</th><th>No. Heat / Mill Cert</th><th>Ø (mm)</th><th>Berat Teoritis/Aktual (kg)</th><th>QC</th><th>Dikirim</th></tr></thead>
    <tbody>
    @forelse($d['cages'] as $cage)
        <tr><td>{{ $cage->number }}</td><td>{{ $cage->heat_number ?? '-' }} / {{ $cage->mill_cert_number ?? '-' }}</td><td>{{ $cage->diameter_mm }}</td><td>{{ $cage->theoretical_weight_kg ?? '-' }} / {{ $cage->actual_weight_kg ?? '-' }}</td><td>{{ strtoupper($cage->qc_status) }}</td><td>{{ optional($cage->delivered_at)->format('d/m/y') }}</td></tr>
    @empty
        <tr><td colspan="6">Belum ada cage terkirim.</td></tr>
    @endforelse
    </tbody>
</table>
<table>
    <thead><tr><th>Casing</th><th>Kepemilikan</th><th>Siklus</th><th>Kondisi</th></tr></thead>
    <tbody>
    @forelse($d['casings'] as $cs)
        <tr><td>{{ $cs->code }}</td><td>{{ $cs->ownership === 'owned' ? 'Milik' : 'Sewa' }}</td><td>{{ $cs->usage_cycle_count }}×</td><td>{{ $cs->condition ?? '-' }}</td></tr>
    @empty
        <tr><td colspan="4">Belum ada casing terpasang.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>4. Delivery Beton & Slump</h2>
<table>
    <thead><tr><th>DO</th><th>Truk</th><th>Batch/Tiba/Mulai/Selesai</th><th>Terdima (m³)</th><th>Slump (cm)</th><th>Sampel</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($d['deliveries'] as $dl)
        <tr><td>{{ $dl->delivery_order_number }}</td><td>{{ $dl->truck_number }}</td><td>{{ optional($dl->batch_time)->format('H:i') ?? '-' }} / {{ optional($dl->arrived_at)->format('H:i') ?? '-' }} / {{ optional($dl->pour_started)->format('H:i') ?? '-' }} / {{ optional($dl->pour_finished)->format('H:i') ?? '-' }}</td><td>{{ $dl->accepted_volume_m3 }}</td><td>{{ $dl->slump_cm ?? '-' }}</td><td>{{ $dl->sample_number ?? '-' }}</td><td>{{ strtoupper($dl->status) }}</td></tr>
    @empty
        <tr><td colspan="7">Belum ada delivery.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>5. Hasil Pengujian</h2>
<table>
    <thead><tr><th>Jenis</th><th>Nomor</th><th>Agensi</th><th>Jadwal / Uji</th><th>Hasil</th><th>Laporan</th><th>Persetujuan Konsultan</th></tr></thead>
    <tbody>
    @forelse($d['tests'] as $t)
        <tr><td>{{ $t->test_type }}</td><td>{{ $t->number }}</td><td>{{ $t->provider_name ?? '-' }}</td><td>{{ optional($t->scheduled_date)->format('d/m/y') ?? '-' }} / {{ optional($t->tested_at)->format('d/m/y') ?? '-' }}</td><td>{{ strtoupper($t->result_status) }}</td><td>{{ $t->report_number ?? '-' }}</td><td>{{ $t->consultant_approved_at ? 'Disetujui' : '-' }}</td></tr>
    @empty
        <tr><td colspan="7">Belum ada pengujian.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>6. NCR / Ketidaksesuaian Terkait</h2>
@forelse($d['nonconformities'] as $ncr)
    <p style="margin:2px 0"><strong>{{ $ncr->number }}</strong> [{{ strtoupper($ncr->severity) }}] — {{ $ncr->description }} <em>({{ $ncr->status }})</em></p>
@empty
    <p>Tidak ada NCR yang tertaut ke pile ini.</p>
@endforelse

<div class="page-break"></div>
<h2>7. Bukti Foto</h2>
<table class="photos"><tr>
    @foreach($d['evidences'] as $i => $ev)
    @if($i > 0 && $i % 3 === 0)</tr><tr>@endif
    <td style="width:33%">
        @if(isset($ev->src))<img src="{{ $ev->src }}">@else<div style="height:78px;border:1px dashed #cbd5e1;border-radius:4px;line-height:78px;color:#94a3b8">n/a</div>@endif
        {{ \App\Models\FieldEvidence::LABELS[$ev->evidence_type] ?? $ev->evidence_type }}
    </td>
    @endforeach
    @if($d['evidences']->isEmpty())<td colspan="3" style="color:#94a3b8">Belum ada foto evidence.</td>@endif
</tr></table>

<h2>8. Ringkasan Acceptance & Tanda Tangan</h2>
@if($d['acceptance'])
<table class="kv">
    <tr><td>Status</td><td>{{ strtoupper($d['acceptance']->status) }}</td></tr>
    <tr><td>Gate Checks</td><td>@foreach($d['acceptance']->gate_checks ?? [] as $key => $check){{ str($key)->replace('_',' ')->title() }}: {{ $check ? 'OK' : 'BELUM' }}&nbsp;&nbsp;@endforeach</td></tr>
    @if($d['acceptance']->conditions)<tr><td>Syarat Kondisional</td><td>{{ $d['acceptance']->conditions }}</td></tr>@endif
    @if($d['acceptance']->rejection_reason)<tr><td>Alasan Penolakan</td><td>{{ $d['acceptance']->rejection_reason }}</td></tr>@endif
    <tr><td>Diminta / Diputuskan</td><td>{{ optional($d['acceptance']->requested_at)->format('d/m/Y H:i') ?? '-' }} / {{ optional($d['acceptance']->decided_at)->format('d/m/Y H:i') ?? '-' }}</td></tr>
</table>
@else
<p>Pile belum melalui proses acceptance formal.</p>
@endif
<table class="sign"><tr>
    <td style="width:50%"><div class="line">Dibuat oleh — QC Site</div></td>
    <td style="width:50%"><div class="line">Disetujui oleh — Engineer/Consultant</div></td>
</tr></table>

<footer>{{ $brand['config']['footer_text'] ?? '' }} · {{ $brand['config']['company_display_name'] ?? config('app.name') }} — dihasilkan otomatis dari sistem, dokumen asli tersimpan berversi di object storage</footer>
</body>
</html>
