<x-layouts.app title="Administrasi Kontrak">
<div class="page-container">
<x-ui.page-header title="Administrasi Kontrak" subtitle="Milestone progres dengan bobot (total ≤ 100%) dan register asuransi per kontrak. Status polis dihitung otomatis: aktif, jatuh tempo ≤ 30 hari, atau kedaluwarsa.">
<x-slot:actions>
@if($selected)<a href="#milestone-form" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm"><x-ui.icon name="plus" class="h-4 w-4" />Tambah Milestone</a>@endif
</x-slot:actions>
</x-ui.page-header>
@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-5">
<x-ui.stat-card label="Kontrak" value="{{ number_format($stats['contracts']) }}" icon="flag" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Milestone" value="{{ number_format($stats['milestones']) }}" icon="grid" tone="info" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Tercapai" value="{{ number_format($stats['achieved']) }}@if($progress !== null) · {{ round((float)$progress * 100, 1) }}% bobot@endif" icon="check" tone="success" :value-class="'text-[16px] leading-tight'" />
<x-ui.stat-card label="Terlambat" value="{{ number_format($stats['late']) }}" icon="clock" tone="{{ $stats['late'] > 0 ? 'danger' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Asuransi Bermasalah" value="{{ number_format($stats['insuranceExpiring']) }}" icon="shield" tone="{{ $stats['insuranceExpiring'] > 0 ? 'warning' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
</div>

<section class="mt-8 rounded-2xl border bg-white p-5"><h2 class="text-lg font-black">Pilih Kontrak</h2><div class="mt-3 flex flex-wrap gap-2">@forelse($awards as $award)<a href="/admin/contract-admin?award_id={{ $award->id }}" class="rounded-xl border px-3 py-2 text-xs font-bold {{ $selected?->id === $award->id ? 'border-[var(--brand-primary)] bg-emerald-50 text-[var(--brand-primary)]' : '' }}">{{ $award->award_number }} · Rp {{ number_format((float)$award->contract_value / 1000000, 0) }} jt</a>@empty<span class="text-sm text-slate-500">Belum ada kontrak (award). Kontrak terbentuk dari tender menang.</span>@endforelse</div></section>

@if($selected)
<section id="milestone-form" class="mt-8 grid gap-6 lg:grid-cols-2">
<div class="rounded-2xl border bg-white p-5">
<h2 class="font-bold">Milestone — {{ $selected->award_number }}</h2>
<p class="mt-1 text-sm text-slate-500">Bobot terpakai: <strong>{{ $weightUsed }}%</strong> dari 100%.</p>
<form method="post" action="/admin/contract-admin/{{ $selected->id }}/milestones" class="mt-3 grid gap-2 md:grid-cols-2">@csrf
<input name="name" required placeholder="Nama milestone" class="rounded-xl border p-2.5 text-sm md:col-span-2">
<input type="date" name="planned_date" class="rounded-xl border p-2.5 text-sm">
<input type="number" step=".001" min=".001" max="100" name="weight_percent" required placeholder="Bobot %" class="rounded-xl border p-2.5 text-sm">
<input type="number" step=".01" min="0" name="amount" value="0" placeholder="Nilai (Rp)" class="rounded-xl border p-2.5 text-sm">
<button class="rounded-xl bg-[var(--brand-primary)] p-2.5 text-sm font-bold text-white md:col-span-2">Simpan Milestone</button>
</form>
<div class="mt-4 overflow-x-auto"><table class="w-full min-w-[520px] text-sm"><thead><tr><th>Milestone</th><th>Rencana</th><th>Realisasi</th><th>Bobot</th><th>Status</th></tr></thead><tbody>
@forelse($milestones as $m)
<tr class="h-[48px] border-t"><td>{{ $m->name }}</td><td>{{ $m->planned_date?->format('d/m/Y') ?? '—' }}</td><td>{{ $m->actual_date?->format('d/m/Y') ?? '—' }}</td><td>{{ $m->weight_percent }}%</td><td>@if($m->status === 'achieved')<span class="chip chip-default bg-emerald-50 text-emerald-700">TERCAPAI</span>@elseif($m->isLate())<span class="chip chip-default bg-red-50 text-red-700">TERLAMBAT</span>@else<span class="chip chip-default">PENDING</span>@endif
@if($m->status === 'pending' && auth()->user()->hasPermission('contract.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))<form method="post" action="/admin/contract-admin/milestones/{{ $m->id }}/achieve" class="mt-1 flex gap-1">@csrf<input type="date" name="actual_date" value="{{ today()->toDateString() }}" required class="rounded-lg border p-1 text-xs"><button class="rounded-lg bg-emerald-700 px-2 py-1 text-xs font-bold text-white">Tandai</button></form>@endif</td></tr>
@empty<tr><td colspan="5" class="p-6 text-center text-slate-500">Belum ada milestone.</td></tr>@endforelse
</tbody></table></div>
</div>

<div class="rounded-2xl border bg-white p-5">
<h2 class="font-bold">Asuransi — {{ $selected->award_number }}</h2>
<form method="post" action="/admin/contract-admin/{{ $selected->id }}/insurances" class="mt-3 grid gap-2 md:grid-cols-2">@csrf
<input name="policy_number" required placeholder="Nomor polis" class="rounded-xl border p-2.5 text-sm">
<input name="provider" required placeholder="Perusahaan asuransi" class="rounded-xl border p-2.5 text-sm">
<select name="coverage_type" class="rounded-xl border p-2.5 text-sm"><option value="car">CAR (Kontruksi)</option><option value="ear">EAR (Ereksi)</option><option value="tpl">TPL (Liability)</option><option value="surety">Surety Bond</option><option value="other">Lainnya</option></select>
<input type="number" step=".01" min=".01" name="insured_amount" required placeholder="Nilai pertanggungan" class="rounded-xl border p-2.5 text-sm">
<input type="number" step=".01" min="0" name="premium" value="0" placeholder="Premi" class="rounded-xl border p-2.5 text-sm">
<div class="grid grid-cols-2 gap-2 md:col-span-2"><input type="date" name="start_date" required class="rounded-xl border p-2.5 text-sm"><input type="date" name="end_date" required class="rounded-xl border p-2.5 text-sm"></div>
<button class="rounded-xl bg-violet-700 p-2.5 text-sm font-bold text-white md:col-span-2">Simpan Polis</button>
</form>
<div class="mt-4 overflow-x-auto"><table class="w-full min-w-[520px] text-sm"><thead><tr><th>Polis</th><th>Jenis</th><th>Pertanggungan</th><th>Berlaku</th><th>Status</th></tr></thead><tbody>
@forelse($insurances as $i)
@php($st = $i->statusNow())
<tr class="h-[48px] border-t"><td class="font-mono">{{ $i->policy_number }}</td><td>{{ strtoupper($i->coverage_type) }}</td><td>Rp {{ number_format((float)$i->insured_amount / 1000000, 0) }} jt</td><td>{{ $i->start_date->format('d/m/y') }}–{{ $i->end_date->format('d/m/y') }}</td><td><span class="chip chip-default @if($st==='expired') bg-red-50 text-red-700 @elseif($st==='expiring') bg-amber-50 text-amber-700 @endif">{{ strtoupper($st) }}</span></td></tr>
@empty<tr><td colspan="5" class="p-6 text-center text-slate-500">Belum ada polis terdaftar.</td></tr>@endforelse
</tbody></table></div>
</div>
</section>
@endif
</div>
</x-layouts.app>
