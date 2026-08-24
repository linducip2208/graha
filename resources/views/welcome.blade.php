<x-layouts.public>
@php($site = \App\Support\PublicSite::resolve())
@php($sec = $site['sections'])
@php($shot = fn (string $name) => asset('marketing/screens/'.$name.'.png'))

{{-- ===== HERO ===== --}}
<section class="relative overflow-hidden bg-gradient-to-b from-slate-950 via-slate-900 to-sky-950 text-white">
<div class="pointer-events-none absolute inset-0 opacity-40" style="background-image:radial-gradient(circle at 18% 20%, rgba(14,165,233,.35), transparent 45%),radial-gradient(circle at 85% 75%, rgba(6,182,212,.22), transparent 42%)"></div>
<div class="mx-auto grid max-w-6xl gap-12 px-5 pb-20 pt-16 lg:grid-cols-[1.05fr_1fr] lg:items-center lg:pb-28 lg:pt-24">
<div>
<p class="text-xs font-extrabold uppercase tracking-[0.28em] text-sky-400">ERP Konstruksi · Pondasi · Manufaktur</p>
<h1 class="mt-5 text-4xl font-black leading-[1.08] tracking-tight sm:text-5xl lg:text-[3.4rem]">{{ $site['hero_title'] }}</h1>
<p class="mt-6 max-w-xl text-base leading-relaxed text-sky-100/80 sm:text-lg">{{ $site['hero_subtitle'] }}</p>
<div class="mt-9 flex flex-wrap gap-3">
<a href="{{ $site['cta1_url'] }}" class="rounded-xl bg-sky-500 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-sky-950/50 transition hover:-translate-y-px hover:bg-sky-400">{{ $site['cta1_label'] }}</a>
<a href="{{ $site['cta2_url'] }}" class="rounded-xl border border-white/25 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-white/10">{{ $site['cta2_label'] }}</a>
</div>
<p class="mt-6 text-xs text-sky-200/60">Multi-company · Approval berjenjang · Jurnal otomatis · Audit hash-chain</p>
</div>
<div class="relative">
<div class="overflow-hidden rounded-2xl border border-white/15 shadow-2xl">
<img src="{{ $shot('dashboard-redesign-v2-1440') }}" alt="Tampilan dashboard Graha ERP" width="1440" height="900" class="h-auto w-full object-cover" fetchpriority="high">
</div>
<div class="absolute -left-4 top-8 hidden rounded-xl border border-white/15 bg-slate-900/90 p-3 shadow-xl backdrop-blur sm:block">
<p class="text-[10px] font-bold uppercase tracking-widest text-sky-300">Project Health</p>
<p class="mt-1 text-sm font-black">Watch / Critical / Healthy</p>
</div>
<div class="absolute -bottom-5 -right-3 hidden rounded-xl border border-white/15 bg-slate-900/90 p-3 shadow-xl backdrop-blur sm:block">
<p class="text-[10px] font-bold uppercase tracking-widest text-emerald-300">Approval</p>
<p class="mt-1 text-sm font-black">SLA · Quorum · Delegasi</p>
</div>
</div>
</div>
</section>

@if($sec['proof'])
{{-- ===== PRODUCT PROOF (tab screenshot asli) ===== --}}
<section class="mx-auto max-w-6xl px-5 py-20">
<div class="text-center">
<h2 class="text-3xl font-black tracking-tight">Satu sistem yang benar-benar terpakai harian</h2>
<p class="mx-auto mt-3 max-w-2xl text-slate-500">Bukan mockup — ini tampilan aplikasi: portofolio proyek, keuangan, foundation control, dan launcher aplikasi.</p>
</div>
<div id="proof-tabs" class="mt-10" data-proof>
<div class="flex flex-wrap justify-center gap-2">
<button type="button" data-proof-btn="dashboard" class="tab-link active">Dashboard</button>
<button type="button" data-proof-btn="project" class="tab-link">Project Control</button>
<button type="button" data-proof-btn="finance" class="tab-link">Keuangan</button>
<button type="button" data-proof-btn="foundation" class="tab-link">Foundation Control</button>
</div>
<div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
<div class="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3"><span class="h-3 w-3 rounded-full bg-red-400"></span><span class="h-3 w-3 rounded-full bg-amber-400"></span><span class="h-3 w-3 rounded-full bg-emerald-400"></span><span class="ml-3 hidden rounded-lg bg-white px-3 py-1 text-xs text-slate-400 sm:block">grahapondasi.test/admin</span></div>
<img data-proof-img src="{{ $shot('dashboard-redesign-v2-1440') }}" alt="Preview dashboard" width="1440" height="900" loading="lazy" class="h-auto w-full">
</div>
</div>
</section>
@endif

