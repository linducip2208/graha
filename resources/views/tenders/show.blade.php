<x-layouts.app title="Tender {{ $tender->number }}">
@php($tabs = ['overview' => 'Ringkasan', 'estimate' => 'Estimasi & Margin', 'participants' => 'Peserta', 'outcome' => 'Outcome', 'lessons' => 'Lessons Learned'])
<section class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
<p class="text-xs font-bold uppercase tracking-widest text-sky-700">{{ $tender->number }} · {{ $tender->year }}</p>
<h1 class="mt-1 text-3xl font-black">{{ $tender->project_name }}</h1>
<div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-slate-500">
<span class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold uppercase">{{ str_replace('_', ' ', $tender->status) }}</span>
<span>Pelanggan: <strong>{{ $tender->customer?->name }}</strong></span>
@if($tender->location)<span>Lokasi: {{ $tender->location }}</span>@endif
@if($tender->bid_value)<span>Bid: <strong class="font-mono">Rp {{ number_format((float) $tender->bid_value, 0, ',', '.') }}</strong></span>@endif
@if($project)<a href="/admin/projects/{{ $project->id }}" class="font-semibold text-sky-700 hover:underline">Proyek terkait →</a>@endif
</div>

<nav class="mt-6 flex gap-1 overflow-x-auto border-b no-print" aria-label="Tab tender">
@foreach($tabs as $key => $label)
<a href="/admin/tenders/{{ $tender->id }}?tab={{ $key }}" @class(['whitespace-nowrap rounded-t-xl px-4 py-2.5 text-sm font-semibold', 'bg-sky-700 text-white shadow-sm' => $activeTab === $key, 'text-slate-500 hover:bg-slate-100 hover:text-slate-800' => $activeTab !== $key])>{{ $label }}</a>
@endforeach
</nav>

@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800 print:hidden">{{ session('status') }}</div>@endif

@if($activeTab === 'overview')
<section class="mt-6 grid gap-5 sm:grid-cols-3">
<article class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Owner Estimate</p><p class="mt-1 text-xl font-black">Rp {{ number_format((float) ($tender->owner_estimate ?? 0), 0, ',', '.') }}</p></article>
<article class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Nilai Bid Kami</p><p class="mt-1 text-xl font-black">Rp {{ number_format((float) ($tender->bid_value ?? 0), 0, ',', '.') }}</p></article>
<article class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Estimasi Biaya</p><p class="mt-1 text-xl font-black">Rp {{ number_format((float) ($tender->estimated_cost ?? 0), 0, ',', '.') }}</p>@if($tender->bid_value && $tender->estimated_cost)@php($margin = round((1 - (float) $tender->estimated_cost / (float) $tender->bid_value) * 100, 1))<p class="text-xs {{ $margin < 5 ? 'text-red-600' : 'text-emerald-700' }}">Margin indikatif: {{ $margin }}%</p>@endif</article>
</section>
@endif

@if($activeTab === 'estimate')
<section class="mt-6 overflow-x-auto rounded-2xl border bg-white">
<table class="w-full text-sm table-sticky"><thead><tr><th>Deskripsi Item</th><th class="text-right">Volume</th><th>Satuan</th><th class="text-right">Harga Satuan</th><th class="text-right">Jumlah</th></tr></thead><tbody>
@forelse($tender->estimate?->items ?? [] as $item)
<tr><td>{{ $item->description }}</td><td class="text-right font-mono">{{ $item->quantity }}</td><td>{{ $item->unit }}</td><td class="text-right font-mono">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td><td class="text-right font-mono">{{ number_format((float) $item->total_price, 0, ',', '.') }}</td></tr>
@empty
<tr><td colspan="5" class="p-8 text-center text-slate-500">Belum ada rincian estimasi. Kelola di halaman tender utama.</td></tr>
@endforelse
</tbody></table>
</section>
@endif

@if($activeTab === 'participants')
<section class="mt-6 overflow-x-auto rounded-2xl border bg-white">
<table class="w-full text-sm table-sticky"><thead><tr><th>Peserta</th><th class="text-right">Bid</th><th>Rank</th><th>Pemenang</th></tr></thead><tbody>
@forelse($tender->participants as $participant)
<tr><td>{{ $participant->name }}</td><td class="text-right font-mono">{{ $participant->bid_value ? number_format((float) $participant->bid_value, 0, ',', '.') : '-' }}</td><td>{{ $participant->rank ?? '-' }}</td><td>@if($participant->is_winner)🏆 @endif{{ $participant->is_winner ? 'Ya' : '-' }}</td></tr>
@empty
<tr><td colspan="4" class="p-8 text-center text-slate-500">Belum ada peserta yang dicatat.</td></tr>
@endforelse
</tbody></table>
</section>
@endif

@if($activeTab === 'outcome')
<section class="mt-6">
@if($tender->outcome)
<div class="grid gap-5 sm:grid-cols-2">
<article class="rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">Hasil</h2><dl class="mt-3 space-y-2 text-sm"><div class="flex justify-between"><dt>Status</dt><dd class="font-black uppercase {{ $tender->outcome->outcome === 'won' ? 'text-emerald-700' : 'text-red-600' }}">{{ $tender->outcome->outcome === 'won' ? '🏆 MENANG' : 'KALAH' }}</dd></div><div class="flex justify-between"><dt>Diumumkan</dt><dd>{{ $tender->outcome->announced_at?->format('d/m/Y') }}</dd></div>@if($tender->outcome->contract_value)<div class="flex justify-between"><dt>Nilai Kontrak</dt><dd class="font-mono">Rp {{ number_format((float) $tender->outcome->contract_value, 0, ',', '.') }}</dd></div>@endif @if($tender->outcome->winner_name)<div class="flex justify-between"><dt>Pemenang</dt><dd>{{ $tender->outcome->winner_name }}</dd></div>@endif @if($tender->outcome->winning_bid_value)<div class="flex justify-between"><dt>Bid Pemenang</dt><dd class="font-mono">Rp {{ number_format((float) $tender->outcome->winning_bid_value, 0, ',', '.') }}</dd></div>@endif</dl></article>
<article class="rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">Analisis</h2><dl class="mt-3 space-y-2 text-sm">@if($tender->outcome->primary_reason)<div><dt class="font-semibold">Alasan Utama</dt><dd class="text-slate-600">{{ $tender->outcome->primary_reason }}</dd></div>@endif @if(! empty($tender->outcome->additional_reasons))<div><dt class="font-semibold">Alasan Tambahan</dt><dd class="text-slate-600">{{ implode('; ', $tender->outcome->additional_reasons) }}</dd></div>@endif</dl></article>
</div>
@else
<x-ui.empty icon="flag" title="Hasil tender belum dicatat" description="Catat outcome di halaman tender utama setelah pengumuman." />
@endif
</section>
@endif

@if($activeTab === 'lessons')
<section class="mt-6">
@if($tender->outcome?->lesson_learned)
<article class="rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">Lesson Learned</h2><p class="mt-2 whitespace-pre-line text-sm leading-relaxed">{{ $tender->outcome->lesson_learned }}</p></article>
@else
<x-ui.empty icon="document" title="Belum ada lesson learned" description="Dokumentasikan pembelajaran agar bisa jadi referensi penawaran berikutnya." />
@endif
</section>
@endif

<a href="/admin/tenders" class="mt-8 inline-block text-sm font-bold text-sky-700 print:hidden">← Kembali ke daftar tender</a>
</section>
</x-layouts.app>
