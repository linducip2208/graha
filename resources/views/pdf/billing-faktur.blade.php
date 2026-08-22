<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Faktur {{ $billing->number }}</title>
<style>
body{font-family:Helvetica,Arial,sans-serif;font-size:12px;color:#0f172a;margin:32px}
.kop{display:flex;justify-content:space-between;border-bottom:3px double #0f172a;padding-bottom:12px}
.kop .brand{font-size:20px;font-weight:bold}
.kop .meta{text-align:right;font-size:11px;color:#475569}
.title{margin:22px 0 14px;text-align:center}
.title h1{font-size:16px;margin:0;letter-spacing:2px;text-transform:uppercase}
table.detail{width:100%;border-collapse:collapse;margin-top:6px}
table.detail td,table.detail th{padding:7px 9px;border:1px solid #cbd5e1}
table.detail th{background:#f1f5f9;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.5px}
.num{text-align:right;font-family:'Courier New',monospace}
.terbilang{margin-top:12px;padding:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;font-style:italic}
.ttd{margin-top:42px;display:flex;justify-content:space-between}
.ttd .box{width:44%;text-align:center}
.sign{margin-top:64px;border-top:1px solid #0f172a;padding-top:5px;font-weight:bold;display:inline-block;width:220px}
.footer{margin-top:36px;border-top:1px solid #e2e8f0;padding-top:8px;font-size:9px;color:#94a3b8;text-align:center}
</style></head><body>
<div class="kop">
<div>
@php($meta = collect([\App\Models\CompanySetting::val($company->id, 'company_address'), 'Telp: '.\App\Models\CompanySetting::val($company->id, 'company_phone'), \App\Models\CompanySetting::val($company->id, 'company_email'), 'NPWP: '.\App\Models\CompanySetting::val($company->id, 'company_npwp')])->filter(fn ($v) => $v !== '' && $v !== 'Telp: ' && $v !== 'NPWP: '))
<div class="brand">{{ $company->name }}</div>
@foreach($meta as $line)<div style="font-size:10px;color:#475569">{{ $line }}</div>@endforeach
@if($meta->isEmpty())<div style="font-size:11px;color:#475569">Jasa Konstruksi Pondasi — Bored Pile & Pile Cap</div>@endif
</div>
<div class="meta">No. Faktur: <strong>{{ $billing->number }}</strong><br>Tanggal: {{ $billing->billing_date->format('d/m/Y') }}<br>Jatuh Tempo: {{ $billing->due_date?->format('d/m/Y') ?? '-' }}</div>
</div>
<div class="title"><h1>Faktur Tagihan Progres</h1><p style="margin:4px 0 0;color:#475569">Proyek: {{ $project->name }} ({{ $project->code }}) · Pelanggan: {{ $customerName }}</p></div>
<table class="detail">
<tr><th style="width:55%">Uraian</th><th class="num" style="width:45%">Nilai (Rp)</th></tr>
<tr><td>Nilai pekerjaan periode ini (DPP) — progres {{ number_format((float) $billing->progress_percent, 2, ',', '.') }}%</td><td class="num">{{ number_format((float) $billing->gross_amount, 2, ',', '.') }}</td></tr>
@if((float) $billing->tax_amount > 0)<tr><td>PPN {{ $billing->taxRate?->name ?: '' }}</td><td class="num">{{ number_format((float) $billing->tax_amount, 2, ',', '.') }}</td></tr>@endif
@if((float) $billing->retention_amount > 0)<tr><td>Dikurangi retensi {{ number_format((float) $billing->retention_percent, 2, ',', '.') }}%</td><td class="num">({{ number_format((float) $billing->retention_amount, 2, ',', '.') }})</td></tr>@endif
@if((float) $billing->advance_recovery > 0)<tr><td>Pemulihan uang muka</td><td class="num">({{ number_format((float) $billing->advance_recovery, 2, ',', '.') }})</td></tr>@endif
<tr><td style="font-weight:bold;background:#f8fafc">TOTAL TAGIHAN</td><td class="num" style="font-weight:bold;background:#f8fafc">{{ number_format((float) $billing->net_receivable, 2, ',', '.') }}</td></tr>
</table>
<div class="terbilang"><strong>Terbilang:</strong> {{ \App\Support\Terbilang::rupiah((string) $billing->net_receivable) }}</div>
<p style="font-size:10px;color:#64748b">Pembayaran mohon ditransfer ke rekening yang tercantum pada penawaran. Mohon lampirkan bukti potong PPh (bila dikenakan pemotongan) bersama dokumen pembayaran.</p>
<div class="ttd">
<div class="box"><p>Hormat kami,</p><div class="sign">{{ $signer }}</div></div>
<div class="box"><p>Diterima oleh,</p><div class="sign">&nbsp;</div></div>
</div>
<div class="footer">Dokumen ini dihasilkan otomatis oleh {{ config('app.name') }} · Status jurnal: {{ strtoupper($billing->status) }}</div>
</body></html>
