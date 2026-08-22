<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Struk {{ $billing->number }}</title>
<style>
@page{size:80mm auto;margin:3mm}
body{font-family:'Courier New',monospace;font-size:11px;width:74mm;margin:0 auto;color:#000}
.c{text-align:center}.b{font-weight:bold}
hr{border:none;border-top:1px dashed #000;margin:4px 0}
table{width:100%;font-size:11px;border-collapse:collapse}
td{padding:1px 0;vertical-align:top}
.r{text-align:right}
.big{font-size:13px;font-weight:bold}
@media print{button{display:none}}
</style></head><body onload="window.print()">
<div class="c b">{{ strtoupper($company->name) }}</div>
@if($company->address ?? false)<div class="c" style="font-size:10px">{{ \App\Models\CompanySetting::val($company->id, 'company_address') }}</div>@endif
<div class="c" style="font-size:10px">NPWP: {{ \App\Models\CompanySetting::val($company->id, 'company_npwp') ?: '-' }}</div>
<hr>
<div>Faktur : <span class="b">{{ $billing->number }}</span></div>
<div>Tgl   : {{ $billing->billing_date->format('d/m/Y') }}</div>
<div>Jatuh Tempo : {{ $billing->due_date?->format('d/m/Y') ?? '-' }}</div>
<div>Proyek: {{ \Illuminate\Support\Str::limit($project?->name ?? '-', 34) }}</div>
<div>Klien : {{ \Illuminate\Support\Str::limit($customerName, 34) }}</div>
<hr>
<table>
<tr><td>Nilai progres (DPP)</td><td class="r">{{ number_format((float) $billing->gross_amount, 0, ',', '.') }}</td></tr>
@if((float) $billing->tax_amount > 0)<tr><td>PPN</td><td class="r">{{ number_format((float) $billing->tax_amount, 0, ',', '.') }}</td></tr>@endif
@if((float) $billing->retention_amount > 0)<tr><td>Retensi</td><td class="r">({{ number_format((float) $billing->retention_amount, 0, ',', '.') }})</td></tr>@endif
@if((float) $billing->advance_recovery > 0)<tr><td>Uang muka</td><td class="r">({{ number_format((float) $billing->advance_recovery, 0, ',', '.') }})</td></tr>@endif
<tr class="big"><td>TOTAL</td><td class="r">Rp {{ number_format((float) $billing->net_receivable, 0, ',', '.') }}</td></tr>
</table>
<hr>
<div style="font-size:10px">{{ \Illuminate\Support\Str::limit(\App\Support\Terbilang::rupiah((string) $billing->net_receivable), 90) }}</div>
<div style="font-size:10px">{{ \App\Models\CompanySetting::val($company->id, 'invoice_footer_note') }}</div>
<hr>
<div class="c" style="font-size:9px">Dicetak {{ now()->format('d/m/Y H:i') }} oleh {{ $signer }}</div>
<button onclick="window.print()" style="margin-top:8px;padding:6px 14px">🖨️ Cetak lagi</button>
</body></html>
