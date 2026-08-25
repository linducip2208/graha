<x-layouts.app title="Tender {{ $tender->number }}">
@php($tabs = ['overview' => 'Ringkasan', 'estimate' => 'Estimasi & Margin', 'decision' => 'Bid / No-Bid', 'participants' => 'Peserta', 'outcome' => 'Outcome', 'lessons' => 'Lessons Learned'])
<section class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
<p class="text-xs font-bold uppercase tracking-widest text-[var(--brand-primary)]">{{ $tender->number }} · {{ $tender->year }}</p>
<x-ui.page-header title="{{ $tender->project_name }}" subtitle="Pelanggan: {{ $tender->customer?->name }}@if($tender->location) · {{ $tender->location }}@endif@if($tender->bid_value) · Bid Rp {{ number_format((float) $tender->bid_value, 0, ',', '.') }}@endif" status="{{ str_replace('_',' ', $tender->status) }}">
@if($project)<a href="/admin/projects/{{ $project->id }}" class="font-semibold text-[var(--brand-primary)] hover:underline">Proyek terkait →</a>@endif
</x-ui.page-header>

<x-ui.tabs :tabs="$tabs" :active="$activeTab" class="mt-6" />

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

@if($activeTab === 'decision')
<section class="mt-6 space-y-5">
@php($decision = $tender->bid_decision_json)
<x-ui.card bodyClass="p-6">
<div class="flex flex-wrap items-center justify-between gap-3">
<div>
<h2 class="font-bold">Keputusan Bid / No-Bid</h2>
<p class="text-sm text-slate-500">Skor dari faktor data nyata; bobot & ambang diatur perusahaan di Pengaturan. Faktor tanpa data tidak dikarang — hasilnya Perlu Review.</p>
</div>
<form method="post" action="/admin/tenders/{{ $tender->id }}/bid-decision" class="no-print">@csrf
<button @disabled(!in_array($tender->status, ['preparation','bidding'])) class="rounded-xl bg-[var(--brand-primary)] px-5 py-2.5 text-sm font-bold text-white disabled:opacity-40">{{ $decision ? 'Evaluasi ulang' : 'Jalankan evaluasi' }}</button>
</form>
</div>
@guest
<p class="mt-4 rounded-xl border border-dashed p-4 text-sm text-slate-500">Belum dievaluasi.</p>
@endguest
@if($decision)
<div class="mt-4 grid gap-4 lg:grid-cols-[220px_1fr]">
<div class="rounded-xl border p-4 text-center {{ in_array($tender->status, ['won','lost']) ? 'opacity-60' : '' }}">
<p class="text-xs uppercase tracking-wide text-slate-500">Skor</p>
<p class="text-3xl font-bold tracking-tight">{{ $decision['score'] }}</p>
@php($rec = match($decision['recommendation']) { 'recommended_bid' => ['BID', 'bg-emerald-50 text-emerald-700 border-emerald-300'], 'recommended_no_bid' => ['NO-BID', 'bg-red-50 text-red-700 border-red-300'], default => ['PERLU REVIEW', 'bg-amber-50 text-amber-700 border-amber-300'] })
<span class="mt-1 inline-block rounded-full border px-3 py-1 text-xs font-black {{ $rec[1] }}">{{ $rec[0] }}</span>
<p class="mt-2 text-[11px] text-slate-400">Ambang: bid ≥ {{ $decision['thresholds']['bid'] }} · no-bid < {{ $decision['thresholds']['no_bid'] }}<br>Evaluasi terakhir {{ \Carbon\Carbon::parse($decision['evaluated_at'])->format('d/m/Y H:i') }}</p>
</div>
<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th>Faktor</th><th>Data</th><th class="text-right">Skor</th><th class="text-right">Bobot efektif</th></tr></thead><tbody>
@foreach($decision['factors'] as $f)
<tr class="border-t"><td>{{ $f['label'] }}</td><td>{{ $f['display'] }}</td><td class="text-right font-mono">{{ $f['score'] }}</td><td class="text-right">{{ $f['weight_share'] }}%</td></tr>
@endforeach
</tbody></table></div>
</div>
@if(!empty($decision['reasons']))
<div class="mt-3 rounded-xl bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800"><strong>Catatan:</strong><ul class="list-disc pl-5">@foreach($decision['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul></div>
@endif
@endif
</x-ui.card>
</section>
@endif
@if($activeTab === 'outcome')
<section class="mt-6">
@if($tender->outcome)
<div class="grid gap-5 sm:grid-cols-2">
<x-ui.card bodyClass="p-6"><h2 class="font-bold">Hasil</h2><dl class="mt-3 space-y-2 text-sm"><div class="flex justify-between"><dt>Status</dt><dd class="font-black uppercase {{ $tender->outcome->outcome === 'won' ? 'text-emerald-700' : 'text-red-600' }}">{{ $tender->outcome->outcome === 'won' ? '🏆 MENANG' : 'KALAH' }}</dd></div><div class="flex justify-between"><dt>Diumumkan</dt><dd>{{ $tender->outcome->announced_at?->format('d/m/Y') }}</dd></div>@if($tender->outcome->contract_value)<div class="flex justify-between"><dt>Nilai Kontrak</dt><dd class="font-mono">Rp {{ number_format((float) $tender->outcome->contract_value, 0, ',', '.') }}</dd></div>@endif @if($tender->outcome->winner_name)<div class="flex justify-between"><dt>Pemenang</dt><dd>{{ $tender->outcome->winner_name }}</dd></div>@endif @if($tender->outcome->winning_bid_value)<div class="flex justify-between"><dt>Bid Pemenang</dt><dd class="font-mono">Rp {{ number_format((float) $tender->outcome->winning_bid_value, 0, ',', '.') }}</dd></div>@endif</dl></x-ui.card>
<x-ui.card bodyClass="p-6"><h2 class="font-bold">Analisis</h2><dl class="mt-3 space-y-2 text-sm">@if($tender->outcome->primary_reason)<div><dt class="font-semibold">Alasan Utama</dt><dd class="text-slate-600">{{ $tender->outcome->primary_reason }}</dd></div>@endif @if(! empty($tender->outcome->additional_reasons))<div><dt class="font-semibold">Alasan Tambahan</dt><dd class="text-slate-600">{{ implode('; ', $tender->outcome->additional_reasons) }}</dd></div>@endif</dl></x-ui.card>
</div>
@else
<x-ui.empty icon="flag" title="Hasil tender belum dicatat" description="Catat outcome di halaman tender utama setelah pengumuman." />
@endif
</section>
@endif

@if($activeTab === 'lessons')
<section class="mt-6">
@if($tender->outcome?->lesson_learned)
<x-ui.card bodyClass="p-6"><h2 class="font-bold">Lesson Learned</h2><p class="mt-2 whitespace-pre-line text-sm leading-relaxed">{{ $tender->outcome->lesson_learned }}</p></x-ui.card>
@else
<x-ui.empty icon="document" title="Belum ada lesson learned" description="Dokumentasikan pembelajaran agar bisa jadi referensi penawaran berikutnya." />
@endif
</section>
@endif

<a href="/admin/tenders" class="mt-8 inline-block text-sm font-bold text-[var(--brand-primary)] print:hidden">← Kembali ke daftar tender</a>
</section>
</x-layouts.app>
