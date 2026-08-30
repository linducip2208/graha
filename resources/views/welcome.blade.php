<x-layouts.public :title="'ERP Konstruksi Pondasi & Bored Pile'" :description="'ERP multi-company untuk kontraktor pondasi: tender, bored pile, field operations, keuangan berimbang, QMS/HSE, dan audit hash-chain — dalam satu sistem.'">
@php($site = \App\Support\PublicSite::resolve())
@php($sec = $site['sections'])
@php($shot = fn (string $name) => asset('marketing/screens/'.$name.'.png'))

{{-- ===== HERO — permukaan kerja ===== --}}
<section class="relative isolate overflow-hidden bg-slate-950 text-white">
<div class="absolute inset-0"><x-bored-pile-animation fill /></div>
<div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-slate-950/95 via-slate-950/60 to-slate-950/10"></div>
<div class="pointer-events-none absolute inset-x-0 bottom-0 h-44 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent"></div>
<div class="relative mx-auto max-w-6xl px-5 pb-28 pt-20 lg:pb-40 lg:pt-28">
<p class="text-xs font-extrabold uppercase tracking-[0.28em] text-sky-400">ERP Konstruksi · Pondasi · Manufaktur</p>
<h1 class="mt-5 max-w-3xl text-4xl font-black leading-[1.08] tracking-tight sm:text-5xl lg:text-[3.4rem]">{{ $site['hero_title'] }}</h1>
<p class="mt-6 max-w-xl text-base leading-relaxed text-sky-100/80 sm:text-lg">{{ $site['hero_subtitle'] }}</p>
<div class="mt-9 flex flex-wrap gap-3">
<a href="{{ $site['cta1_url'] }}" class="rounded-xl bg-sky-700 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-sky-950/50 ring-1 ring-sky-400/40 transition hover:-translate-y-px hover:bg-sky-600">{{ $site['cta1_label'] }}</a>
<a href="{{ $site['cta2_url'] }}" class="rounded-xl border border-white/25 bg-white/5 px-6 py-3.5 text-sm font-bold text-white backdrop-blur transition hover:bg-white/15">{{ $site['cta2_label'] }}</a>
</div>
<p class="mt-6 text-xs text-sky-200/75">Multi-company · Approval berjenjang · Jurnal otomatis · Audit hash-chain</p>
</div>
<p class="bp-dig-hint" aria-hidden="true">GULIR UNTUK MENGGALI <span>▼</span></p>
</section>
@if($sec['proof'])
<x-strata-divider class="bg-slate-950" :colors="['#242016','#4a3c26','#f5f1e8']" />
@endif

@if($sec['proof'])
{{-- ===== PRODUCT PROOF — permukaan ===== --}}
<section class="bp-topo bg-[#f5f1e8]">
<div class="mx-auto max-w-6xl px-5 py-20">
<div class="text-center">
<p class="elev-badge">EL ±0.00 · Permukaan Kerja</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Satu sistem yang benar-benar terpakai harian</h2>
<p class="mx-auto mt-3 max-w-2xl text-slate-600">Bukan mockup — ini tampilan aplikasi: portofolio proyek, keuangan, foundation control, dan launcher aplikasi.</p>
</div>
<div id="proof-tabs" class="mt-10" data-proof>
<div class="flex flex-wrap justify-center gap-2">
<button type="button" data-proof-btn="dashboard" class="tab-link active" aria-pressed="true">Dashboard</button>
<button type="button" data-proof-btn="project" class="tab-link" aria-pressed="false">Project Control</button>
<button type="button" data-proof-btn="finance" class="tab-link" aria-pressed="false">Keuangan</button>
<button type="button" data-proof-btn="foundation" class="tab-link" aria-pressed="false">Foundation Control</button>
</div>
<div class="mt-6 overflow-hidden rounded-2xl border border-[#e0d7c2] bg-white shadow-xl">
<div class="flex items-center gap-2 border-b border-[#e0d7c2] bg-[#f2ede0] px-4 py-3"><span class="h-3 w-3 rounded-full bg-red-400"></span><span class="h-3 w-3 rounded-full bg-amber-400"></span><span class="h-3 w-3 rounded-full bg-emerald-400"></span><span class="ml-3 hidden rounded-lg bg-white px-3 py-1 text-xs text-slate-500 sm:block">grahapondasi.test/admin</span></div>
<img data-proof-img src="{{ $shot('dashboard-redesign-v2-1440') }}" alt="Preview dashboard" width="1440" height="900" loading="lazy" decoding="async" class="h-auto w-full">
</div>
</div>
</div>
</section>
@endif

