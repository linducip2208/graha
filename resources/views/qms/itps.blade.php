<x-layouts.app title="Inspection & Test Plan">
<div class="page-container">
<x-ui.page-header title="Inspection & Test Plan (ITP)" subtitle="Rencana inspeksi per proyek/titik pile. Hold point tanpa hasil pass menahan penutupan ITP. Pemeriksa wajib berbeda dari perekam hasil." />
@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
<x-ui.stat-card label="Total ITP" value="{{ number_format($stats['total']) }}" icon="document" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Aktif" value="{{ number_format($stats['active']) }}" icon="play" tone="success" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Hold Point Terbuka" value="{{ number_format($stats['hold_open']) }}" icon="pause" tone="{{ $stats['hold_open'] > 0 ? 'warning' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Inspeksi Tercatat" value="{{ number_format($stats['inspections']) }}" icon="check" tone="info" :value-class="'text-[24px] leading-tight'" />
</div>

<button type="button" class="btn-brand mt-4 inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="itp-drawer" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />Buat ITP</button>

<div class="mt-8 space-y-5">
@forelse($plans as $plan)
<x-ui.card>
<div class="flex flex-wrap items-start justify-between gap-3">
<div>
<strong class="font-mono">{{ $plan->number }}</strong> — {{ $plan->title }}
<p class="text-sm text-slate-500">{{ $plan->project?->code }}@if($plan->boredPile) · Pile {{ $plan->boredPile?->pile_code ?? $plan->boredPile?->id }}@endif · {{ $plan->items->count() }} item · disiapkan oleh {{ \App\Models\User::find($plan->prepared_by)?->name ?? '—' }}</p>
</div>
<div class="flex flex-wrap items-center gap-2">
<span class="chip chip-default">{{ strtoupper($plan->status) }}</span>
@if($plan->status === 'active')
<form method="post" action="/admin/itps/{{ $plan->id }}/close">@csrf<button data-confirm="Tutup ITP ini? Hold point tanpa hasil pass akan menolak penutupan." class="rounded-xl border border-[var(--border-default)] px-3 py-1.5 text-xs font-bold hover:bg-[var(--surface-muted)]">Tutup ITP</button></form>
@endif
</div>
</div>
<div class="mt-4 overflow-x-auto"><table class="w-full min-w-[640px] text-sm"><thead><tr><th>Tahap</th><th>Metode</th><th>Kriteria</th><th>Tipe</th><th>Status Hold</th><th>Catat Inspeksi</th></tr></thead><tbody>
@foreach($plan->items as $item)
<tr class="border-t"><td class="py-2 font-semibold">{{ $item->stage }}</td><td>{{ $item->method }}</td><td class="max-w-64 truncate" title="{{ $item->acceptance_criteria }}">{{ $item->acceptance_criteria }}</td><td><span class="chip chip-default">{{ strtoupper($item->checkpoint_type) }}</span></td><td>@if($item->checkpoint_type === 'hold'){{ $item->holdOpen() ? '🔴 Terbuka' : '✅ Pass' }}@else — @endif</td><td>
<form method="post" action="/admin/itp-items/{{ $item->id }}/inspections" class="flex flex-wrap gap-1.5">@csrf
<input type="date" name="performed_at" value="{{ today()->toDateString() }}" required class="rounded-lg border p-1.5 text-xs">
<select name="result" required class="rounded-lg border p-1.5 text-xs"><option value="pending">Pending</option><option value="pass">Pass</option><option value="fail">Fail</option></select>
<select name="inspector_id" required class="rounded-lg border p-1.5 text-xs"><option value="">Pemeriksa</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
<input name="measured_value" placeholder="Nilai ukur" class="w-24 rounded-lg border p-1.5 text-xs">
<input name="notes" placeholder="Catatan (wajib bila fail)" class="w-36 rounded-lg border p-1.5 text-xs">
<button class="rounded-lg bg-slate-800 px-2.5 py-1.5 text-xs font-bold text-white">Simpan</button>
</form>
@if($item->inspections->isNotEmpty())<p class="mt-1.5 text-xs text-slate-500">Terakhir: {{ $item->inspections->first()->performed_at->format('d/m/Y') }} · {{ strtoupper($item->inspections->first()->result) }} oleh {{ \App\Models\User::find($item->inspections->first()->inspector_id)?->name ?? '—' }}</p>@endif
</td></tr>
@endforeach
</tbody></table></div>
</x-ui.card>
@empty
<div class="rounded-2xl border border-dashed p-8 text-center"><h3 class="font-bold">Belum ada ITP</h3><p class="mt-1 text-sm text-slate-500">Buat rencana inspeksi sebelum pekerjaan dimulai agar titik henti/witness terdefinisi.</p></div>
@endforelse
</div>
</div>

<x-ui.drawer id="itp-drawer" title="Buat Inspection & Test Plan" description="Satu baris = satu tahap inspeksi. Hold point = pekerjaan tidak boleh lanjut tanpa inspeksi lolos.">
<form method="post" action="/admin/itps" class="grid gap-4">@csrf
<x-ui.field label="Proyek" name="project_id" required><select name="project_id" required id="itp-project" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih proyek</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->code }} — {{ $project->name }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Judul ITP" name="title" required><input name="title" required placeholder="mis. ITP Bored Pile Fase 1" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div id="itp-items" class="grid gap-3">
<div class="grid grid-cols-[1fr_1fr_1fr_120px] gap-2 itp-row"><input name="stage[]" required placeholder="Tahap" class="rounded-xl border p-2.5 text-sm"><input name="method[]" required placeholder="Metode uji" class="rounded-xl border p-2.5 text-sm"><input name="acceptance_criteria[]" required placeholder="Kriteria terima" class="rounded-xl border p-2.5 text-sm"><select name="checkpoint_type[]" class="rounded-xl border p-2.5 text-sm"><option value="witness">Witness</option><option value="hold">Hold</option><option value="review">Review</option></select></div>
</div>
<button type="button" onclick="const c=document.getElementById('itp-items');const r=c.firstElementChild.cloneNode(true);r.querySelectorAll('input').forEach(i=>i.value='');c.appendChild(r)" class="min-h-[38px] rounded-xl border px-3 text-sm font-bold">+ Tambah Item</button>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan ITP</button></div>
</form>
</x-ui.drawer>
</x-layouts.app>
