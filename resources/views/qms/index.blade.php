<x-layouts.app title="Quality, HSE & ISO">
<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
 <h1 class="text-3xl font-black">Quality, HSE & ISO</h1><p class="mt-2 text-slate-500">Dukungan implementasi QMS—bukan klaim sertifikasi ISO.</p>
 @if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
 @if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif
 <div class="mt-8 flex gap-2 no-print"><a href="/admin/qms" @class(['rounded-xl border px-4 py-2 text-sm font-semibold', 'bg-sky-700 text-white' => !request('view'), 'bg-white' => request('view')])>Tabel</a><a href="/admin/qms?view=kanban" @class(['rounded-xl border px-4 py-2 text-sm font-semibold', 'bg-sky-700 text-white' => request('view') === 'kanban', 'bg-white' => request('view') !== 'kanban'])>Kanban NCR</a></div>
 @if($kanban)<x-ui.kanban :columns="$kanban" class="mt-6" />@endif
 @if(!request('view'))
 <div class="mt-8 grid gap-5 lg:grid-cols-3">
  <form method="post" action="/admin/qms/risks" class="grid gap-3 rounded-2xl border bg-white p-5">@csrf<h2 class="font-bold">Risk & Opportunity</h2><input name="code" required placeholder="Kode" class="rounded-xl border p-3"><select name="type" class="rounded-xl border p-3"><option value="risk">Risiko</option><option value="opportunity">Peluang</option></select><input name="title" required placeholder="Judul" class="rounded-xl border p-3"><textarea name="description" required placeholder="Deskripsi" class="rounded-xl border p-3"></textarea><div class="grid grid-cols-2 gap-2"><input type="number" min="1" max="5" name="likelihood" required placeholder="Likelihood 1-5" class="rounded-xl border p-3"><input type="number" min="1" max="5" name="impact" required placeholder="Impact 1-5" class="rounded-xl border p-3"></div><select name="owner_id" required class="rounded-xl border p-3"><option value="">Owner</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select><button class="rounded-xl bg-sky-700 p-3 text-white">Simpan & hitung skor</button></form>
  <form method="post" action="/admin/qms/ncrs" class="grid gap-3 rounded-2xl border bg-white p-5">@csrf<h2 class="font-bold">Nonconformity (NCR)</h2><input name="number" required placeholder="Nomor NCR" class="rounded-xl border p-3"><input name="source_type" required placeholder="Sumber: inspection/audit" class="rounded-xl border p-3"><select name="severity" class="rounded-xl border p-3"><option value="minor">Minor</option><option value="major">Major</option><option value="observation">Observasi</option></select><textarea name="description" required placeholder="Ketidaksesuaian" class="rounded-xl border p-3"></textarea><textarea name="containment" placeholder="Containment" class="rounded-xl border p-3"></textarea><input type="date" name="due_at" class="rounded-xl border p-3"><button class="rounded-xl bg-amber-600 p-3 text-white">Catat NCR</button></form>
  <form method="post" action="/admin/qms/audits" class="grid gap-3 rounded-2xl border bg-white p-5">@csrf<h2 class="font-bold">Jadwal Audit Internal</h2><input name="number" required placeholder="Nomor audit" class="rounded-xl border p-3"><input name="scope" required placeholder="Ruang lingkup" class="rounded-xl border p-3"><input name="criteria" required placeholder="Kriteria" class="rounded-xl border p-3"><select name="department_id" required class="rounded-xl border p-3"><option value="">Departemen</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select><select name="auditor_id" required class="rounded-xl border p-3"><option value="">Auditor</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select><select name="auditee_id" required class="rounded-xl border p-3"><option value="">Auditee</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select><input type="date" name="scheduled_at" required class="rounded-xl border p-3"><button class="rounded-xl bg-slate-800 p-3 text-white">Jadwalkan audit</button></form>
 </div>
 <div class="mt-8 overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-sm"><thead><tr><th>Kode</th><th>Jenis</th><th>Judul</th><th>Skor</th><th>Status</th></tr></thead><tbody>@forelse($risks as $risk)<tr><td>{{ $risk->code }}</td><td>{{ strtoupper($risk->type) }}</td><td>{{ $risk->title }}</td><td>{{ $risk->inherent_score }}</td><td>{{ $risk->status }}</td></tr>@empty<tr><td colspan="5" class="p-8 text-center">Belum ada risiko/peluang.</td></tr>@endforelse</tbody></table></div>
 <div class="mt-8 space-y-4"><h2 class="text-xl font-bold">NCR & CAPA</h2>@forelse($ncrs as $ncr)<article class="rounded-2xl border bg-white p-5"><div class="flex flex-wrap justify-between gap-3"><strong>{{ $ncr->number }} · {{ strtoupper($ncr->severity) }}</strong><span>{{ strtoupper($ncr->status) }}</span></div><p class="mt-2">{{ $ncr->description }}</p><form method="post" action="/admin/qms/ncrs/{{ $ncr->id }}/actions" class="mt-4 grid gap-2 md:grid-cols-4">@csrf<input name="action" required placeholder="Corrective action" class="rounded-xl border p-3"><select name="owner_id" required class="rounded-xl border p-3"><option value="">PIC</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select><input type="date" name="due_at" required class="rounded-xl border p-3"><button class="rounded-xl bg-slate-800 p-3 text-white">Tambah CAPA</button></form>@foreach($ncr->actions as $action)<form method="post" action="/admin/qms/actions/{{ $action->id }}/verify" class="mt-3 grid gap-2 rounded-xl bg-slate-50 p-3 md:grid-cols-4">@csrf<div>{{ $action->action }}<br><small>{{ strtoupper($action->status) }}</small></div><input name="evidence" value="{{ $action->evidence }}" placeholder="Referensi evidence" class="rounded-xl border p-3"><input name="effectiveness_notes" required placeholder="Catatan efektivitas" class="rounded-xl border p-3"><button class="rounded-xl bg-emerald-700 p-3 text-white">Verifikasi independen</button></form>@endforeach</article>@empty<p>Belum ada NCR.</p>@endforelse</div>
 <div class="mt-8 overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-sm"><thead><tr><th>Nomor Audit</th><th>Ruang Lingkup</th><th>Jadwal</th><th>Status</th></tr></thead><tbody>@forelse($audits as $audit)<tr><td>{{ $audit->number }}</td><td>{{ $audit->scope }}</td><td>{{ $audit->scheduled_at }}</td><td>{{ strtoupper($audit->status) }}</td></tr>@empty<tr><td colspan="4" class="p-8 text-center">Belum ada audit.</td></tr>@endforelse</tbody></table></div>

