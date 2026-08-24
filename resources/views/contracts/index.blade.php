<x-layouts.app title="Administrasi Kontrak">
<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-2xl font-bold tracking-tight">Administrasi Kontrak</h1>
<p class="mt-2 text-slate-500">Variation Order, addendum, EOT, claim, denda keterlambatan, dan bond — semua melalui approval berjenjang, tanpa posting jurnal otomatis.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

<form method="get" class="mt-6 flex flex-wrap items-end gap-3 no-print">
<div><label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Jenis</label><select name="type" class="mt-1 rounded-xl border p-2 text-sm"><option value="">Semua</option>@foreach($types as $key => $label)<option value="{{ $key }}" @selected($filterType === $key)>{{ $label }}</option>@endforeach</select></div>
<div><label class="block text-xs font-bold uppercase tracking-wide text-slate-500">Status</label><select name="status" class="mt-1 rounded-xl border p-2 text-sm"><option value="">Semua</option>@foreach(['draft', 'pending_approval', 'approved', 'rejected'] as $s)<option value="{{ $s }}" @selected($filterStatus === $s)>{{ strtoupper(str_replace('_', ' ', $s)) }}</option>@endforeach</select></div>
<button class="rounded-xl border px-4 py-2 text-sm font-semibold">Filter</button>
</form>

<div class="mt-6 overflow-x-auto rounded-2xl border bg-white">
<table class="w-full text-sm table-sticky"><thead><tr><th>Nomor</th><th>Jenis</th><th>Judul</th><th>Proyek</th><th class="text-right">Nilai</th><th>Tenggat/Efektif</th><th>Status</th></tr></thead><tbody>
@forelse($changes as $change)
<tr class="cursor-pointer hover:bg-slate-50 dark:hover:!bg-slate-800" onclick="location.href='/admin/contracts/{{ $change->id }}'">
<td class="font-mono text-xs">{{ $change->number }}</td>
<td>{{ $types[$change->type] ?? $change->type }}</td>
<td class="max-w-[240px] truncate">{{ $change->title }}</td>
<td>{{ $change->project?->code ?? '-' }}</td>
<td class="text-right font-mono">{{ number_format((float) $change->amount, 0, ',', '.') }}@if((int) $change->days_extension > 0)<span class="ml-1 text-xs text-slate-500">+{{ $change->days_extension }} hr</span>@endif</td>
<td>{{ $change->effective_date?->format('d/m/Y') ?? '-' }}</td>
<td>@php($badge = match ($change->status) { 'approved' => 'posted', 'rejected' => 'exception', 'pending_approval' => 'pending_approval', default => 'draft' })<x-ui.badge :status="$badge" :label="str_replace('_', ' ', $change->status)" /></td>
</tr>
@empty
<tr><td colspan="7" class="p-8 text-center text-slate-500">Belum ada perubahan kontrak. Gunakan formulir di bawah untuk membuat draft pertama.</td></tr>
@endforelse
</tbody></table>
</div>

<form method="post" action="/admin/contracts" id="create-change" class="mt-8 grid gap-3 rounded-2xl border bg-white p-6 no-print lg:grid-cols-3">@csrf
<h2 class="font-bold lg:col-span-3">Buat Perubahan Kontrak</h2>
<select name="project_id" class="rounded-xl border p-3"><option value="">— Tanpa proyek —</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->code }} — {{ $project->name }}</option>@endforeach</select>
<select name="type" required class="rounded-xl border p-3">@foreach($types as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
<input name="number" placeholder="Nomor (mis. VO-2026-001)" required class="rounded-xl border p-3">
<input name="title" placeholder="Judul / pokok perubahan" required class="rounded-xl border p-3 lg:col-span-2">
<input name="amount" type="number" step="0.01" min="0" placeholder="Nilai (Rp 0 bila EOT saja)" required class="rounded-xl border p-3">
<input name="days_extension" type="number" min="0" placeholder="Perpanjangan (hari)" class="rounded-xl border p-3">
<input name="effective_date" type="date" class="rounded-xl border p-3">
<textarea name="description" placeholder="Uraian, dasar permintaan, referensi klausa kontrak…" class="rounded-xl border p-3 lg:col-span-3"></textarea>
<button class="rounded-xl bg-[var(--brand-primary)] p-3 text-white">Simpan Draft</button>
</form>

@if($workflows->isEmpty())
<div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800 no-print">Belum ada workflow <strong>contract_change</strong> aktif. Buat di <a href="/admin/approvals" class="font-bold underline">Approval Center</a> dengan document type <code>contract_change</code> agar dokumen dapat diajukan.</div>
@endif
</section>
</x-layouts.app>