@if($sec['flow'])
{{-- ===== BUSINESS FLOW ===== --}}
<section id="flow" class="border-y border-slate-200 bg-slate-50">
<div class="mx-auto max-w-6xl px-5 py-20">
<h2 class="text-center text-3xl font-black tracking-tight">Dari peluang sampai serah terima</h2>
<p class="mx-auto mt-3 max-w-2xl text-center text-slate-500">Setiap tahap menulis jejak data yang saling terhubung — bukan silo spreadsheet.</p>
<div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
@foreach([['Tender','Peluang, estimasi & bidding','flag'],['Kontrak','Award, VO & retensi','document'],['Proyek','Zona, pile & jadwal','cube'],['Procurement','PR, RFQ, PO & receipt','cart'],['Field Ops','Drilling, beton & testing','wrench'],['Billing','Progress billing & retensi','receipt'],['Keuangan','Jurnal, pajak & rekonsiliasi','banknote'],['Handover','As-built, dossier & serah terima','check']] as $i => [$title, $desc, $icon])
<div class="relative rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<span class="absolute -top-3 left-5 rounded-full bg-sky-700 px-2.5 py-0.5 text-[10px] font-black text-white">{{ $i + 1 }}</span>
<x-ui.icon :name="$icon" class="h-5 w-5 text-sky-700" />
<p class="mt-3 font-bold">{{ $title }}</p>
<p class="mt-1 text-sm leading-relaxed text-slate-500">{{ $desc }}</p>
</div>
@endforeach
</div>
</div>
</section>
@endif

@if($sec['modules'])
{{-- ===== CORE WORKSPACES ===== --}}
<section id="modules" class="mx-auto max-w-6xl px-5 py-20">
<h2 class="text-center text-3xl font-black tracking-tight">Delapan workspace, satu platform</h2>
<p class="mx-auto mt-3 max-w-2xl text-center text-slate-500">Navigasi harian tetap ringkas — launcher menampilkan seluruh capability sesuai kewenangan.</p>
<div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
@php($ws = [['Komersial','Tender, pelanggan, kontrak & administrasi perubahan.','commercial',['Tender','Kontrak','VO & Retensi'],'commercial.webp'],['Proyek','Planning, field operations, foundation control & costing.','project',['Gantt','Bored Pile','Kurva-S'],'project.webp'],['Supply Chain','Inventory, material request, procurement & lot traceability.','supply-chain',['Stok','RFQ','Lot Trace'],'supply-chain.webp'],['Workshop & Equipment','Manufaktur, cage & casing, equipment dan BBM.','operations',['Routing','QC','Hour Meter'],'operations.webp'],['Keuangan','GL, billing, pajak, cash-bank, costing & aset tetap.','finance',['Jurnal','Pajak','EAC'],'finance.webp'],['Quality & HSE','Risiko, NCR, CAPA, audit mutu, JSA & insiden.','quality-hse',['NCR','JSA','Audit'],'quality-hse.webp'],['Dokumen & Approval','Versioning, approval berjenjang & tanda tangan digital.','documents-approval',['Versioning','Signing','Audit'],'documents-approval.webp'],['Laporan','Bisnis, keuangan, operasional, manufaktur & aging.','reports',['Executive','Aging','Export'],'reports.webp']])
@foreach($ws as [$title, $desc, $key, $chips, $cover])
<article class="card-lift group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="relative aspect-[16/9] overflow-hidden bg-slate-900">
<img src="{{ asset('images/apps/'.$cover) }}" alt="Cover workspace {{ $title }}" width="1200" height="675" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.04]" onerror="this.remove()">
</div>
<div class="p-6">
<h3 class="text-lg font-black">{{ $title }}</h3>
<p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $desc }}</p>
<div class="mt-4 flex flex-wrap gap-1.5">@foreach($chips as $chip)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-500">{{ $chip }}</span>@endforeach</div>
<a href="/docs" class="mt-5 inline-flex items-center gap-1 text-sm font-bold text-sky-700 hover:underline">Pelajari <span aria-hidden="true">→</span></a>
</div>
</article>
@endforeach
</div>
</section>
@endif