<h2 id="sasaran" class="mt-12 scroll-mt-24 text-lg font-black">Sasaran Mutu & KPI</h2>
<form method="post" action="/admin/qms/objectives" class="mt-3 grid gap-2 rounded-2xl border bg-white p-5 no-print">@csrf
<div class="grid gap-2 sm:grid-cols-5"><input name="title" required placeholder="Sasaran mutu (mis. Overbreak < 8%)" class="rounded-xl border p-3"><input name="kpi_metric" placeholder="KPI / metrik" class="rounded-xl border p-3"><input type="number" step=".01" name="target_value" placeholder="Target" class="rounded-xl border p-3"><input type="date" name="due_date" class="rounded-xl border p-3"><button class="rounded-xl bg-violet-700 p-3 font-bold text-white">Tambah sasaran</button></div>
</form>
<div class="mt-3 overflow-x-auto rounded-2xl border bg-white"><table class="w-full min-w-[700px] text-sm table-sticky"><thead><tr><th>Sasaran</th><th>KPI</th><th class="text-right">Target</th><th class="text-right">Realisasi</th><th class="text-right">Capaian</th><th>Update realisasi</th></tr></thead><tbody>
@if($objectives->isEmpty())
<tr><td colspan="6" class="p-6 text-center text-slate-500">Belum ada sasaran mutu.</td></tr>
@endif
@foreach($objectives as $objective)
@php(
    $achievement = ($objective->target_value && $objective->actual_value !== null && (float) $objective->target_value != 0) ? round((float) $objective->actual_value / (float) $objective->target_value * 100, 1) : null
)
@php(
    $achClass = ($achievement !== null && $achievement >= 100) ? 'font-bold text-emerald-600' : ''
)
<tr>
<td>{{ $objective->title }}<span class="block text-xs text-slate-400">{{ $objective->due_date?->format('d/m/Y') }}</span></td>
<td>{{ $objective->kpi_metric ?? '-' }}</td>
<td class="text-right font-mono">{{ $objective->target_value ?? '-' }}</td>
<td class="text-right font-mono">{{ $objective->actual_value ?? '-' }}</td>
<td class="text-right {{ $achClass }}">{{ $achievement === null ? '-' : $achievement.'%' }}</td>
<td><form method="post" action="/admin/qms/objectives/{{ $objective->id }}/actual" class="flex gap-1">@csrf<input type="number" step=".01" name="actual_value" required class="w-24 rounded border p-1 text-xs"><button class="font-bold text-sky-700 text-xs">Update</button></form></td>
</tr>
@endforeach
</tbody></table></div>

