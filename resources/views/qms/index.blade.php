<x-layouts.app title="Quality, HSE & ISO">
<div class="page-container">
@php($qmsTab = request('tab', 'overview'))
<x-ui.page-header title="Quality, HSE & ISO" subtitle="Dukungan implementasi QMS — bukan klaim sertifikasi ISO.">
<x-slot:actions>
@if($qmsTab === 'overview' && auth()->user()->hasPermission('qms.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="qms-ncr-drawer"><x-ui.icon name="plus" class="h-4 w-4" />Catat NCR</button>
@endif
@if($qmsTab === 'risk' && auth()->user()->hasPermission('qms.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="qms-risk-drawer"><x-ui.icon name="plus" class="h-4 w-4" />Risk / Peluang</button>
@endif
@if($qmsTab === 'audit' && auth()->user()->hasPermission('qms.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="qms-audit-drawer"><x-ui.icon name="plus" class="h-4 w-4" />Jadwalkan Audit</button>
@endif
@if($qmsTab === 'objectives' && auth()->user()->hasPermission('qms.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="qms-objective-drawer"><x-ui.icon name="plus" class="h-4 w-4" />Sasaran Mutu</button>
@endif
@if($qmsTab === 'survey' && auth()->user()->hasPermission('qms.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="qms-survey-drawer"><x-ui.icon name="plus" class="h-4 w-4" />Catat Survei</button>
@endif
</x-slot:actions>
</x-ui.page-header>

<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
<x-ui.stat-card label="Total Risiko Terdaftar" value="{{ number_format($risks->count()) }}" icon="search" tone="info" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="NCR Terbuka" value="{{ number_format($ncrs->whereIn('status', ['open', 'containment'])->count()) }}" icon="triangle-alert" tone="{{ $ncrs->whereIn('status', ['open', 'containment'])->isNotEmpty() ? 'danger' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Total NCR" value="{{ number_format($ncrs->count()) }}" icon="shield" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="NCR Ditutup" value="{{ number_format($ncrs->where('status', 'closed')->count()) }}" icon="check" tone="success" :value-class="'text-[24px] leading-tight'" />
</div>

@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<x-ui.tabs :tabs="['overview' => 'Overview NCR', 'risk' => 'Risk & Opportunity', 'audit' => 'Audit Internal', 'objectives' => 'Sasaran Mutu', 'survey' => 'Kepuasan Pelanggan']" :active="$qmsTab" class="mt-6" />

{{-- ===== TAB: OVERVIEW — NCR kanban/tabel + kartu CAPA ===== --}}
@if(in_array($qmsTab, ['overview']))
<div class="mt-6 flex gap-2 no-print"><a href="/admin/qms" @class(['rounded-xl border px-4 py-2 text-sm font-semibold', 'bg-[var(--brand-primary)] text-white' => !request('view'), 'bg-[var(--surface-card)] text-slate-600' => request('view')])>Tabel</a><a href="/admin/qms?view=kanban" @class(['rounded-xl border px-4 py-2 text-sm font-semibold', 'bg-[var(--brand-primary)] text-white' => request('view') === 'kanban', 'bg-[var(--surface-card)] text-slate-600' => request('view') !== 'kanban'])>Kanban NCR</a></div>
@if($kanban)<x-ui.kanban :columns="$kanban" class="mt-6" />@endif
@if(!request('view'))
<div class="mt-6 space-y-4">@forelse($ncrs as $ncr)<x-ui.card><a href="/admin/qms/ncrs/{{ $ncr->id }}" class="float-right text-xs font-bold text-[var(--brand-primary)] hover:underline no-print">Detail →</a><div class="flex flex-wrap justify-between gap-3"><strong>{{ $ncr->number }} · {{ strtoupper($ncr->severity) }}</strong><span>{{ strtoupper($ncr->status) }}</span></div><p class="mt-2">{{ $ncr->description }}</p><form method="post" action="/admin/qms/ncrs/{{ $ncr->id }}/actions" class="mt-4 grid gap-2 md:grid-cols-4">@csrf<input name="action" required placeholder="Corrective action" class="rounded-xl border p-3"><select name="owner_id" required class="rounded-xl border p-3"><option value="">PIC</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select><input type="date" name="due_at" required class="rounded-xl border p-3"><button class="rounded-xl bg-slate-800 p-3 text-white">Tambah CAPA</button></form>@foreach($ncr->actions as $action)<form method="post" action="/admin/qms/actions/{{ $action->id }}/verify" class="mt-3 grid gap-2 rounded-xl bg-slate-50 p-3 md:grid-cols-4">@csrf<div>{{ $action->action }}<br><small>{{ strtoupper($action->status) }}</small></div><input name="evidence" value="{{ $action->evidence }}" placeholder="Referensi evidence" class="rounded-xl border p-3"><input name="effectiveness_notes" required placeholder="Catatan efektivitas" class="rounded-xl border p-3"><button class="rounded-xl bg-emerald-700 p-3 text-white">Verifikasi independen</button></form>@endforeach</x-ui.card>@empty<p>Belum ada NCR.</p>@endforelse</div>
@endif
<div class="mt-12"><h2 id="timeline-ncr" class="scroll-mt-24 text-lg font-black">Timeline NCR & Tindakan</h2><p class="mt-1 text-sm text-slate-500">Kronologi ketidaksesuaian dan tindakan korektifnya dari yang terbaru.</p>
<ol class="mt-4 space-y-4 border-l-2 border-slate-200 pl-6">
@forelse($ncrs as $ncr)
<li class="relative">
<span class="absolute -left-[31px] top-1.5 grid h-5 w-5 place-items-center rounded-full {{ $ncr->status === 'closed' ? 'bg-emerald-500' : ($ncr->severity === 'critical' ? 'bg-red-500' : 'bg-amber-500') }} text-[9px] font-black text-white">{{ $ncr->status === 'closed' ? '✓' : '!' }}</span>
<article class="rounded-xl border bg-white p-4"><div class="flex flex-wrap items-center justify-between gap-2 text-sm"><strong>{{ $ncr->number }}</strong><span class="text-xs uppercase text-slate-500">{{ $ncr->source_type }} · severitas {{ $ncr->severity }}</span></div>
<p class="mt-1 text-sm text-slate-600">{{ str($ncr->description)->limit(140) }}</p>
@if($ncr->actions->isNotEmpty())<ul class="mt-2 space-y-1 border-t pt-2 text-xs text-slate-500">@foreach($ncr->actions as $action)<li>• <strong>{{ $action->status }}</strong> · {{ str($action->action)->limit(90) }} @if($action->due_at)· tenggat {{ $action->due_at->format('d/m/Y') }}@endif</li>@endforeach</ul>@endif
</article>
</li>
@empty
<li class="text-sm text-slate-500">Belum ada NCR tercatat — kualitas dalam kendali.</li>
@endforelse
</ol></div>
@endif

{{-- ===== TAB: RISK ===== --}}
@if($qmsTab === 'risk')
<div class="mt-6 overflow-x-auto rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]"><table class="w-full text-sm"><thead><tr><th>Kode</th><th>Jenis</th><th>Judul</th><th>Skor</th><th>Status</th></tr></thead><tbody>@forelse($risks as $risk)<tr class="h-[52px]"><td class="font-mono font-bold">{{ $risk->code }}</td><td><span class="chip chip-default">{{ strtoupper($risk->type) }}</span></td><td>{{ $risk->title }}</td><td class="tabular-nums font-bold">{{ $risk->inherent_score }}</td><td><span class="chip chip-draft">{{ $risk->status }}</span></td></tr>@empty<tr><td colspan="5" class="p-2"><x-ui.empty icon="search" title="Belum ada risiko/peluang" description="Catat risiko atau peluang beserta likelihood & impact untuk dihitung skornya." /></td></tr>@endforelse</tbody></table></div>
@endif

{{-- ===== TAB: AUDIT ===== --}}
@if($qmsTab === 'audit')
<div class="mt-6 overflow-x-auto rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]"><table class="w-full text-sm"><thead><tr><th>Nomor Audit</th><th>Ruang Lingkup</th><th>Jadwal</th><th>Status</th></tr></thead><tbody>@forelse($audits as $audit)<tr class="h-[52px]"><td class="font-mono font-bold">{{ $audit->number }}</td><td>{{ $audit->scope }}</td><td>{{ $audit->scheduled_at }}</td><td><span class="chip chip-draft">{{ strtoupper($audit->status) }}</span></td></tr>@empty<tr><td colspan="4" class="p-2"><x-ui.empty icon="document" title="Belum ada audit" description="Jadwalkan audit internal: auditor ≠ auditee demi independensi." /></td></tr>@endforelse</tbody></table></div>
@endif

{{-- ===== TAB: OBJECTIVES ===== --}}
@if($qmsTab === 'objectives')
<div class="mt-6 overflow-x-auto rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]"><table class="w-full min-w-[700px] text-sm table-sticky"><thead><tr><th>Sasaran</th><th>KPI</th><th class="text-right">Target</th><th class="text-right">Realisasi</th><th class="text-right">Capaian</th><th>Update realisasi</th></tr></thead><tbody>
@if($objectives->isEmpty())
<tr><td colspan="6" class="p-2"><x-ui.empty icon="chart" title="Belum ada sasaran mutu" description="Tetapkan sasaran mutu terukur beserta KPI dan targetnya." /></td></tr>
@endif
@foreach($objectives as $objective)
@php(
    $achievement = ($objective->target_value && $objective->actual_value !== null && (float) $objective->target_value != 0) ? round((float) $objective->actual_value / (float) $objective->target_value * 100, 1) : null
)
@php(
    $achClass = ($achievement !== null && $achievement >= 100) ? 'font-bold text-emerald-600' : ''
)
<tr class="h-[52px]">
<td>{{ $objective->title }}<span class="block text-xs text-slate-400">{{ $objective->due_date?->format('d/m/Y') }}</span></td>
<td>{{ $objective->kpi_metric ?? '-' }}</td>
<td class="text-right font-mono">{{ $objective->target_value ?? '-' }}</td>
<td class="text-right font-mono">{{ $objective->actual_value ?? '-' }}</td>
<td class="text-right {{ $achClass }}">{{ $achievement === null ? '-' : $achievement.'%' }}</td>
<td><form method="post" action="/admin/qms/objectives/{{ $objective->id }}/actual" class="flex gap-1">@csrf<input type="number" step=".01" name="actual_value" required class="w-24 rounded border p-1 text-xs"><button class="font-bold text-[var(--brand-primary)] text-xs">Update</button></form></td>
</tr>
@endforeach
</tbody></table></div>
@endif

{{-- ===== TAB: SURVEY ===== --}}
@if($qmsTab === 'survey')
<div class="mt-6">
@if($surveyAvg !== null)<p class="mb-3 text-sm text-slate-500">Rata-rata keseluruhan: <strong class="text-emerald-600">{{ number_format((float) $surveyAvg, 2, ',', '.') }} / 5.00</strong></p>@endif
<div class="overflow-x-auto rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]"><table class="w-full min-w-[720px] text-sm table-sticky"><thead><tr><th>Tanggal</th><th>Pelanggan/Proyek</th><th class="text-right">Mutu</th><th class="text-right">Jadwal</th><th class="text-right">Komunikasi</th><th>Rata-rata</th></tr></thead><tbody>
@if($surveys->isEmpty())
<tr><td colspan="6" class="p-2"><x-ui.empty icon="check" title="Belum ada survei kepuasan pelanggan" description="Catat hasil survei untuk melacak kepuasan pelanggan per proyek." /></td></tr>
@endif
@foreach($surveys as $sv)
@php(
    $svAvg = round(($sv->quality_score + $sv->schedule_score + $sv->communication_score) / 3, 2)
)
@php(
    $svClass = $svAvg >= 4 ? 'font-bold text-emerald-600' : ($svAvg < 3 ? 'font-bold text-red-500' : 'font-bold text-amber-500')
)
<tr class="h-[52px]"><td>{{ $sv->survey_date->format('d/m/Y') }}</td><td>{{ $sv->customer?->name }}{{ $sv->project ? ' · '.$sv->project->code : '' }}<span class="block text-slate-400">{{ $sv->respondent_name }}</span></td><td class="text-right">{{ $sv->quality_score }}</td><td class="text-right">{{ $sv->schedule_score }}</td><td class="text-right">{{ $sv->communication_score }}</td><td class="{{ $svClass }}">{{ $svAvg }}</td></tr>
@endforeach
</tbody></table></div></div>
@endif
</div>

{{-- ===== DRAWERS (create — endpoint tetap) ===== --}}
@if(auth()->user()->hasPermission('qms.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<x-ui.drawer id="qms-risk-drawer" title="Risk & Opportunity" description="Skor inherent dihitung otomatis dari likelihood × impact.">
<form method="post" action="/admin/qms/risks" class="grid gap-4">@csrf
<x-ui.field label="Kode" name="code" required><input name="code" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5" placeholder="mis. R-2026-01"></x-ui.field>
<x-ui.field label="Jenis" name="type" required><select name="type" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="risk">Risiko</option><option value="opportunity">Peluang</option></select></x-ui.field>
<x-ui.field label="Judul" name="title" required><input name="title" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Deskripsi" name="description" required><textarea name="description" required rows="3" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<div class="grid grid-cols-2 gap-3">
<x-ui.field label="Likelihood 1-5" name="likelihood" required><input type="number" min="1" max="5" name="likelihood" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Impact 1-5" name="impact" required><input type="number" min="1" max="5" name="impact" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
</div>
<x-ui.field label="Owner" name="owner_id" required><select name="owner_id" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih owner</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></x-ui.field>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan & hitung skor</button></div>
</form>
</x-ui.drawer>

<x-ui.drawer id="qms-ncr-drawer" title="Nonconformity (NCR)" description="Catat ketidaksesuaian beserta containment awal.">
<form method="post" action="/admin/qms/ncrs" class="grid gap-4">@csrf
<x-ui.field label="Nomor NCR" name="number" required><input name="number" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Sumber" name="source_type" required hint="mis. inspection / audit"><input name="source_type" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Severitas" name="severity" required><select name="severity" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="minor">Minor</option><option value="major">Major</option><option value="observation">Observasi</option></select></x-ui.field>
<x-ui.field label="Ketidaksesuaian" name="description" required><textarea name="description" required rows="3" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<x-ui.field label="Containment" name="containment"><textarea name="containment" rows="2" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<x-ui.field label="Tenggat" name="due_at"><input type="date" name="due_at" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="rounded-xl bg-amber-600 min-h-[42px] px-5 text-sm font-bold text-white">Catat NCR</button></div>
</form>
</x-ui.drawer>

<x-ui.drawer id="qms-audit-drawer" title="Jadwal Audit Internal" description="Auditor ≠ auditee demi independensi.">
<form method="post" action="/admin/qms/audits" class="grid gap-4">@csrf
<x-ui.field label="Nomor audit" name="number" required><input name="number" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Ruang lingkup" name="scope" required><input name="scope" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Kriteria" name="criteria" required><input name="criteria" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Departemen" name="department_id" required><select name="department_id" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih departemen</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select></x-ui.field>
<div class="grid grid-cols-2 gap-3">
<x-ui.field label="Auditor" name="auditor_id" required><select name="auditor_id" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih auditor</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Auditee" name="auditee_id" required><select name="auditee_id" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih auditee</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></x-ui.field>
</div>
<x-ui.field label="Jadwal" name="scheduled_at" required><input type="date" name="scheduled_at" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Jadwalkan Audit</button></div>
</form>
</x-ui.drawer>

<x-ui.drawer id="qms-objective-drawer" title="Sasaran Mutu" description="Sasaran terukur dengan KPI dan target — realisasi diupdate berkala.">
<form method="post" action="/admin/qms/objectives" class="grid gap-4">@csrf
<x-ui.field label="Sasaran mutu" name="title" required><input name="title" required placeholder="mis. Overbreak < 8%" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="KPI / metrik" name="kpi_metric"><input name="kpi_metric" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div class="grid grid-cols-2 gap-3">
<x-ui.field label="Target" name="target_value"><input type="number" step=".01" name="target_value" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Tenggat" name="due_date"><input type="date" name="due_date" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
</div>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="rounded-xl bg-violet-700 min-h-[42px] px-5 text-sm font-bold text-white">Tambah Sasaran</button></div>
</form>
</x-ui.drawer>

<x-ui.drawer id="qms-survey-drawer" title="Survei Kepuasan Pelanggan" description="Skala 1–5 untuk mutu, jadwal, dan komunikasi.">
<form method="post" action="/admin/qms/surveys" class="grid gap-4">@csrf
<x-ui.field label="Pelanggan" name="customer_id" required><select name="customer_id" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih pelanggan</option>@foreach($customers as $cst)<option value="{{ $cst->id }}">{{ $cst->name }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Proyek terkait (opsional)" name="project_id"><select name="project_id" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">—</option>@foreach($projects as $prj)<option value="{{ $prj->id }}">{{ $prj->code }} - {{ $prj->name }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Nama responden" name="respondent_name"><input name="respondent_name" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div class="grid grid-cols-3 gap-3">
<x-ui.field label="Mutu" name="quality_score" required><input type="number" min="1" max="5" name="quality_score" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Jadwal" name="schedule_score" required><input type="number" min="1" max="5" name="schedule_score" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Komunikasi" name="communication_score" required><input type="number" min="1" max="5" name="communication_score" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
</div>
<x-ui.field label="Tanggal survei" name="survey_date" required><input type="date" name="survey_date" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Masukan pelanggan" name="comments"><textarea name="comments" rows="2" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="rounded-xl bg-violet-700 min-h-[42px] px-5 text-sm font-bold text-white">Catat Survei</button></div>
</form>
</x-ui.drawer>
@endif
</x-layouts.app>
