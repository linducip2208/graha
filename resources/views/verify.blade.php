<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verifikasi Tanda Tangan Digital</title>
<style>
body{font-family:ui-sans-serif,system-ui,sans-serif;background:#f8fafc;color:#0f172a;margin:0;padding:24px}
.wrap{max-width:640px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:28px}
.badge{display:inline-block;padding:6px 14px;border-radius:999px;font-weight:800;font-size:13px;letter-spacing:.04em}
.ok{background:#dcfce7;color:#166534}.bad{background:#fee2e2;color:#991b1b}
dl{margin:18px 0 0;display:grid;grid-template-columns:150px 1fr;gap:8px 12px;font-size:14px}
dt{color:#64748b;font-weight:600}dd{margin:0;word-break:break-all;font-variant-numeric:tabular-nums}
.hash{font-family:ui-monospace,monospace;font-size:12px}
ul.checks{list-style:none;padding:0;margin:14px 0 0;font-size:13px}
li{padding:4px 0}li.ya{color:#166534}li.tidak{color:#b91c1c}
.qr{float:right;width:132px;height:132px;margin:0 0 10px 14px;border:1px solid #e2e8f0;border-radius:12px;padding:6px;background:#fff}
footer{margin-top:22px;padding-top:14px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:12px}
</style>
</head>
<body>
<div class="wrap">
<img alt="QR verifikasi" class="qr" src="data:image/svg+xml;base64,{{ base64_encode($qrSvg) }}">
@php($allOk = $result['valid'])
<span class="badge {{ $allOk ? 'ok' : 'bad' }}">{{ $allOk ? 'TERVERIFIKASI' : 'GAGAL VERIFIKASI' }}</span>
<h1 style="font-size:20px;margin:12px 0 4px">Tanda Tangan Digital {{ $signature->version?->document?->number }}</h1>
<p style="color:#475569;font-size:14px;margin:0">Verifikasi kriptografis terhadap versi dokumen v{{ $signature->version?->version }} dan keutuhan file di penyimpanan.</p>

<ul class="checks">
<li class="{{ $result['checks']['version_found'] ? 'ya' : 'tidak' }}">{{ $result['checks']['version_found'] ? '✓' : '✗' }} Versi dokumen ditemukan</li>
<li class="{{ $result['checks']['status_completed'] ? 'ya' : 'tidak' }}">{{ $result['checks']['status_completed'] ? '✓' : '✗' }} Status tanda tangan selesai</li>
<li class="{{ $result['checks']['hash_bound'] ? 'ya' : 'tidak' }}">{{ $result['checks']['hash_bound'] ? '✓' : '✗' }} Hash tanda tangan terikat versi dokumen</li>
<li class="{{ $result['checks']['file_intact'] ? 'ya' : 'tidak' }}">{{ $result['checks']['file_intact'] ? '✓' : '✗' }} File utuh (SHA-256 cocok, tidak diubah)</li>
</ul>

<dl>
<dt>Dokumen</dt><dd>{{ $signature->version?->document?->title }}</dd>
<dt>Versi</dt><dd>v{{ $signature->version?->version }}</dd>
<dt>Penandatangan</dt><dd>{{ $signature->signer_name }}@if($signature->signer_position) — {{ $signature->signer_position }}@endif</dd>
<dt>Tipe</dt><dd>{{ str($signature->signature_type)->replace('_',' ') }}</dd>
<dt>Waktu</dt><dd>{{ $signature->signed_at?->format('d/m/Y H:i:s') ?? '-' }} WIB</dd>
<dt>SHA-256</dt><dd class="hash">{{ $signature->signed_hash }}</dd>
</dl>

<footer>Halaman verifikasi publik. Pindai QR pada dokumen untuk membuka halaman ini kembali kapan pun. Nomor verifikasi: <span class="hash">{{ $token }}</span></footer>
</div>
</body>
</html>
