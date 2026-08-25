<x-layouts.app title="HSE Workspace">
<div class="page-container">
@php($hseTab = request('tab', 'overview'))
<x-ui.page-header title="HSE Workspace" subtitle="JSA mengendalikan risiko sebelum pekerjaan dimulai. Permit hanya diterbitkan dari JSA disetujui. Incident ditindaklanjuti sampai tindakan diverifikasi efektif.">
<x-slot:actions>
@if(auth()->user()->hasPermission('hse.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
@if($hseTab === 'jsa')<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="hse-jsa-drawer"><x-ui.icon name="plus" class="h-4 w-4" />Buat JSA</button>@endif
@if($hseTab === 'incident')<button type="button" class="rounded-xl bg-red-700 inline-flex min-h-[42px] items-center gap-2 px-4 text-sm font-bold text-white shadow-sm" data-drawer-open="hse-incident-drawer"><x-ui.icon name="plus" class="h-4 w-4" />Laporkan Incident</button>@endif
@if($hseTab === 'review')<button type="button" class="rounded-xl bg-violet-700 inline-flex min-h-[42px] items-center gap-2 px-4 text-sm font-bold text-white shadow-sm" data-drawer-open="hse-review-drawer"><x-ui.icon name="plus" class="h-4 w-4" />Management Review</button>@endif
@endif
</x-slot:actions>
</x-ui.page-header>

@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
<x-ui.stat-card label="JSA Aktif (Approved)" value="{{ number_format($jsas->where('status', 'approved')->count()) }}" icon="document" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Permit Berlaku" value="{{ number_format($permits->where('status', 'issued')->filter(fn ($p) => $p->valid_until->isFuture())->count()) }}" icon="check" tone="success" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Incident Terbuka" value="{{ number_format($incidents->where('status', '!=', 'closed')->count()) }}" icon="triangle-alert" tone="{{ $incidents->where('status', '!=', 'closed')->isNotEmpty() ? 'danger' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Action Overdue" value="{{ number_format($incidents->flatMap->actions->filter(fn ($a) => $a->status !== 'verified' && $a->due_at?->isPast())->count()) }}" icon="clock" tone="warning" :value-class="'text-[24px] leading-tight'" />
</div>

<x-ui.tabs :tabs="['overview' => 'JSA & Permit', 'incident' => 'Incident & Corrective Action', 'review' => 'Management Review']" :active="$hseTab" class="mt-6" />

{{-- ===== TAB: JSA & PERMIT ===== --}}
@if($hseTab === 'overview')
<section>
<p class="text-sm text-slate-500">Alur: Draft JSA → approval → validasi hasil approval → terbitkan Permit to Work.</p>
<div class="mt-4 space-y-4">@forelse($jsas as $jsa)<x-ui.card><div class="flex flex-wrap justify-between gap-3"><div><strong>{{ $jsa->number }} — {{ $jsa->activity }}</strong><p class="text-sm text-slate-500">Risiko {{ strtoupper($jsa->risk_level) }} · berlaku {{ $jsa->valid_from->format('d/m/Y') }}–{{ $jsa->valid_until->format('d/m/Y') }}</p></div><span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold">{{ strtoupper($jsa->status) }}</span></div><div class="mt-4">@if($jsa->status==='draft')<form method="post" action="/admin/hse/jsa/{{ $jsa->id }}/submit" class="flex flex-wrap gap-2">@csrf<select name="workflow_id" required class="min-w-64 rounded-xl border p-2"><option value="">Pilih workflow approval</option>@foreach($workflows as $workflow)<option value="{{ $workflow->id }}">{{ $workflow->name }}</option>@endforeach</select><button class="rounded-xl bg-[var(--brand-primary)] px-4 text-white">Kirim Approval</button></form>@elseif($jsa->status==='pending_approval')<form method="post" action="/admin/hse/jsa/{{ $jsa->id }}/activate">@csrf<button class="rounded-xl bg-emerald-700 px-4 py-2 text-white">Validasi Approval Selesai</button></form>@elseif($jsa->status==='approved')<form method="post" action="/admin/hse/jsa/{{ $jsa->id }}/permits" class="grid gap-2 rounded-xl bg-amber-50 p-4 md:grid-cols-3">@csrf<input name="number" required placeholder="Nomor PTW" class="rounded-xl border p-2"><input name="permit_type" required placeholder="Jenis permit" class="rounded-xl border p-2"><input name="work_location" required placeholder="Lokasi kerja" class="rounded-xl border p-2"><input type="datetime-local" name="valid_from" required class="rounded-xl border p-2"><input type="datetime-local" name="valid_until" required class="rounded-xl border p-2"><button class="rounded-xl bg-amber-600 p-2 font-bold text-white md:col-span-3">Terbitkan Permit to Work</button></form>@endif</div></x-ui.card>@empty<div class="rounded-2xl border border-dashed bg-white p-8 text-center"><h3 class="font-bold">Belum ada JSA</h3><p class="mt-1 text-sm text-slate-500">Buat JSA untuk mengendalikan risiko sebelum pekerjaan dimulai.</p></div>@endforelse</div>
<div class="mt-8 overflow-x-auto rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]"><table class="w-full min-w-[700px] text-sm"><thead><tr><th>Nomor Permit</th><th>JSA</th><th>Jenis</th><th>Lokasi</th><th>Berlaku</th><th>Status</th></tr></thead><tbody>@forelse($permits as $permit)<tr class="h-[52px]"><td class="font-mono font-bold">{{ $permit->number }}</td><td>#{{ $permit->job_safety_analysis_id }}</td><td>{{ $permit->permit_type }}</td><td>{{ $permit->work_location }}</td><td>{{ $permit->valid_from->format('d/m/Y H:i') }}–{{ $permit->valid_until->format('d/m/Y H:i') }}</td><td><span class="chip chip-default">{{ strtoupper($permit->status) }}</span></td></tr>@empty<tr><td colspan="6" class="p-2"><x-ui.empty icon="document" title="Belum ada Permit to Work" description="Permit diterbitkan dari JSA berstatus approved." /></td></tr>@endforelse</tbody></table></div>
</section>
@endif

{{-- ===== TAB: INCIDENT ===== --}}
@if($hseTab === 'incident')
<section>
<p class="text-sm text-slate-500">Alur: laporan kejadian → investigasi → action & PIC → evidence → verifikasi independen → close.</p>
<div class="mt-4 space-y-4">@forelse($incidents as $incident)<x-ui.card><div class="flex flex-wrap justify-between gap-3"><div><strong>{{ $incident->number }} — {{ strtoupper(str_replace('_', ' ', $incident->type)) }}</strong><p class="text-sm text-slate-500">{{ $incident->occurred_at->format('d/m/Y H:i') }} · {{ $incident->location }} · severity {{ strtoupper($incident->severity) }}</p></div><span class="rounded-lg bg-red-50 px-3 py-1 text-xs font-bold text-red-700">{{ strtoupper($incident->status) }}</span></div><p class="mt-3">{{ $incident->description }}</p><form method="post" action="/admin/hse/incidents/{{ $incident->id }}/actions" class="mt-4 grid gap-2 rounded-xl bg-slate-50 p-4 md:grid-cols-4">@csrf<input name="action" required placeholder="Corrective action" class="rounded-xl border p-2"><select name="owner_id" required class="rounded-xl border p-2">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select><input type="date" name="due_at" required class="rounded-xl border p-2"><button class="rounded-xl bg-slate-800 p-2 text-white">Tambah Action</button></form>@foreach($incident->actions as $action)<form method="post" action="/admin/hse/actions/{{ $action->id }}/verify" class="mt-2 grid gap-2 rounded-xl border p-3 md:grid-cols-[1fr_1fr_160px]">@csrf<span>{{ $action->action }} · {{ strtoupper($action->status) }}</span><input name="evidence" value="{{ $action->evidence }}" placeholder="Referensi evidence" class="rounded-xl border p-2"><button class="rounded-xl bg-emerald-700 px-3 text-white">Verifikasi Efektif</button></form>@endforeach @if($incident->status!=='closed')<form method="post" action="/admin/hse/incidents/{{ $incident->id }}/close" class="mt-3 text-right">@csrf<button class="font-bold text-red-700">Tutup setelah seluruh action verified</button></form>@endif</x-ui.card>@empty<div class="rounded-2xl border border-dashed bg-white p-8 text-center"><h3 class="font-bold">Belum ada near miss atau incident</h3><p class="mt-1 text-sm text-slate-500">Gunakan tombol "Laporkan Incident" untuk memulai investigasi dan corrective action.</p></div>@endforelse</div>
</section>
@endif

{{-- ===== TAB: MANAGEMENT REVIEW ===== --}}
@if($hseTab === 'review')
<section>
<div class="overflow-x-auto rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]"><table class="w-full text-sm"><thead><tr><th>Nomor Review</th><th>Tanggal Rapat</th><th>Status</th></tr></thead><tbody>
@forelse($reviews as $review)
<tr class="h-[52px]"><td class="font-mono font-bold">{{ $review->number }}</td><td>{{ $review->meeting_date?->format('d/m/Y') }}</td><td><span class="chip chip-default">{{ strtoupper($review->status ?? 'snapshot') }}</span></td></tr>
@empty
<tr><td colspan="3" class="p-2"><x-ui.empty icon="clock" title="Belum ada management review" description="Tarik snapshot evidence risiko, NCR, audit, incident, dan tindakan pada tanggal rapat." /></td></tr>
@endforelse
</tbody></table></div>
</section>
@endif
</div>

@if(auth()->user()->hasPermission('hse.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<x-ui.drawer id="hse-jsa-drawer" title="Job Safety Analysis" description="Identifikasi hazard dan control sebelum meminta izin kerja.">
<form method="post" action="/admin/hse/jsa" class="grid gap-4">@csrf
<x-ui.field label="Proyek" name="project_id" required><select name="project_id" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih proyek</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->code }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Nomor JSA" name="number" required><input name="number" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Aktivitas kerja" name="activity" required><input name="activity" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Lokasi" name="location"><input name="location" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Hazard (satu per baris)" name="hazards" required><textarea name="hazards" required rows="3" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<x-ui.field label="Control (satu per baris)" name="controls" required><textarea name="controls" required rows="3" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<x-ui.field label="Tingkat risiko" name="risk_level"><select name="risk_level" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="extreme">Extreme</option></select></x-ui.field>
<div class="grid grid-cols-2 gap-3">
<x-ui.field label="Berlaku dari" name="valid_from" required><input type="date" name="valid_from" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Berlaku sampai" name="valid_until" required><input type="date" name="valid_until" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
</div>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan Draft JSA</button></div>
</form>
</x-ui.drawer>

<x-ui.drawer id="hse-incident-drawer" title="Near Miss / Incident" description="Catat kejadian, tindakan langsung, dan hasil investigasi awal.">
<form method="post" action="/admin/hse/incidents" class="grid gap-4">@csrf
<x-ui.field label="Proyek" name="project_id" required><select name="project_id" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih proyek</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->code }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Nomor laporan" name="number" required><input name="number" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div class="grid grid-cols-2 gap-3">
<x-ui.field label="Jenis" name="type"><select name="type" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="near_miss">Near Miss</option><option value="incident">Incident</option><option value="environmental">Environmental</option><option value="unsafe_condition">Unsafe Condition</option></select></x-ui.field>
<x-ui.field label="Severity" name="severity"><select name="severity" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="fatal">Fatal</option></select></x-ui.field>
</div>
<x-ui.field label="Waktu kejadian" name="occurred_at" required><input type="datetime-local" name="occurred_at" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Lokasi kejadian" name="location" required><input name="location" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Deskripsi kejadian" name="description" required><textarea name="description" required rows="3" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<x-ui.field label="Tindakan langsung" name="immediate_action"><textarea name="immediate_action" rows="2" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<x-ui.field label="Root cause awal" name="root_cause"><textarea name="root_cause" rows="2" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="rounded-xl bg-red-700 min-h-[42px] px-5 text-sm font-bold text-white">Simpan Laporan</button></div>
</form>
</x-ui.drawer>

<x-ui.drawer id="hse-review-drawer" title="Management Review" description="Mengambil evidence risiko, NCR, audit, incident, dan tindakan pada tanggal rapat.">
<form method="post" action="/admin/hse/management-reviews" class="grid gap-4">@csrf
<x-ui.field label="Nomor review" name="number" required><input name="number" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Tanggal rapat" name="meeting_date" required><input type="date" name="meeting_date" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="rounded-xl bg-violet-700 min-h-[42px] px-5 text-sm font-bold text-white">Buat Snapshot Evidence</button></div>
</form>
</x-ui.drawer>
@endif
</x-layouts.app>