@if($sec['flow'])
{{-- ===== BUSINESS FLOW — jalur hauling ===== --}}
<section id="flow" class="border-y border-[#e0d7c2] bg-[#ece5d4]">
<div class="mx-auto max-w-6xl px-5 py-20">
<div class="text-center">
<p class="elev-badge">Tinjauan Atas · Alur Operasional</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Dari peluang sampai serah terima</h2>
<p class="mx-auto mt-3 max-w-2xl text-slate-600">Setiap tahap menulis jejak data yang saling terhubung — bukan silo spreadsheet.</p>
</div>
<div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
@foreach([['Tender','Peluang, estimasi & bidding','flag'],['Kontrak','Award, VO & retensi','document'],['Proyek','Zona, pile & jadwal','cube'],['Procurement','PR, RFQ, PO & receipt','cart'],['Field Ops','Drilling, beton & testing','wrench'],['Billing','Progress billing & retensi','receipt'],['Keuangan','Jurnal, pajak & rekonsiliasi','banknote'],['Handover','As-built, dossier & serah terima','check']] as $i => [$title, $desc, $icon])
<div class="flow-step relative rounded-2xl border border-[#e0d7c2] bg-white p-5 shadow-sm">
<span class="absolute -top-3 left-5 rounded-full bg-amber-500 px-2.5 py-0.5 text-[10px] font-black text-slate-950">{{ $i + 1 }}</span>
<x-ui.icon :name="$icon" class="h-5 w-5 text-amber-700" />
<p class="mt-3 font-bold">{{ $title }}</p>
<p class="mt-1 text-sm leading-relaxed text-slate-500">{{ $desc }}</p>
</div>
@endforeach
</div>
</div>
</section>
@endif

@if($sec['modules'])
{{-- ===== CORE WORKSPACES — peta site ===== --}}
<section id="modules" class="bp-topo bg-[#f5f1e8]">
<div class="mx-auto max-w-6xl px-5 py-20">
<div class="text-center">
<p class="elev-badge">Peta Site · 8 Workspace</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Delapan workspace, satu platform</h2>
<p class="mx-auto mt-3 max-w-2xl text-center text-slate-600">Navigasi harian tetap ringkas — launcher menampilkan seluruh capability sesuai kewenangan.</p>
</div>
<div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
@php($ws = [['Komersial','Tender, pelanggan, kontrak & administrasi perubahan.','commercial',['Tender','Kontrak','VO & Retensi'],'commercial.webp'],['Proyek','Planning, field operations, foundation control & costing.','project',['Gantt','Bored Pile','Kurva-S'],'project.webp'],['Supply Chain','Inventory, material request, procurement & lot traceability.','supply-chain',['Stok','RFQ','Lot Trace'],'supply-chain.webp'],['Workshop & Equipment','Manufaktur, cage & casing, equipment dan BBM.','operations',['Routing','QC','Hour Meter'],'operations.webp'],['Keuangan','GL, billing, pajak, cash-bank, costing & aset tetap.','finance',['Jurnal','Pajak','EAC'],'finance.webp'],['Quality & HSE','Risiko, NCR, CAPA, audit mutu, JSA & insiden.','quality-hse',['NCR','JSA','Audit'],'quality-hse.webp'],['Dokumen & Approval','Versioning, approval berjenjang & tanda tangan digital.','documents-approval',['Versioning','Signing','Audit'],'documents-approval.webp'],['Laporan','Bisnis, keuangan, operasional, manufaktur & aging.','reports',['Executive','Aging','Export'],'reports.webp']])
@foreach($ws as [$title, $desc, $key, $chips, $cover])
<article class="card-lift group overflow-hidden rounded-2xl border border-[#e0d7c2] bg-white shadow-sm">
<div class="relative aspect-[16/9] overflow-hidden bg-slate-900">
<img src="{{ asset('images/apps/'.$cover) }}" alt="Cover workspace {{ $title }}" width="1200" height="675" loading="lazy" decoding="async" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.04]" onerror="this.remove()">
</div>
<div class="p-6">
<h3 class="text-lg font-black">{{ $title }}</h3>
<p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $desc }}</p>
<div class="mt-4 flex flex-wrap gap-1.5">@foreach($chips as $chip)<span class="rounded-full bg-[#f2ede0] px-2.5 py-1 text-[11px] font-bold text-slate-600">{{ $chip }}</span>@endforeach</div>
<a href="/docs" class="mt-5 inline-flex items-center gap-1 text-sm font-bold text-sky-700 hover:underline">Pelajari <span aria-hidden="true">→</span></a>
</div>
</article>
@endforeach
</div>
</div>
</section>
@endif

