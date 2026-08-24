<x-layouts.app title="Kontrak — {{ $contract->number }}">
<section class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
<x-ui.page-header title="{{ $contract->number }} — {{ $contract->title }}" subtitle="{{ ($types = \App\Models\ContractChange::TYPES)[$contract->type] ?? $contract->type }} · Nilai Rp {{ number_format((float) $contract->amount, 0, ',', '.') }}@if($contract->days_extension > 0) · +{{ $contract->days_extension }} hari@endif@if($contract->effective_date) · Efektif {{ $contract->effective_date->format('d/m/Y') }}@endif" status="{{ str_replace('_',' ', $contract->status) }}">
@if($contract->project)<a href="/admin/projects/{{ $contract->project->id }}" class="font-semibold text-sky-700 hover:underline">{{ $contract->project->code }}</a>@endif
</x-ui.page-header>

@if($contract->description)
<article class="mt-6 rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">Uraian</h2><p class="mt-2 whitespace-pre-line text-sm leading-relaxed">{{ $contract->description }}</p></article>
@endif

<article class="mt-6 rounded-2xl border bg-white p-6 shadow-sm"><h2 class="font-bold">Riwayat Approval</h2>
<div class="mt-3 space-y-2">@forelse($contract->approvalRequests as $request)
<div class="flex items-center justify-between rounded-xl border p-3 text-sm"><div><strong>{{ $request->workflow?->name }}</strong><span class="block text-xs text-slate-500">Diajukan {{ $request->submitted_at?->format('d/m/Y H:i') }} oleh #{{ $request->submitted_by }} · tahap {{ $request->current_sequence }}</span></div><x-ui.badge :status="$request->status === 'approved' ? 'posted' : ($request->status === 'rejected' ? 'exception' : 'pending_approval')" :label="str_replace('_', ' ', $request->status)" /></div>
@empty<p class="text-sm text-slate-500">Belum pernah diajukan ke approval.</p>@endforelse</div>
</article>

@if($contract->status === 'draft')
<form method="post" action="/admin/contracts/{{ $contract->id }}/submit" id="submit-contract" class="mt-6 grid gap-3 rounded-2xl border bg-white p-6 no-print md:grid-cols-[1fr_240px_160px]">@csrf
<select name="workflow_id" required class="rounded-xl border p-3">@foreach($workflows as $workflow)<option value="{{ $workflow->id }}">{{ $workflow->name }}</option>@endforeach</select>
<input type="hidden" name="idempotency_key" value="cc-{{ $contract->id }}-{{ $contract->updated_at?->timestamp }}">
<button @if($workflows->isEmpty()) disabled title="Buat workflow contract_change dulu" @endif class="rounded-xl bg-sky-700 p-3 text-white">Ajukan ke Approval</button>
</form>
@endif
<a href="/admin/contracts" class="mt-8 inline-block text-sm font-bold text-sky-700">← Kembali ke daftar kontrak</a>
</section>
</x-layouts.app>
