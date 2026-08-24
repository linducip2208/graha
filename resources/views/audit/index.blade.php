<x-layouts.app title="Audit Trail"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-2xl font-bold tracking-tight">Audit Trail</h1>
<p class="mt-2 text-slate-500">Catatan append-only dengan hash chain — setiap entri terikat ke entri sebelumnya dan tidak dapat diubah atau dihapus.</p>

@if($eventSummary->isNotEmpty())
<div class="mt-6 flex flex-wrap gap-2 no-print">@foreach($eventSummary as $event => $total)<span class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600">{{ str($event)->replace('.', ' · ') }} <span class="ml-1 rounded bg-white px-1.5 font-mono">{{ $total }}</span></span>@endforeach</div>
@endif

<form method="get" class="mt-6 grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-5 no-print">
<input name="event" value="{{ $filters['event'] ?? '' }}" placeholder="Filter event (mis. billing)" class="rounded-xl border p-3">
<select name="actor_id" class="rounded-xl border p-3"><option value="">Semua aktor</option>@foreach($actors as $actor)<option value="{{ $actor->id }}" @selected(($filters['actor_id'] ?? '') == $actor->id)>{{ $actor->name }}</option>@endforeach</select>
<input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-xl border p-3">
<input type="date" name="until" value="{{ $filters['until'] ?? '' }}" class="rounded-xl border p-3">
<button class="rounded-xl bg-slate-900 p-3 font-bold text-white">Terapkan filter</button>
</form>

<div class="mt-6 overflow-x-auto rounded-2xl border bg-white">
<table class="w-full min-w-[900px] text-sm table-sticky">
<thead><tr><th>Waktu</th><th>Aktor</th><th>Event</th><th>Objek</th><th>Hash</th><th>Detail</th></tr></thead>
<tbody>@forelse($logs as $log)
<tr class="align-top hover:bg-slate-50">
<td class="whitespace-nowrap text-xs">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
<td>{{ $log->actor?->name ?? '—' }}</td>
<td><code class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px]">{{ $log->event }}</code></td>
<td class="text-xs">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
<td><span class="font-mono text-[10px] text-slate-400" title="{{ $log->entry_hash }}">{{ substr((string) $log->entry_hash, 0, 10) }}…</span></td>
<td class="max-w-md"><details><summary class="cursor-pointer text-xs font-bold text-[var(--brand-primary)]">Lihat data</summary><pre class="mt-1 max-h-48 overflow-auto whitespace-pre-wrap break-all rounded-lg bg-slate-50 p-2 text-[11px]">{{ json_encode($log->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details></td>
</tr>
@empty<tr><td colspan="6" class="p-8 text-center text-slate-500">Belum ada aktivitas tercatat untuk filter ini.</td></tr>@endforelse
</tbody>
</table>
</div>
{{ $logs->links() }}
</section></x-layouts.app>
