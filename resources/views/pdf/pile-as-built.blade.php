<!doctype html>
<html lang="id"><head><meta charset="utf-8">
<title>As-Built Pile</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#0f172a}
h1{font-size:18px;margin:0} h2{font-size:13px;margin:14px 0 4px;border-bottom:1.5px solid #0ea5e9;padding-bottom:2px}
table{width:100%;border-collapse:collapse;margin-top:3px} th,td{border:.5px solid #94a3b8;padding:3px 5px;text-align:left}
th{background:#e0f2fe;font-size:10px;text-transform:uppercase;letter-spacing:.04em}
.kv{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}.kv div{border:.5px solid #cbd5e1;border-radius:5px;padding:3px 7px;background:#f8fafc}
.flag{border-radius:5px;padding:3px 7px;margin-top:3px;font-size:10px}
.crit{background:#fee2e2;border:.5px solid #fca5a5}.warn{background:#fef9c3;border:.5px solid #fde047}
.ok{background:#dcfce7;border:.5px solid #86efac}
.head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #0f172a;padding-bottom:6px}
.muted{color:#64748b}.photos img{width:110px;height:82px;object-fit:cover;border:.5px solid #94a3b8;border-radius:4px;margin-right:4px}
.page-break{page-break-before:always}
.sign{margin-top:26px;display:flex;justify-content:space-between}.sign div{width:44%;border-top:.8px solid #0f172a;padding-top:4px;text-align:center}
footer{position:fixed;bottom:-14px;left:0;right:0;font-size:9px;color:#94a3b8;text-align:center}
</style></head><body>
@foreach($sections as $s)
@php($p = $s['pile'])
@if(!$loop->first)<div class="page-break"></div>@endif
<div class="head"><div>
<h1>As-Built Bored Pile — {{ $p->pile_number }}</h1>
<div class="muted">{{ $p->project->customer?->name }} — {{ $p->project->code }} — {{ $p->project->name }}</div>
</div><div class="muted" style="text-align:right">
@if(!empty($experience['logo_data_uri']))<img src="{{ $experience['logo_data_uri'] }}" style="height:34px;object-fit:contain;margin-bottom:4px"><br>@endif
Dicetak {{ now()->format('d/m/Y H:i') }}<br>{{ ($experience['config']['company_display_name'] ?? null) ?: config('app.name') }}</div></div>

<h2>Identitas & Volume</h2>
<div class="kv">
<div>Zona: {{ $p->zone?->name ?? '-' }}</div><div>Diameter: {{ $p->diameter_mm }} mm</div>
<div>Rencana: {{ $p->planned_depth_m }} m</div><div>Aktual: {{ $p->actual_depth_m ?? '-' }} m</div>
<div>Beton teoretis: {{ $p->theoretical_concrete_m3 ?? '-' }} m³</div><div>Beton aktual: {{ $p->actual_concrete_m3 ?? '-' }} m³</div>
<div>Overbreak: {{ $p->overbreak_percent ?? 0 }}%</div><div>Status: {{ str($p->status)->replace('_',' ') }}</div>
</div>

<h2>Anomali</h2>
@forelse($s['anomalies'] as $flag)
<div class="flag {{ $flag['severity'] === 'critical' ? 'crit' : 'warn' }}"><strong>{{ str($flag['code'])->replace('_',' ')->title() }}:</strong> {{ $flag['detail'] }}</div>
@empty
<div class="flag ok">Tidak ada anomali terdeteksi pada data yang tersedia.</div>
@endforelse

<h2>Bore Log</h2>
@foreach($s['drillings'] as $drilling)
<p style="margin:4px 0 1px"><strong>{{ $drilling->drilling_started_at?->format('d/m/Y H:i') ?? '-' }}</strong> — status {{ strtoupper($drilling->status) }}, perekam {{ $drilling->recorder?->name }}{{ $drilling->verifier ? ', verifikator '.$drilling->verifier->name : '' }}</p>
<table><thead><tr><th style="width:16%">Dari (m)</th><th style="width:16%">Ke (m)</th><th>Deskripsi Tanah</th></tr></thead><tbody>
@foreach($drilling->layers as $layer)<tr><td>{{ $layer->depth_from_m }}</td><td>{{ $layer->depth_to_m }}</td><td>{{ $layer->soil_description }}</td></tr>@endforeach
</tbody></table>
@endforeach
@if($s['drillings']->isEmpty())<p class="muted">Belum ada drilling record.</p>@endif

<h2>Delivery Beton, Slump & Sampel</h2>
<table><thead><tr><th>DO</th><th>Truk</th><th>Plant</th><th>Tiba</th><th>Pesan</th><th>Diterima</th><th>Ditolak</th><th>Slump</th><th>Sampel</th></tr></thead><tbody>
@foreach($s['deliveries'] as $d)<tr><td>{{ $d->delivery_order_number }}</td><td>{{ $d->truck_number }}</td><td>{{ $d->batching_plant ?? '-' }}</td><td>{{ optional($d->arrived_at)->format('d/m/y H:i') ?? '-' }}</td><td>{{ $d->ordered_volume_m3 }}</td><td>{{ $d->accepted_volume_m3 }}</td><td>{{ $d->rejected_volume_m3 }}</td><td>{{ $d->slump_cm ?? '-' }}</td><td>{{ $d->sample_number ?? '-' }}</td></tr>@endforeach
@if($s['deliveries']->isEmpty())<tr><td colspan="9">Belum ada delivery.</td></tr>@endif
</tbody></table>

<h2>Cage Tulangan</h2>
<table><thead><tr><th>Nomor</th><th>Heat No</th><th>Mill Cert</th><th>Teoretis (kg)</th><th>Aktual (kg)</th><th>QC</th><th>Terkirim</th></tr></thead><tbody>
@foreach($s['cages'] as $cage)<tr><td>{{ $cage->number }}</td><td>{{ $cage->heat_number ?? '-' }}</td><td>{{ $cage->mill_cert_number ?? '-' }}</td><td>{{ $cage->theoretical_weight_kg ?? '-' }}</td><td>{{ $cage->actual_weight_kg ?? '-' }}</td><td>{{ strtoupper($cage->qc_status) }}</td><td>{{ optional($cage->delivered_at)->format('d/m/y') }}</td></tr>@endforeach
@if($s['cages']->isEmpty())<tr><td colspan="7">Belum ada cage.</td></tr>@endif
</tbody></table>

<h2>Casing</h2>
<table><thead><tr><th>Kode</th><th>Kepemilikan</th><th>Siklus</th><th>Kondisi</th></tr></thead><tbody>
@foreach($s['casings'] as $cs)<tr><td>{{ $cs->code }}</td><td>{{ $cs->ownership === 'owned' ? 'Milik' : 'Sewa' }}</td><td>{{ $cs->usage_cycle_count }}×</td><td>{{ $cs->condition ?? '-' }}</td></tr>@endforeach
@if($s['casings']->isEmpty())<tr><td colspan="4">Tidak ada casing terpasang saat ini.</td></tr>@endif
</tbody></table>

<h2>Pengujian</h2>
<table><thead><tr><th>Jenis</th><th>Nomor</th><th>Jadwal</th><th>Hasil</th><th>Laporan</th><th>Setuju Konsultan</th></tr></thead><tbody>
@foreach($s['tests'] as $t)<tr><td>{{ $t->test_type }}</td><td>{{ $t->number }}</td><td>{{ optional($t->scheduled_date)->format('d/m/y') }}</td><td>{{ strtoupper($t->result_status) }}</td><td>{{ $t->report_number ?? '-' }}</td><td>{{ $t->consultant_approved_at ? optional($t->consultant_approved_at)->format('d/m/y') : '-' }}</td></tr>@endforeach
@if($s['tests']->isEmpty())<tr><td colspan="6">Belum ada pengujian.</td></tr>@endif
</tbody></table>

<h2>Foto Evidence</h2>
<div class="photos">
@forelse($s['evidences'] as $ev)
<img src="{{ $ev->src ?? '' }}" alt="{{ $ev->original_name }}">
@empty<span class="muted">Belum ada evidence.</span>@endforelse
</div>

<div class="sign"><div>Dibuat oleh,<br><br><br>{{ $p->project->pm_name ?? 'Site Engineer' }}</div><div>Disetujui oleh,<br><br><br>{{ $p->project->client_name ?? 'Konsultan/MK' }}</div></div>
<footer>Dokumen dihasilkan otomatis dari {{ ($experience['config']['system_name'] ?? null) ?: config('app.name') }} - data per {{ now()->format('d/m/Y') }} | ID pile #{{ $p->id }}@if(!empty($experience['config']['footer_text'])) - {{ $experience['config']['footer_text'] }}@endif</footer>
@endforeach
</body></html>