@if($sec['foundation'])
{{-- ===== PROJECT & FOUNDATION SPOTLIGHT ===== --}}
<section id="foundation" class="border-y border-slate-200 bg-slate-950 text-white">
<div class="mx-auto grid max-w-6xl items-center gap-12 px-5 py-20 lg:grid-cols-2">
<div class="overflow-hidden rounded-2xl border border-white/15 shadow-2xl">
<img src="{{ $shot('projects-portfolio-v2-1440') }}" alt="Project Control Center" width="1440" height="900" loading="lazy" class="h-auto w-full">
</div>
<div>
<p class="text-xs font-extrabold uppercase tracking-[0.28em] text-amber-400">Project & Foundation Control</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Portofolio proyek sampai tiap titik pile</h2>
<p class="mt-4 leading-relaxed text-slate-300">Project Control Center memantau progres, nilai kontrak, margin, dan kesehatan proyek. Turun satu level: zone, bored pile, drilling, beton, testing — dengan risk engine deterministik.</p>
<ul class="mt-6 grid gap-2.5 text-sm sm:grid-cols-2">
@foreach(['Portfolio & project health','Gantt & Kurva-S','Field operations harian','Drilling record & bore log','Delivery beton + slump','Pile testing','Foundation Control Tower','Pile Passport & genealogi'] as $item)
<li class="flex items-center gap-2 text-slate-200"><x-ui.icon name="check" class="h-4 w-4 shrink-0 text-emerald-400" />{{ $item }}</li>
@endforeach
</ul>
</div>
</div>
</section>
@endif

@if($sec['passport'])
{{-- ===== DIGITAL PILE PASSPORT ===== --}}
<section class="mx-auto max-w-6xl px-5 py-20">
<div class="grid items-center gap-12 lg:grid-cols-2">
<div class="order-last lg:order-first">
<p class="text-xs font-extrabold uppercase tracking-[0.28em] text-sky-600">Digital Pile Passport</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Setiap pile punya paspor digital</h2>
<p class="mt-4 leading-relaxed text-slate-500">Genealogi lengkap tiap titik pile: identitas, kronologi konstruksi, evidence foto, pengujian, acceptance berjenjang, as-built, sampai dossier serah terima. QR passport membuat riwayat pile dapat dibuka oleh pihak berkepentingan.</p>
<div class="mt-6 flex flex-wrap gap-2">@foreach(['QR Passport','Genealogi','Evidence','Acceptance','As-Built PDF','Dossier & Handover'] as $chip)<span class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600">{{ $chip }}</span>@endforeach</div>
</div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
<img src="{{ $shot('pile-passport-v2-1440') }}" alt="Pile Passport" width="1440" height="900" loading="lazy" class="h-auto w-full">
</div>
</div>
</div>
</section>
@endif