@if($sec['modules'] && $sec['foundation'])
<x-strata-divider class="bg-[#f5f1e8]" :colors="['#6b5836','#3a2d1a','#16110a']" />
@endif

@if($sec['foundation'])
{{-- ===== FOUNDATION SPOTLIGHT — zona pengeloboran ===== --}}
<section id="foundation" class="relative isolate overflow-hidden bg-[#16110a] text-white">
<div class="bp-topo-dark pointer-events-none absolute inset-0"></div>
<div class="relative mx-auto grid max-w-6xl items-center gap-12 px-5 py-20 lg:grid-cols-2">
<div class="overflow-hidden rounded-2xl border border-white/15 shadow-2xl">
<img src="{{ $shot('projects-portfolio-v2-1440') }}" alt="Project Control Center" width="1440" height="900" loading="lazy" decoding="async" class="h-auto w-full">
</div>
<div>
<p class="elev-badge elev-badge-dark">EL -6.00 · Zona Pengeloboran</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Portofolio proyek sampai tiap titik pile</h2>
<p class="mt-4 leading-relaxed text-slate-300">Project Control Center memantau progres, nilai kontrak, margin, dan kesehatan proyek. Turun satu level: zone, bored pile, drilling, beton, testing — dengan risk engine deterministik.</p>
<ul class="mt-6 grid gap-2.5 text-sm sm:grid-cols-2">
@foreach(['Portfolio & project health','Gantt & Kurva-S','Field operations harian','Drilling record & bore log','Delivery beton + slump','Pile testing','Foundation Control Tower','Pile Passport & genealogi'] as $item)
<li class="flex items-center gap-2 text-slate-200"><x-ui.icon name="check" class="h-4 w-4 shrink-0 text-amber-400" />{{ $item }}</li>
@endforeach
</ul>
</div>
</div>
</section>
@endif

@if($sec['passport'])
{{-- ===== DIGITAL PILE PASSPORT — lapias pile ===== --}}
<section class="relative isolate overflow-hidden bg-[#100c07] text-white">
<div class="relative mx-auto max-w-6xl px-5 py-20">
<div class="grid items-center gap-12 lg:grid-cols-2">
<div class="order-last lg:order-first">
<p class="elev-badge elev-badge-dark">EL -14.00 · Lapias Pile</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Setiap pile punya paspor digital</h2>
<p class="mt-4 leading-relaxed text-slate-300">Genealogi lengkap tiap titik pile: identitas, kronologi konstruksi, evidence foto, pengujian, acceptance berjenjang, as-built, sampai dossier serah terima. QR passport membuat riwayat pile dapat dibuka oleh pihak berkepentingan.</p>
<div class="mt-6 flex flex-wrap gap-2">@foreach(['QR Passport','Genealogi','Evidence','Acceptance','As-Built PDF','Dossier & Handover'] as $chip)<span class="rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-xs font-bold text-amber-200/90">{{ $chip }}</span>@endforeach</div>
</div>
<div class="overflow-hidden rounded-2xl border border-white/15 bg-white shadow-xl">
<img src="{{ $shot('pile-passport-v2-1440') }}" alt="Pile Passport" width="1440" height="900" loading="lazy" decoding="async" class="h-auto w-full">
</div>
</div>
</div>
</section>
@endif

@if($sec['passport'] && $sec['finance'])
<x-strata-divider class="bg-[#100c07]" :colors="['#4a3c26','#cfc3a8','#f5f1e8']" />
@endif

@if($sec['finance'])
{{-- ===== FINANCE — struktur & biaya ===== --}}
<section id="finance" class="bp-topo bg-[#f5f1e8]">
<div class="mx-auto grid max-w-6xl items-center gap-12 px-5 py-20 lg:grid-cols-2">
<div class="overflow-hidden rounded-2xl border border-[#e0d7c2] bg-white shadow-xl">
<img src="{{ $shot('finance-overview-v2-1440') }}" alt="Ikhtisar Keuangan" width="1440" height="900" loading="lazy" decoding="async" class="h-auto w-full">
</div>
<div>
<p class="elev-badge">EL ±0.00 · Struktur & Biaya</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Jurnal berimbang dari kegiatan nyata</h2>
<p class="mt-4 leading-relaxed text-slate-600">Billing memicu jurnal, penerimaan barang membentuk GRNI, penyusutan terposting otomatis — semua melalui mapping akun yang dapat dikonfigurasi, dengan periode fiskal & rekonsiliasi bank.</p>
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
<section class="border-y border-[#e0d7c2] bg-[#ece5d4]">
<div class="mx-auto grid max-w-6xl gap-12 px-5 py-20 lg:grid-cols-2">
@if($sec['supply'])
<div>
<p class="elev-badge">Gudang & Logistik</p>
<h2 class="mt-3 text-2xl font-black tracking-tight">Material terlacak dari pengadaan sampai proyek</h2>
<p class="mt-3 leading-relaxed text-slate-600">Inventory FIFO, permintaan material proyek, stock opname, reorder recommendation, lot traceability, procurement dengan RFQ & price comparison, sampai tools check-out.</p>
</div>
@endif
@if($sec['workshop'])
<div class="mt-10 lg:mt-0">
<p class="elev-badge">Workshop</p>
<h2 class="mt-3 text-2xl font-black tracking-tight">Produksi terkendali, biaya terukur</h2>
<p class="mt-3 leading-relaxed text-slate-600">Routing & costing produksi, QC dengan disposition, output nonconforming, reinforcement cage & casing, equipment dengan hour meter, dan tangki BBM dengan rekonsiliasi.</p>
</div>
@endif
</div>
</section>
@endif

