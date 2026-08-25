<x-layouts.app title="Keluhan Pelanggan">
<div class="page-container">
<x-ui.page-header title="Keluhan Pelanggan" subtitle="ISO 9001 clause 9.1.2 — umpan balik dan keluhan pelanggan tercatat, ditindaklanjuti, dan diselesaikan. Keluhan major dapat ditautkan ke NCR untuk investigasi formal." />
@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
<x-ui.stat-card label="Total Keluhan" value="{{ number_format($stats['total']) }}" icon="bell" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Belum Selesai" value="{{ number_format($stats['open']) }}" icon="clock" tone="{{ $stats['open'] > 0 ? 'warning' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Major" value="{{ number_format($stats['major']) }}" icon="triangle-alert" tone="{{ $stats['major'] > 0 ? 'danger' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Selesai" value="{{ number_format($stats['resolved']) }}" icon="check" tone="success" :value-class="'text-[24px] leading-tight'" />
</div>

<button type="button" class="btn-brand mt-4 inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="complaint-drawer" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />Catat Keluhan</button>

<div class="mt-8 space-y-3">@forelse($complaints as $complaint)
<x-ui.card><div class="flex flex-wrap items-start justify-between gap-3"><div><strong class="font-mono">{{ $complaint->number }}</strong> — {{ $complaint->subject }}<p class="text-sm text-slate-500">{{ $complaint->customer?->name }}@if($complaint->project) · {{ $complaint->project?->code }}@endif · {{ ucfirst($complaint->channel) }} · {{ $complaint->complaint_date->format('d/m/Y') }}</p><p class="mt-2 text-sm">{{ \Illuminate\Support\Str::limit($complaint->description, 180) }}</p>@if($complaint->ncr_id)<p class="mt-1 text-xs font-bold text-violet-700">Tertaut NCR #{{ $complaint->ncr_id }}</p>@endif @if($complaint->status === 'resolved')<p class="mt-1 text-xs text-emerald-700">Selesai {{ $complaint->resolved_at?->format('d/m/Y') }}: {{ \Illuminate\Support\Str::limit($complaint->resolution_notes, 120) }}</p>@endif</div>
<div class="flex flex-col items-end gap-2"><span class="chip chip-default @if($complaint->severity === 'major') bg-red-50 text-red-700 @endif">{{ strtoupper($complaint->severity) }}</span><span class="chip chip-default">{{ strtoupper($complaint->status) }}</span>
@if($complaint->status !== 'resolved' && auth()->user()->hasPermission('qms.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<form method="post" action="/admin/complaints/{{ $complaint->id }}/resolve" class="flex gap-1">@csrf<input name="resolution_notes" required placeholder="Resolusi" class="w-40 rounded-lg border p-1.5 text-xs"><button class="rounded-lg bg-emerald-700 px-2.5 py-1.5 text-xs font-bold text-white">Tutup</button></form>
@endif
</div></div></x-ui.card>
@empty<div class="rounded-2xl border border-dashed p-8 text-center"><h3 class="font-bold">Belum ada keluhan</h3><p class="mt-1 text-sm text-slate-500">Rekam setiap keluhan agar tren mutu layanan terukur.</p></div>@endforelse</div>
</div>

@if(auth()->user()->hasPermission('qms.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<x-ui.drawer id="complaint-drawer" title="Catat Keluhan Pelanggan" description="Keluhan major yang berdampak mutu sebaiknya diikuti pembuatan NCR (menu Risiko, NCR & Audit Mutu).">
<form method="post" action="/admin/complaints" class="grid gap-4">@csrf
<x-ui.field label="Pelanggan" name="customer_id" required><select name="customer_id" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih pelanggan</option>@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Proyek (opsional)" name="project_id"><select name="project_id" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">—</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->code }}</option>@endforeach</select></x-ui.field>
<div class="grid grid-cols-3 gap-3">
<x-ui.field label="Tanggal" name="complaint_date"><input type="date" name="complaint_date" value="{{ today()->toDateString() }}" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Kanal" name="channel"><select name="channel" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="email">Email</option><option value="phone">Telepon</option><option value="visit">Kunjungan</option><option value="other">Lainnya</option></select></x-ui.field>
<x-ui.field label="Severity" name="severity"><select name="severity" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="minor">Minor</option><option value="major">Major</option></select></x-ui.field>
</div>
<x-ui.field label="Subjek" name="subject" required><input name="subject" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Uraian keluhan" name="description" required><textarea name="description" required rows="3" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan</button></div>
</form>
</x-ui.drawer>
@endif
</x-layouts.app>