@if($sec['finance'])
{{-- ===== FINANCE ===== --}}
<section id="finance" class="border-y border-slate-200 bg-slate-50">
<div class="mx-auto grid max-w-6xl items-center gap-12 px-5 py-20 lg:grid-cols-2">
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
<img src="{{ $shot('finance-overview-v2-1440') }}" alt="Ikhtisar Keuangan" width="1440" height="900" loading="lazy" class="h-auto w-full">
</div>
<div>
<p class="text-xs font-extrabold uppercase tracking-[0.28em] text-sky-600">Keuangan Terintegrasi Operasional</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Jurnal berimbang dari kegiatan nyata</h2>
<p class="mt-4 leading-relaxed text-slate-500">Billing memicu jurnal, penerimaan barang membentuk GRNI, penyusutan terposting otomatis — semua melalui mapping akun yang dapat dikonfigurasi, dengan periode fiskal & rekonsiliasi bank.</p>
<ul class="mt-6 grid gap-2.5 text-sm sm:grid-cols-2">
@foreach(['General Ledger & periode','Progress billing & retensi','Pajak & bukti potong','Kas, bank & rekonsiliasi','Project costing & EAC','Fixed asset & depresiasi','Trial balance & laporan keuangan','Procurement posting'] as $item)
<li class="flex items-center gap-2 text-slate-700"><x-ui.icon name="check" class="h-4 w-4 shrink-0 text-emerald-600" />{{ $item }}</li>
@endforeach
</ul>
</div>
</div>
</section>
@endif

@if($sec['supply'] || $sec['workshop'])
{{-- ===== SUPPLY CHAIN + WORKSHOP ===== --}}
<section class="mx-auto max-w-6xl px-5 py-20">
<div class="grid gap-12 lg:grid-cols-2">
@if($sec['supply'])
<div>
<p class="text-xs font-extrabold uppercase tracking-[0.28em] text-emerald-600">Supply Chain</p>
<h2 class="mt-3 text-2xl font-black tracking-tight">Material terlacak dari pengadaan sampai proyek</h2>
<p class="mt-3 leading-relaxed text-slate-500">Inventory FIFO, permintaan material proyek, stock opname, reorder recommendation, lot traceability, procurement dengan RFQ & price comparison, sampai tools check-out.</p>
</div>
@endif
@if($sec['workshop'])
<div>
<p class="text-xs font-extrabold uppercase tracking-[0.28em] text-sky-600">Workshop & Equipment</p>
<h2 class="mt-3 text-2xl font-black tracking-tight">Produksi terkendali, biaya terukur</h2>
<p class="mt-3 leading-relaxed text-slate-500">Routing & costing produksi, QC dengan disposition, output nonconforming, reinforcement cage & casing, equipment dengan hour meter, dan tangki BBM dengan rekonsiliasi.</p>
</div>
@endif
</div>
</section>
@endif

@if($sec['qhse'])
{{-- ===== QMS & HSE ===== --}}
<section id="qhse" class="border-y border-slate-200 bg-slate-50">
<div class="mx-auto grid max-w-6xl items-center gap-12 px-5 py-20 lg:grid-cols-2">
<div>
<p class="text-xs font-extrabold uppercase tracking-[0.28em] text-emerald-600">Quality & HSE</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Bukti penerapan sistem manajemen mutu</h2>
<p class="mt-4 leading-relaxed text-slate-500">Risiko & peluang, NCR dengan containment dan CAPA ber-tenggat, audit mutu, JSA, insiden dengan investigasi dan tindakan korektif. Sistem mendukung dokumentasi dan bukti penerapan sistem manajemen mutu — bukan klaim sertifikasi.</p>
</div>
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
<img src="{{ $shot('qms-v2-1440') }}" alt="Quality & HSE workspace" width="1440" height="900" loading="lazy" class="h-auto w-full">
</div>
</div>
</section>
@endif

@if($sec['documents'])
{{-- ===== DOCUMENT & APPROVAL ===== --}}
<section class="mx-auto max-w-6xl px-5 py-20">
<div class="grid items-center gap-12 lg:grid-cols-2">
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
<img src="{{ $shot('documents-index-v2-1440') }}" alt="Document Control" width="1440" height="900" loading="lazy" class="h-auto w-full">
</div>
<div>
<p class="text-xs font-extrabold uppercase tracking-[0.28em] text-violet-600">Document & Approval</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Dokumen berversi, approval terukur</h2>
<p class="mt-4 leading-relaxed text-slate-500">Setiap versi dokumen terikat hash SHA-256. Alur: dokumen → review → approval berjenjang (SLA, quorum, delegasi) → tanda tangan digital → terkunci & terarsip. Verifikasi tanda tangan via QR dapat diakses publik.</p>
<div class="mt-6 flex flex-wrap gap-2">@foreach(['Versioning SHA-256','Workflow SLA','Digital Signing','QR Verify','Audit Trail'] as $chip)<span class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600">{{ $chip }}</span>@endforeach</div>
</div>
</div>
</section>
@endif

