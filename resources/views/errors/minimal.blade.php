<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $code ?? 500 }} — {{ config('app.name', 'Graha Pondasi ERP') }}</title>
<meta name="robots" content="noindex">
<link rel="icon" href="{{ asset('favicon-default.svg') }}">
<style>
body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;background:#f6f8fb;color:#0f172a;display:grid;place-items:center;min-height:100vh;padding:24px}
.wrap{max-width:480px;text-align:center}
.code{font-size:88px;font-weight:900;letter-spacing:-.04em;background:linear-gradient(135deg,#0369a1,#0e7490);-webkit-background-clip:text;background-clip:text;color:transparent;line-height:1}
h1{margin:14px 0 8px;font-size:22px;font-weight:800}
p{margin:0;color:#64748b;font-size:14px;line-height:1.6}
.actions{margin-top:26px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
a{display:inline-block;padding:11px 20px;border-radius:12px;font-size:14px;font-weight:700;text-decoration:none;transition:.2s}
.primary{background:#0369a1;color:#fff}.primary:hover{background:#075985}
.ghost{border:1px solid #dbe3ec;color:#334155}.ghost:hover{background:#eef2f7}
footer{margin-top:34px;font-size:12px;color:#94a3b8}
@media(prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
</head>
<body>
<div class="wrap">
<div class="code">{{ $code ?? 500 }}</div>
<h1>{{ $title ?? 'Terjadi kesalahan' }}</h1>
<p>{{ $description ?? 'Sesuatu tidak berjalan semestinya. Coba lagi atau kembali ke beranda.' }}</p>
<div class="actions">
<a href="/" class="primary">Ke Beranda</a>
<a href="/login" class="ghost">Masuk</a>
</div>
<footer>© {{ date('Y') }} {{ config('app.name', 'Graha Pondasi ERP') }}</footer>
</div>
</body>
</html>
