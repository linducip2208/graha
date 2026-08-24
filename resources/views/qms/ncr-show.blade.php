<x-layouts.app title="NCR {{ $ncr->number }}"><div class="page-container">
@php($ncrSubtitle = ucfirst($ncr->source_type).' · Severitas '.ucfirst($ncr->severity).($ncr->project ? ' · '.$ncr->project->code.' — '.$ncr->project->name : '').($ncr->due_at ? ' · Tenggat '.(is_string($ncr->due_at) ? $ncr->due_at : $ncr->due_at->format('d/m/Y')) : ''))
<x-ui.page-header title="NCR {{ $ncr->number }}" :subtitle="$ncrSubtitle" status="{{ str_replace('_',' ', $ncr->status) }}">
<a href="/admin/qms" class="x-ui.button secondary">← Kembali ke QMS</a>
</x-ui.page-header>

<x-ui.card label="Deskripsi Ketidaksesuaian" class="mt-6">
<p class="text-sm leading-relaxed">{{ $ncr->description }}</p>
@if($ncr->containment)<p class="mt-3 text-sm"><strong>Containment:</strong> {{ $ncr->containment }}</p>@endif
@if($ncr->root_cause)<p class="mt-2 text-sm"><strong>Akar masalah:</strong> {{ $ncr->root_cause }}</p>@endif
</x-ui.card>

<x-ui.card label="CAPA — Tindakan Korektif & Preventif ({{ $ncr->actions->count() }})" class="mt-5">
<div class="space-y-3">@forelse($ncr->actions as $action)
<div class="rounded-xl border p-4 text-sm">
<div class="flex flex-wrap items-center justify-between gap-2">
<strong>{{ \Illuminate\Support\Str::limit($action->action, 80) }}</strong>
<span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $action->status === 'effective' ? 'bg-emerald-50 text-emerald-700' : ($action->status === 'open' ? 'bg-red-50 text-red-700' : 'bg-sky-50 text-[var(--brand-primary)]') }}">{{ strtoupper($action->status) }}</span>
</div>
<p class="mt-1 text-xs text-slate-500">PIC {{ $action->owner?->name }} · tenggat {{ $action->due_at?->format('d/m/Y') }}@if($action->verified_at) · diverifikasi {{ $action->verified_at->format('d/m/Y') }} oleh {{ $action->verifier?->name }}@endif</p>
@if($action->effectiveness_notes)<p class="mt-1 text-xs text-emerald-700">Efektivitas: {{ $action->effectiveness_notes }}</p>@endif
@if($action->status !== 'effective')
<form method="post" action="/admin/qms/actions/{{ $action->id }}/verify" class="mt-2 flex flex-wrap gap-2 no-print">@csrf
<input name="notes" required placeholder="Catatan verifikasi efektivitas" class="min-w-56 flex-1 rounded-lg border p-1.5 text-xs">
<button class="font-bold text-emerald-700">Verifikasi efektif</button>
</form>
@endif
</div>
@empty<p class="text-sm text-slate-400">Belum ada CAPA — tambahkan dari daftar NCR di halaman QMS.</p>@endforelse
</div>
</x-ui.card>
</div></x-layouts.app>