@if($sec['security'] || $sec['multicompany'])
{{-- ===== SECURITY & MULTI-COMPANY ===== --}}
<section id="security" class="bg-slate-950 text-white">
<div class="mx-auto max-w-6xl px-5 py-20">
<h2 class="text-center text-3xl font-black tracking-tight">Governance yang bisa dipertanggungjawabkan</h2>
<div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
@foreach([['Role & Permission','Kewenangan per modul per perusahaan','shield'],['Company Isolation','Data PT A tidak terlihat PT B','building'],['Audit Hash-Chain','Log append-only dengan rantai hash','document'],['Approval & Signing','Keputusan tercatat, tanda tangan terverifikasi','pen']] as [$title, $desc, $icon])
<div class="rounded-2xl border border-white/10 bg-white/5 p-6">
<x-ui.icon :name="$icon" class="h-6 w-6 text-sky-400" />
<p class="mt-4 font-bold">{{ $title }}</p>
<p class="mt-2 text-sm leading-relaxed text-slate-400">{{ $desc }}</p>
</div>
@endforeach
</div>
@if($sec['multicompany'])
<div class="mt-12 rounded-2xl border border-white/10 bg-white/5 p-8 text-center">
<p class="text-lg font-black">Satu instalasi, beberapa perusahaan</p>
<p class="mx-auto mt-2 max-w-2xl text-sm leading-relaxed text-slate-400">PT A · PT B · PT C berbagi platform yang sama — konteks perusahaan terisolasi: master data, transaksi, laporan, dan branding masing-masing.</p>
</div>
@endif
</div>
</section>
@endif

{{-- ===== IMPLEMENTATION + FINAL CTA ===== --}}
<section class="mx-auto max-w-6xl px-5 py-20">
<h2 class="text-center text-3xl font-black tracking-tight">Mulai dari proses yang sudah berjalan</h2>
<div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
@foreach([['Struktur perusahaan','Perusahaan, cabang, departemen & membership'],['Master & permission','Role, kewenangan, item, gudang, COA'],['Workflow operasional','Tender, proyek, pengadaan, lapangan'],['Go-live & monitoring','Dashboard, laporan & audit trail']] as $i => [$title, $desc])
<div class="relative rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
<span class="grid h-9 w-9 place-items-center rounded-xl bg-sky-700 text-sm font-black text-white">{{ $i + 1 }}</span>
<p class="mt-4 font-bold">{{ $title }}</p>
<p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $desc }}</p>
</div>
@endforeach
</div>
</section>
<section class="bg-gradient-to-br from-sky-900 via-sky-950 to-slate-950 text-white">
<div class="mx-auto max-w-4xl px-5 py-20 text-center">
<h2 class="text-3xl font-black tracking-tight sm:text-4xl">Satu sistem untuk mengendalikan proyek dari tender hingga handover.</h2>
<p class="mx-auto mt-4 max-w-xl text-sky-100/80">Jelajahi modul, dokumentasi, dan alurnya — lalu masuk dengan akun Anda.</p>
<div class="mt-9 flex flex-wrap justify-center gap-3">
<a href="/login" class="rounded-xl bg-sky-500 px-7 py-3.5 text-sm font-bold shadow-lg transition hover:-translate-y-px hover:bg-sky-400">Masuk ke Sistem</a>
<a href="/docs" class="rounded-xl border border-white/25 px-7 py-3.5 text-sm font-bold transition hover:bg-white/10">Lihat Dokumentasi</a>
</div>
</div>
</section>
</x-layouts.public>