<h2 id="kepuasan" class="mt-12 scroll-mt-24 text-lg font-black">Kepuasan Pelanggan</h2>
@if($surveyAvg !== null)<p class="mt-1 text-sm text-slate-500">Rata-rata keseluruhan: <strong class="text-emerald-600">{{ number_format((float) $surveyAvg, 2, ',', '.') }} / 5.00</strong></p>@endif
<form method="post" action="/admin/qms/surveys" class="mt-3 grid gap-2 rounded-2xl border bg-white p-5 no-print">@csrf
<select name="customer_id" required class="rounded-xl border p-3"><option value="">Pelanggan</option>@foreach($customers as $cst)<option value="{{ $cst->id }}">{{ $cst->name }}</option>@endforeach</select>
<select name="project_id" class="rounded-xl border p-3"><option value="">Proyek terkait (opsional)</option>@foreach($projects as $prj)<option value="{{ $prj->id }}">{{ $prj->code }} — {{ $prj->name }}</option>@endforeach</select>
<input name="respondent_name" placeholder="Nama responden" class="rounded-xl border p-3">
<div class="grid grid-cols-3 gap-2"><label class="text-xs font-semibold">Mutu<input type="number" min="1" max="5" name="quality_score" required class="mt-1 w-full rounded-xl border p-2"></label><label class="text-xs font-semibold">Jadwal<input type="number" min="1" max="5" name="schedule_score" required class="mt-1 w-full rounded-xl border p-2"></label><label class="text-xs font-semibold">Komunikasi<input type="number" min="1" max="5" name="communication_score" required class="mt-1 w-full rounded-xl border p-2"></label></div>
<input type="date" name="survey_date" required class="rounded-xl border p-3">
<textarea name="comments" rows="2" placeholder="Masukan pelanggan" class="w-full rounded-xl border p-3"></textarea>
<button class="w-fit rounded-xl bg-violet-700 px-6 py-3 font-bold text-white">Catat survei</button>
</form>
<div class="mt-3 overflow-x-auto rounded-2xl border bg-white"><table class="w-full min-w-[720px] text-sm table-sticky"><thead><tr><th>Tanggal</th><th>Pelanggan/Proyek</th><th class="text-right">Mutu</th><th class="text-right">Jadwal</th><th class="text-right">Komunikasi</th><th>Rata-rata</th></tr></thead><tbody>
@if($surveys->isEmpty())
<tr><td colspan="6" class="p-6 text-center text-slate-500">Belum ada survei kepuasan pelanggan.</td></tr>
@endif
@foreach($surveys as $sv)
@php(
    $svAvg = round(($sv->quality_score + $sv->schedule_score + $sv->communication_score) / 3, 2)
)
@php(
    $svClass = $svAvg >= 4 ? 'font-bold text-emerald-600' : ($svAvg < 3 ? 'font-bold text-red-500' : 'font-bold text-amber-500')
)
<tr><td>{{ $sv->survey_date->format('d/m/Y') }}</td><td>{{ $sv->customer?->name }}{{ $sv->project ? ' · '.$sv->project->code : '' }}<span class="block text-slate-400">{{ $sv->respondent_name }}</span></td><td class="text-right">{{ $sv->quality_score }}</td><td class="text-right">{{ $sv->schedule_score }}</td><td class="text-right">{{ $sv->communication_score }}</td><td class="{{ $svClass }}">{{ $svAvg }}</td></tr>
@endforeach
</tbody></table></div>

<div class="mt-12"><h2 id="timeline-ncr" class="scroll-mt-24 text-lg font-black">Timeline NCR & Tindakan</h2><p class="mt-1 text-sm text-slate-500">Kronologi ketidaksesuaian dan tindakan korektifnya dari yang terbaru.</p>
<ol class="mt-4 space-y-4 border-l-2 border-slate-200 pl-6">
@forelse($ncrs as $ncr)
<li class="relative">
<span class="absolute -left-[31px] top-1.5 grid h-5 w-5 place-items-center rounded-full {{ $ncr->status === 'closed' ? 'bg-emerald-500' : ($ncr->severity === 'critical' ? 'bg-red-500' : 'bg-amber-500') }} text-[9px] font-black text-white">{{ $ncr->status === 'closed' ? '✓' : '!' }}</span>
<article class="rounded-xl border bg-white p-4"><div class="flex flex-wrap items-center justify-between gap-2 text-sm"><strong>{{ $ncr->number }}</strong><span class="text-xs uppercase text-slate-500">{{ $ncr->source_type }} · severitas {{ $ncr->severity }}</span></div>
<p class="mt-1 text-sm text-slate-600">{{ str($ncr->description)->limit(140) }}</p>
@if($ncr->actions->isNotEmpty())<ul class="mt-2 space-y-1 border-t pt-2 text-xs text-slate-500">@foreach($ncr->actions as $action)<li>↳ <strong>{{ $action->status }}</strong> · {{ str($action->action)->limit(90) }} @if($action->due_at)· tenggat {{ $action->due_at->format('d/m/Y') }}@endif</li>@endforeach</ul>@endif
</article>
</li>
@empty
<li class="text-sm text-slate-500">Belum ada NCR tercatat — kualitas dalam kendali.</li>
@endforelse
</ol></div>
@endif
</section></x-layouts.app>
