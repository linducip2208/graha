<x-layouts.app title="Masuk — {{ config('app.name') }}">
<div class="grid min-h-[calc(100vh-65px)] lg:grid-cols-2 print:hidden">
<section class="relative hidden overflow-hidden bg-gradient-to-br from-sky-900 via-sky-950 to-slate-950 p-12 text-white lg:flex lg:flex-col lg:justify-between">
<div class="pointer-events-none absolute inset-0 opacity-40" style="background-image:radial-gradient(circle at 20% 15%, rgba(14,165,233,.35), transparent 45%),radial-gradient(circle at 85% 80%, rgba(6,182,212,.25), transparent 40%);"></div>
<span class="hero-orb hero-orb-one" aria-hidden="true"></span>
<span class="absolute -bottom-24 -right-24 select-none text-[18rem] opacity-10" aria-hidden="true">🏗️</span>
<div class="relative flex items-center gap-2"><span class="grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-lg backdrop-blur">🏗️</span><strong>Graha Pondasi ERP</strong></div>
<div class="relative">
<h1 class="max-w-lg text-5xl font-black leading-tight">Keputusan proyek dengan data yang dapat ditelusuri.</h1>
<p class="mt-4 max-w-md text-sky-200/90">Dari tender, bored pile, procurement, sampai jurnal berimbang — satu sistem multi-company dengan jejak audit hash-chain.</p>
<div class="mt-8 grid max-w-md grid-cols-3 gap-3">
@foreach([['✅','Approval Berjenjang','SLA, quorum & delegasi'],['⚖️','Jurnal Otomatis','Selalu balanced & idempotent'],['🔒','Audit Hash-Chain','Append-only, anti manipulasi']] as [$icon, $title, $desc])
<div class="rounded-xl bg-white/10 p-4 backdrop-blur"><span class="text-xl">{{ $icon }}</span><p class="mt-2 text-sm font-bold">{{ $title }}</p><p class="mt-1 text-xs text-sky-200/70">{{ $desc }}</p></div>
@endforeach
</div>
</div>
<p class="relative text-xs text-sky-300/60">© {{ date('Y') }} PT Graha Pondasi · Powered by Laravel</p>
</section>

<section class="flex items-center justify-center p-8">
<div class="w-full max-w-md">
<h1 class="text-4xl font-black">Masuk</h1>
<p class="mt-2 text-slate-500">Gunakan akun demo di bawah untuk menjelajah seluruh modul.</p>
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
<form method="post" action="/login" class="mt-6 space-y-4">
@csrf
<label class="block text-sm font-semibold">Email<input class="mt-1.5 w-full rounded-xl border border-stone-300 p-3 transition focus:border-sky-500 focus:outline-none focus:ring-3 focus:ring-sky-500/20" type="email" name="email" required autocomplete="username"></label>
<label class="block text-sm font-semibold">Kata sandi<input class="mt-1.5 w-full rounded-xl border border-stone-300 p-3 transition focus:border-sky-500 focus:outline-none focus:ring-3 focus:ring-sky-500/20" type="password" name="password" required autocomplete="current-password"></label>
<button class="w-full rounded-xl bg-gradient-to-r from-sky-700 to-cyan-700 p-3 font-bold text-white shadow-lg shadow-sky-900/20 transition hover:-translate-y-px hover:shadow-xl">Masuk</button>
</form>
<div class="mt-6 rounded-xl border border-stone-200 bg-stone-50 p-4 text-xs">
<p class="mb-2 font-bold text-stone-800">🧪 Demo Login — semua role pakai password <code class="font-mono">password</code></p>
<div class="space-y-1 font-mono text-stone-600">
<div><span class="font-bold">Admin:</span> admin@grahapondasi.test — akses penuh</div>
<div><span class="font-bold">Finance:</span> finance@grahapondasi.test — billing, pajak, jurnal</div>
<div><span class="font-bold">PM:</span> pm@grahapondasi.test — proyek, equipment, HSE</div>
<div><span class="font-bold">Procurement:</span> procurement@grahapondasi.test — vendor & stok</div>
<div><span class="font-bold">Direktur:</span> direktur@grahapondasi.test — approval & laporan</div>
</div>
</div>
</div>
</section>
</div>
</x-layouts.app>