@if($sec['qhse'])
{{-- ===== QMS & HSE ===== --}}
<section id="qhse" class="bp-topo bg-[#f5f1e8]">
<div class="mx-auto grid max-w-6xl items-center gap-12 px-5 py-20 lg:grid-cols-2">
<div>
<p class="elev-badge">EL +3.00 · Mutu & K3</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Bukti penerapan sistem manajemen mutu</h2>
<p class="mt-4 leading-relaxed text-slate-600">Risiko & peluang, NCR dengan containment dan CAPA ber-tenggat, audit mutu, JSA, insiden dengan investigasi dan tindakan korektif. Sistem mendukung dokumentasi dan bukti penerapan sistem manajemen mutu — bukan klaim sertifikasi.</p>
</div>
<div class="overflow-hidden rounded-2xl border border-[#e0d7c2] bg-white shadow-xl">
<img src="{{ $shot('qms-v2-1440') }}" alt="Quality & HSE workspace" width="1440" height="900" loading="lazy" decoding="async" class="h-auto w-full">
</div>
</div>
</section>
@endif

@if($sec['documents'])
{{-- ===== DOCUMENT & APPROVAL ===== --}}
<section class="bg-[#ece5d4]">
<div class="mx-auto grid max-w-6xl items-center gap-12 px-5 py-20 lg:grid-cols-2">
<div class="overflow-hidden rounded-2xl border border-[#e0d7c2] bg-white shadow-xl">
<img src="{{ $shot('documents-index-v2-1440') }}" alt="Document Control" width="1440" height="900" loading="lazy" decoding="async" class="h-auto w-full">
</div>
<div>
<p class="elev-badge">EL +5.00 · Dokumen & Approval</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Dokumen berversi, approval terukur</h2>
<p class="mt-4 leading-relaxed text-slate-600">Setiap versi dokumen terikat hash SHA-256. Alur: dokumen → review → approval berjenjang (SLA, quorum, delegasi) → tanda tangan digital → terkunci & terarsip. Verifikasi tanda tangan via QR dapat diakses publik.</p>
<div class="mt-6 flex flex-wrap gap-2">@foreach(['Versioning SHA-256','Workflow SLA','Digital Signing','QR Verify','Audit Trail'] as $chip)<span class="rounded-full border border-[#d8cdb2] bg-white px-3 py-1.5 text-xs font-bold text-slate-600">{{ $chip }}</span>@endforeach</div>
</div>
</div>
</section>
@endif

@if($sec['documents'] && $sec['security'])
<x-strata-divider class="bg-[#ece5d4]" :colors="['#27354f','#12203a','#0b1220']" />
@endif

@if($sec['security'] || $sec['multicompany'])
{{-- ===== SECURITY — bedrock ===== --}}
<section id="security" class="bp-bedrock bg-[#0b1220] text-white">
<div class="mx-auto max-w-6xl px-5 py-20">
<div class="text-center">
<p class="elev-badge elev-badge-dark">EL -28.00 · Bedrock · SPT &gt; 50</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Governance yang bisa dipertanggungjawabkan</h2>
</div>
<div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
@foreach([['Role & Permission','Kewenangan per modul per perusahaan','shield'],['Company Isolation','Data PT A tidak terlihat PT B','building'],['Audit Hash-Chain','Log append-only dengan rantai hash','document'],['Approval & Signing','Keputusan tercatat, tanda tangan terverifikasi','pen']] as [$title, $desc, $icon])
<div class="rounded-2xl border border-white/10 bg-white/5 p-6 transition hover:border-amber-400/40 hover:bg-white/10">
<x-ui.icon :name="$icon" class="h-6 w-6 text-amber-400" />
<p class="mt-4 font-bold">{{ $title }}</p>
<p class="mt-2 text-sm leading-relaxed text-slate-300">{{ $desc }}</p>
</div>
@endforeach
</div>
@if($sec['multicompany'])
<div class="mt-12 rounded-2xl border border-white/10 bg-white/5 p-8 text-center">
<p class="text-lg font-black">Satu instalasi, beberapa perusahaan</p>
<p class="mx-auto mt-2 max-w-2xl text-sm leading-relaxed text-slate-300">PT A · PT B · PT C berbagi platform yang sama — konteks perusahaan terisolasi: master data, transaksi, laporan, dan branding masing-masing.</p>
</div>
@endif
</div>
</section>
@endif

