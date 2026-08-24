<x-layouts.app title="My Work">
<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<p class="text-xs font-bold uppercase tracking-widest text-[var(--brand-primary)]">{{ $company?->code }}</p>
<h1 class="mt-1 text-2xl font-bold tracking-tight">Pekerjaan Saya</h1>
<p class="mt-2 text-slate-500">Seluruh penugasan dan dokumen yang menunggu tindakan Anda lintas modul — diurutkan berdasarkan tenggat.</p>

@php($totalTasks = $toDecide->count() + $capaActions->count() + $hseActions->count() + $signatures->count())

<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
<x-ui.stat-card label="Menunggu Keputusan Saya" :value="$toDecide->count()" :hint="$toDecide->where('due_at', '<', now())->count().' melewati SLA'" />
<x-ui.stat-card label="CAPA Ditugaskan" :value="$capaActions->count()" hint="Tindakan korektif atas NCR" />
<x-ui.stat-card label="Aksi HSE Ditugaskan" :value="$hseActions->count()" hint="Tindak lanjut incident" />
<x-ui.stat-card label="Tanda Tangan Menunggu" :value="$signatures->count()" hint="Dokumen untuk saya tandatangani" />
</div>

<div class="mt-8 grid gap-5 lg:grid-cols-2">

<x-ui.card bodyClass="p-6">
<div class="flex items-center justify-between"><h2 class="font-bold">Approval Menunggu Keputusan Saya</h2><a href="/admin/approvals" class="text-xs font-bold text-[var(--brand-primary)]">Approval Center →</a></div>
<div class="mt-3 space-y-2">@forelse($toDecide as $approval)
@php($label = method_exists($approval->approvable ?? null, 'workLabel') ? $approval->approvable->workLabel() : (class_basename($approval->approvable_type).' #'.$approval->approvable_id))
<a href="/admin/approvals" class="flex items-center justify-between gap-3 rounded-xl border p-3 text-sm hover:border-[var(--brand-primary)]"><span class="min-w-0"><strong>{{ $approval->workflow?->document_type ? ucfirst($approval->workflow->document_type) : 'Dokumen' }}</strong> · {{ $label }}<span class="block text-xs text-slate-500">Tahap {{ $approval->current_sequence }} @if($approval->due_at)· tenggat {{ $approval->due_at->format('d/m H:i') }}@endif</span></span>@if($approval->due_at && $approval->due_at->isPast())<x-ui.badge status="exception" label="overdue" />@else<x-ui.badge status="pending_approval" label="pending" />@endif</a>
@empty<x-ui.empty icon="check" title="Tidak ada approval menunggu" description="Semua dokumen dalam batas SLA atau bukan kewenangan Anda." /></@endforelse</div>
</x-ui.card>

<x-ui.card bodyClass="p-6">
<h2 class="font-bold">Pengajuan Saya yang Berjalan</h2>
<div class="mt-3 space-y-2">@forelse($mySubmissions as $submission)
@php($label = class_basename($submission->approvable_type).' #'.$submission->approvable_id)
<a href="/admin/approvals" class="flex items-center justify-between gap-3 rounded-xl border p-3 text-sm hover:border-[var(--brand-primary)]"><span class="min-w-0"><strong>{{ $submission->workflow?->document_type ? ucfirst($submission->workflow->document_type) : 'Dokumen' }}</strong> · {{ $label }}<span class="block text-xs text-slate-500">Diajukan {{ $submission->submitted_at?->format('d/m H:i') }}</span></span>@if($submission->due_at && $submission->due_at->isPast())<x-ui.badge status="exception" label="melewati SLA" />@else<x-ui.badge status="pending_approval" label="di review" />@endif</a>
@empty<x-ui.empty icon="check" title="Tidak ada pengajuan aktif" description="Anda tidak memiliki dokumen yang sedang menunggu persetujuan." /></@endforelse</div>
</x-ui.card>

@if($capaActions->isNotEmpty())
<x-ui.card bodyClass="p-6">
<h2 class="font-bold">CAPA Ditugaskan ke Saya</h2>
<div class="mt-3 space-y-2">@foreach($capaActions as $action)
<a href="/admin/qms" class="flex items-center justify-between gap-3 rounded-xl border p-3 text-sm hover:border-[var(--brand-primary)]"><span class="min-w-0">{{ str($action->action)->limit(80) }}<span class="block text-xs text-slate-500">NCR #{{ $action->nonconformity_id }} · tenggat {{ $action->due_at?->format('d/m/Y') }}</span></span>@if($action->due_at && $action->due_at->isPast())<x-ui.badge status="exception" label="lewat tenggat" />@else<x-ui.badge status="open" label="open" />@endif</a>
@endforeach</div>
</x-ui.card>
@endif

@if($hseActions->isNotEmpty())
<x-ui.card bodyClass="p-6">
<h2 class="font-bold">Aksi HSE Ditugaskan ke Saya</h2>
<div class="mt-3 space-y-2">@foreach($hseActions as $action)
<a href="/admin/hse" class="flex items-center justify-between gap-3 rounded-xl border p-3 text-sm hover:border-[var(--brand-primary)]"><span class="min-w-0">{{ str($action->action)->limit(80) }}<span class="block text-xs text-slate-500">Incident #{{ $action->hse_incident_id }} · tenggat {{ $action->due_at?->format('d/m/Y') }}</span></span>@if($action->due_at && $action->due_at->isPast())<x-ui.badge status="exception" label="lewat tenggat" />@else<x-ui.badge status="open" label="open" />@endif</a>
@endforeach</div>
</x-ui.card>
@endif

@if($materialRequests->isNotEmpty())
<article class="rounded-2xl border bg-white p-6 shadow-sm overflow-x-auto">
<div class="flex items-center justify-between"><h2 class="font-bold">Permintaan Material Aktif</h2><a href="/admin/inventory/material-requests" class="text-xs font-bold text-[var(--brand-primary)]">Inventory →</a></div>
<table class="mt-3 w-full text-sm"><thead><tr><th>Nomor</th><th>Proyek</th><th>Status</th></tr></thead><tbody>@foreach($materialRequests as $mr)
<tr class="cursor-pointer hover:bg-slate-50 dark:hover:!bg-slate-800" onclick="location.href='/admin/inventory/material-requests'"><td class="font-mono text-xs">{{ $mr->number }}</td><td>{{ $mr->project?->code }}</td><td>{{ strtoupper($mr->status) }}</td></tr>
@endforeach</tbody></table>
</article>
@endif

@if($signatures->isNotEmpty())
<x-ui.card bodyClass="p-6">
<div class="flex items-center justify-between"><h2 class="font-bold">Menunggu Tanda Tangan Saya</h2><a href="/admin/signatures" class="text-xs font-bold text-[var(--brand-primary)]">Signing →</a></div>
<div class="mt-3 space-y-2">@foreach($signatures as $signature)
<a href="/admin/signatures" class="flex items-center justify-between gap-3 rounded-xl border p-3 text-sm hover:border-[var(--brand-primary)]"><span class="min-w-0">{{ $signature->version?->document?->title ?? ('Versi #'.$signature->document_version_id) }}<span class="block text-xs text-slate-500">{{ ucfirst($signature->signature_type) }} · diminta {{ $signature->created_at->format('d/m') }}</span></span><x-ui.badge status="pending_approval" label="pending" /></a>
@endforeach</div>
</x-ui.card>
@endif

</div>
</section>
</x-layouts.app>
