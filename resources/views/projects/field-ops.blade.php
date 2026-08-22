<x-layouts.app title="Field Operations — Bored Pile"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-3xl font-black">Field Operations</h1>
<p class="mt-2 text-slate-500">Catatan lapangan bored pile: drilling & bore log, delivery beton (slump, accept/reject), dan pengujian pile dengan gate kelulusan sebelum completed.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 no-print"><form method="get" class="flex gap-2"><select name="project" onchange="this.form.submit()" class="rounded-xl border p-3 text-sm"><option value="">Pilih proyek aktif</option>@foreach($projects as $p)<option value="{{ $p->id }}" @selected($project?->id === $p->id)>{{ $p->code }} — {{ $p->name }}</option>@endforeach</select></form></div>

@if($project)
@php($canManage = auth()->user()->hasPermission('project.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))

<h2 id="drilling" class="mt-10 scroll-mt-24 text-lg font-black">1 · Drilling Record & Bore Log</h2>
@if($canManage)
<form method="post" action="/admin/projects/field-ops/drillings" class="mt-3 grid gap-3 rounded-2xl border bg-white p-5">@csrf
<input type="hidden" name="layers" value="">
<h3 class="text-sm font-bold">Rekam Drilling Baru</h3>
<select name="bored_pile_id" required class="rounded-xl border p-3"><option value="">Titik pile</option>@foreach($piles as $pile)<option value="{{ $pile->id }}">{{ $pile->pile_number }} ({{ str($pile->status)->replace('_',' ') }})</option>@endforeach</select>
<div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4"><label class="text-xs font-semibold">Mulai<input type="datetime-local" name="drilling_started_at" required class="mt-1 w-full rounded-xl border p-2.5"></label><label class="text-xs font-semibold">Selesai<input type="datetime-local" name="drilling_finished_at" class="mt-1 w-full rounded-xl border p-2.5"></label><label class="text-xs font-semibold">Muka air tanah (m)<input type="number" step=".001" name="groundwater_level_m" class="mt-1 w-full rounded-xl border p-2.5"></label><label class="text-xs font-semibold">Alat bor<input name="drilling_tool" placeholder="Bucket / Auger" class="mt-1 w-full rounded-xl border p-2.5"></label><label class="text-xs font-semibold">Metode cleaning<input name="cleaning_method" placeholder="Air siram / bentonite" class="mt-1 w-full rounded-xl border p-2.5"></label><label class="text-xs font-semibold">Final cleaning (menit)<input type="number" min="0" name="final_cleaning_minutes" class="mt-1 w-full rounded-xl border p-2.5"></label><label class="text-xs font-semibold">Endapan (mm)<input type="number" step=".01" name="sediment_depth_mm" class="mt-1 w-full rounded-xl border p-2.5"></label><label class="text-xs font-semibold">Cuaca<input name="weather" class="mt-1 w-full rounded-xl border p-2.5"></label></div>
<label class="block text-xs font-semibold">Bore log — satu lapisan per baris: <code>dari|ke|deskripsi tanah</code><textarea name="layers" rows="4" placeholder="0|2.5|Lempung liat coklat&#10;2.5|8|Pasir lepas" class="mt-1 w-full rounded-xl border p-2.5 font-mono text-xs"></textarea></label>
<div class="grid gap-2 sm:grid-cols-2"><input name="obstruction" placeholder="Hambatan (opsional)" class="rounded-xl border p-3"><input name="problem" placeholder="Masalah (opsional)" class="rounded-xl border p-3"><input name="corrective_action" placeholder="Tindakan korektif (opsional)" class="rounded-xl border p-3"><input name="notes" placeholder="Catatan (opsional)" class="rounded-xl border p-3"></div>
<button class="w-fit rounded-xl bg-sky-700 px-6 py-3 font-bold text-white">Simpan drilling record</button>
</form>
@endif
<div class="mt-4 space-y-3">@forelse($drillings as $d)
<article class="rounded-2xl border bg-white p-4">
<div class="flex flex-wrap items-center justify-between gap-2">
<strong>{{ $d->pile?->pile_number }} · {{ $d->drilling_started_at->format('d/m/Y H:i') }}</strong>
<x-ui.badge :status="$d->status === 'verified' ? 'approved' : 'draft'" />
</div>
<p class="mt-1 text-xs text-slate-500">{{ $d->layers->count() }} lapisan · muka air {{ $d->groundwater_level_m ?? '-' }} m · endapan {{ $d->sediment_depth_mm ?? '-' }} mm · cuaca {{ $d->weather ?? '-' }} · oleh {{ $d->recorder?->name }} @if($d->verifier)· diverifikasi {{ $d->verifier->name }}@endif</p>
<details class="mt-2"><summary class="cursor-pointer text-xs font-bold text-sky-700">Bore log</summary>
<table class="mt-2 w-full text-xs"><thead><tr><th>#</th><th>Dari (m)</th><th>Ke (m)</th><th>Deskripsi</th></tr></thead><tbody>@foreach($d->layers as $layer)<tr><td>{{ $layer->sequence }}</td><td>{{ $layer->depth_from_m }}</td><td>{{ $layer->depth_to_m }}</td><td>{{ $layer->soil_description }}</td></tr>@endforeach</tbody></table></details>
@if($canManage && $d->status === 'draft' && $d->recorded_by !== auth()->id())<form method="post" action="/admin/projects/field-ops/drillings/{{ $d->id }}/verify" class="mt-2">@csrf<button class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white">Verifikasi</button></form>@endif
<form method="post" action="/admin/projects/field-ops/evidence/drilling" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2 no-print">@csrf<input type="hidden" name="id" value="{{ $d->id }}"><input type="file" name="file" accept=".jpg,.jpeg,.png,.webp" required class="text-xs"><button class="rounded-lg border px-2 py-1 text-xs font-bold">Lampirkan foto</button></form>
</article>
@empty<x-ui.empty icon="document" title="Belum ada drilling record" description="Pilih proyek aktif dan rekam drilling pertama beserta bore log lapisan tanahnya." />@endforelse</div>

<h2 id="concrete" class="mt-12 scroll-mt-24 text-lg font-black">2 · Concrete Direct Delivery</h2>
@if($canManage)
<form method="post" action="/admin/projects/field-ops/deliveries" class="mt-3 grid gap-3 rounded-2xl border bg-white p-5">@csrf
<h3 class="text-sm font-bold">Truck Masuk</h3>
<select name="bored_pile_id" required class="rounded-xl border p-3"><option value="">Titik pile tujuan</option>@foreach($piles as $pile)<option value="{{ $pile->id }}">{{ $pile->pile_number }}{{ $pile->concrete_grade ? ' · '.$pile->concrete_grade : '' }}</option>@endforeach</select>
<div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
<label class="text-xs font-semibold">No. DO<input name="delivery_order_number" required class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">No. truk<input name="truck_number" required class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">Pemasok<select name="vendor_id" class="mt-1 w-full rounded-xl border p-2.5"><option value="">-</option>@foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach</select></label>
<label class="text-xs font-semibold">Batching plant<input name="batching_plant" class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">Mutu beton<input name="grade" placeholder="K-225 / fc' 25" class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">Slump (cm)<input type="number" step=".1" name="slump_cm" class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">Volume pesan (m³)<input type="number" step=".0001" name="ordered_volume_m3" required class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">Volume tiba (m³)<input type="number" step=".0001" name="delivered_volume_m3" required class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">Diterima (m³)<input type="number" step=".0001" name="accepted_volume_m3" required class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">Ditolak (m³)<input type="number" step=".0001" name="rejected_volume_m3" value="0" class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">No. sampel<input name="sample_number" class="mt-1 w-full rounded-xl border p-2.5"></label>
<label class="text-xs font-semibold">Idempotency<input name="idempotency_key" value="cd-{{ now()->format('YmdHis') }}-{{ rand(100,999) }}" required class="mt-1 w-full rounded-xl border p-2.5 font-mono text-xs"></label>
</div>
<button class="w-fit rounded-xl bg-sky-700 px-6 py-3 font-bold text-white">Catat truck</button>
</form>
@endif
<div class="mt-4 overflow-x-auto rounded-2xl border bg-white"><table class="w-full min-w-[900px] text-sm table-sticky"><thead><tr><th>DO</th><th>Pile</th><th>Pemasok</th><th>Truk</th><th class="text-right">Pesan/Tiba</th><th class="text-right">Terima/Tolak</th><th>Slump</th><th>Status</th><th>Aksi</th></tr></thead><tbody>@forelse($deliveries as $cd)
<tr><td class="font-mono text-xs">{{ $cd->delivery_order_number }}<span class="block text-slate-400">{{ $cd->grade }}</span></td><td>{{ $cd->pile?->pile_number }}</td><td>{{ $cd->vendor?->name ?? '-' }}</td><td>{{ $cd->truck_number }}</td><td class="text-right font-mono">{{ $cd->ordered_volume_m3 }} / {{ $cd->delivered_volume_m3 }}</td><td class="text-right font-mono {{ (float) $cd->rejected_volume_m3 > 0 ? 'text-red-600' : '' }}">{{ $cd->accepted_volume_m3 }} / {{ $cd->rejected_volume_m3 }}</td><td>{{ $cd->slump_cm ?? '-' }}</td><td><x-ui.badge :status="match($cd->status) { 'approved' => 'approved', 'rejected' => 'rejected', default => 'draft' }" /></td><td class="min-w-40">@if($canManage && $cd->status === 'draft')
<form method="post" action="/admin/projects/field-ops/deliveries/{{ $cd->id }}/approve" class="inline">@csrf<button onclick="return confirm('Approve delivery ini akan memperbarui volume beton aktual pile secara permanen. Lanjutkan?')" class="font-bold text-emerald-700">Approve</button></form>
<form method="post" action="/admin/projects/field-ops/deliveries/{{ $cd->id }}/reject" class="ml-3 inline-flex gap-1">@csrf<input name="rejection_reason" placeholder="Alasan" required class="w-28 rounded border p-1 text-xs"><button class="font-bold text-red-600">Tolak</button></form>
@elseif($cd->status === 'approved')<span class="text-xs text-slate-400">{{ $cd->approved_at?->format('d/m H:i') }}</span>@else<span class="text-xs text-red-500">{{ \Illuminate\Support\Str::limit($cd->rejection_reason, 30) }}</span>@endif</td></tr>
@empty<tr><td colspan="9" class="p-8 text-center">Belum ada delivery beton.</td></tr>@endforelse</tbody></table></div>

<h2 id="testing" class="mt-12 scroll-mt-24 text-lg font-black">3 · Pile Testing</h2>
@if($canManage)
<form method="post" action="/admin/projects/field-ops/tests" class="mt-3 grid gap-3 rounded-2xl border bg-white p-5">@csrf
<h3 class="text-sm font-bold">Jadwalkan Pengujian</h3>
<div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
<select name="bored_pile_id" required class="rounded-xl border p-3"><option value="">Titik pile</option>@foreach($piles as $pile)<option value="{{ $pile->id }}">{{ $pile->pile_number }}</option>@endforeach</select>
<input name="number" required placeholder="Nomor uji (mis. PIT-001)" class="rounded-xl border p-3">
<select name="test_type" required class="rounded-xl border p-3">@foreach($testTypes as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select>
<input name="provider_name" placeholder="Penyedia pengujian" class="rounded-xl border p-3">
<label class="text-xs font-semibold">Tanggal rencana<input type="date" name="scheduled_date" required class="mt-1 w-full rounded-xl border p-2.5"></label>
<input name="acceptance_criteria" placeholder="Kriteria terima" class="rounded-xl border p-3">
</div>
<button class="w-fit rounded-xl bg-violet-700 px-6 py-3 font-bold text-white">Jadwalkan</button>
</form>
@endif
<div class="mt-4 overflow-x-auto rounded-2xl border bg-white"><table class="w-full min-w-[800px] text-sm table-sticky"><thead><tr><th>Nomor</th><th>Pile</th><th>Jenis</th><th>Penyedia</th><th>Rencana</th><th>Hasil</th><th>Laporan</th><th>Aksi</th></tr></thead><tbody>@forelse($tests as $test)
<tr><td class="font-mono text-xs">{{ $test->number }}</td><td>{{ $test->pile?->pile_number }}</td><td>{{ $test->test_type }}</td><td>{{ $test->provider_name ?? '-' }}</td><td>{{ $test->scheduled_date->format('d/m/Y') }}</td>
<td><x-ui.badge :status="match($test->result_status) { 'passed' => 'approved', 'failed' => 'rejected', default => 'pending_approval' }" :label="$test->result_status" />@if($test->consultant_approved_at)<span class="ml-1 text-[10px] text-emerald-600">✓ konsultan</span>@endif</td>
<td>{{ $test->report_number ?? '-' }}</td>
<td class="min-w-56">@if($canManage && $test->result_status === 'scheduled')
<form method="post" action="/admin/projects/field-ops/tests/{{ $test->id }}/result" class="flex flex-wrap items-center gap-1">@csrf<select name="result_status" class="rounded border p-1.5 text-xs"><option value="passed">Passed</option><option value="failed">Failed</option></select><input name="report_number" placeholder="No. laporan" class="w-24 rounded border p-1.5 text-xs"><button class="font-bold text-sky-700">Rekam</button></form>
@elseif($canManage && $test->result_status === 'passed' && ! $test->consultant_approved_at)
<form method="post" action="/admin/projects/field-ops/tests/{{ $test->id }}/approve" class="inline">@csrf<button class="font-bold text-emerald-700">Approval konsultan</button></form>
@else<span class="text-xs text-slate-400">{{ \Illuminate\Support\Str::limit($test->interpretation ?? '-', 40) }}</span>@endif</td></tr>
@empty<tr><td colspan="8" class="p-8 text-center">Belum ada jadwal pengujian.</td></tr>@endforelse</tbody></table></div>
@else
<x-ui.empty icon="cube" title="Belum ada proyek aktif" description="Aktifkan minimal satu proyek untuk memulai catatan lapangan." />
@endif
</section></x-layouts.app>