@if($sec['security'])
<x-strata-divider class="bg-[#0b1220]" :colors="['#2a3a5c','#8d9db4','#f5f1e8']" />
@endif

{{-- ===== IMPLEMENTATION ===== --}}
<section class="bp-topo bg-[#f5f1e8]">
<div class="mx-auto max-w-6xl px-5 py-20">
<div class="text-center">
<p class="elev-badge">Program Kerja</p>
<h2 class="mt-4 text-3xl font-black tracking-tight">Mulai dari proses yang sudah berjalan</h2>
</div>
<div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
@foreach([['Struktur perusahaan','Perusahaan, cabang, departemen & membership'],['Master & permission','Role, kewenangan, item, gudang, COA'],['Workflow operasional','Tender, proyek, pengadaan, lapangan'],['Go-live & monitoring','Dashboard, laporan & audit trail']] as $i => [$title, $desc])
<div class="relative rounded-2xl border border-[#e0d7c2] bg-white p-6 shadow-sm">
<span class="grid h-9 w-9 place-items-center rounded-xl bg-amber-500 text-sm font-black text-slate-950">{{ $i + 1 }}</span>
<p class="mt-4 font-bold">{{ $title }}</p>
<p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $desc }}</p>
</div>
@endforeach
</div>
</div>
</section>

{{-- ===== FINAL CTA — fajar ===== --}}
<section class="bp-topo-dark relative isolate overflow-hidden text-white">
<div class="absolute inset-0 bg-gradient-to-b from-[#0e1a30] via-[#1d2b47] to-[#8a5a26]"></div>
<div class="pointer-events-none absolute inset-x-0 bottom-0 h-56" style="background:radial-gradient(60% 90% at 50% 108%, rgba(251,191,36,.5), transparent 70%)"></div>
<div class="relative mx-auto max-w-4xl px-5 py-24 text-center">
<h2 class="text-3xl font-black tracking-tight sm:text-4xl">Satu sistem untuk mengendalikan proyek dari tender hingga handover.</h2>
<p class="mx-auto mt-4 max-w-xl text-sky-100/90">Jelajahi modul, dokumentasi, dan alurnya — lalu masuk dengan akun Anda.</p>
<div class="mt-9 flex flex-wrap justify-center gap-3">
<a href="/login" class="rounded-xl bg-sky-700 px-7 py-3.5 text-sm font-bold shadow-lg ring-1 ring-sky-400/40 transition hover:-translate-y-px hover:bg-sky-600">Masuk ke Sistem</a>
<a href="/docs" class="rounded-xl border border-white/25 bg-white/5 px-7 py-3.5 text-sm font-bold backdrop-blur transition hover:bg-white/15">Lihat Dokumentasi</a>
</div>
</div>
</section>

{{-- ===== DEPTH GAUGE (scroll = kedalaman) ===== --}}
<div id="depth-gauge" aria-hidden="true">
<span class="dg-read">EL 00.0 M</span>
<div class="dg-track"><div class="dg-fill"></div></div>
</div>
<script>
(() => {
    const g = document.getElementById('depth-gauge');
    if (!g) return;
    const fill = g.querySelector('.dg-fill'), read = g.querySelector('.dg-read');
    let ticking = false;
    const upd = () => {
        ticking = false;
        const max = document.documentElement.scrollHeight - window.innerHeight;
        const p = max > 0 ? Math.min(1, Math.max(0, window.scrollY / max)) : 0;
        fill.style.height = (p * 100).toFixed(1) + '%';
        read.textContent = 'EL -' + (p * 42).toFixed(1).padStart(4, '0') + ' M';
        g.classList.toggle('dg-on', window.scrollY > 80);
    };
    window.addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(upd); } }, { passive: true });
    upd();
})();
</script>
</x-layouts.public>
