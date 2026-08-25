<x-layouts.app title="Jurnal Berulang">
<div class="page-container">
<x-ui.page-header title="Jurnal Berulang" subtitle="Template jurnal tetap (mis. sewa kantor, cicilan alat) yang diposting otomatis tiap bulan oleh scheduler pada tanggal terjadwal. Idempotent per periode — aman dijalankan ulang." />
@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-3">
<x-ui.stat-card label="Total Template" value="{{ number_format($stats['total']) }}" icon="swap" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Aktif" value="{{ number_format($stats['active']) }}" icon="play" tone="success" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Jatuh Tempo" value="{{ number_format($stats['due']) }}" icon="clock" tone="{{ $stats['due'] > 0 ? 'warning' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
</div>

<button type="button" class="btn-brand mt-4 inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="recurring-drawer" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />Buat Template</button>
<p class="mt-3 text-sm text-slate-500">Scheduler: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px]">journals:post-recurring</code> harian pukul 01:00.</p>

<div class="mt-8 overflow-x-auto rounded-2xl border bg-white"><table class="w-full min-w-[720px] text-sm"><thead><tr><th>Nama</th><th>Deskripsi</th><th>Tgl Posting</th><th>Run Berikutnya</th><th>Terakhir</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($templates as $template)
<tr class="h-[56px] border-t"><td><strong>{{ $template->name }}</strong><span class="block text-xs text-slate-500">{{ count($template->lines) }} baris · total Rp {{ number_format(array_sum(array_column($template->lines, 'debit')), 0, ',', '.') }}</span></td><td>{{ \Illuminate\Support\Str::limit($template->description, 60) }}</td><td>{{ $template->day_of_month }}</td><td>{{ $template->next_run_at->format('d/m/Y') }}@if($template->next_run_at->isPast() && $template->status === 'active') <span class="font-bold text-amber-600">· due</span>@endif</td><td>{{ $template->last_posted_at?->format('d/m/Y') ?? '—' }}</td><td><span class="chip chip-default {{ $template->status === 'active' ? 'bg-emerald-50 text-emerald-700' : '' }}">{{ strtoupper($template->status) }}</span></td><td><div class="flex gap-1">@if(auth()->user()->hasPermission('finance.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))<form method="post" action="/admin/recurring-journals/{{ $template->id }}/toggle">@csrf<button class="rounded-lg border px-2.5 py-1.5 text-xs font-bold">{{ $template->status === 'active' ? 'Jeda' : 'Aktifkan' }}</button></form>@endif @if($template->status === 'active' && auth()->user()->hasPermission('accounting.post', app(\App\Support\Tenancy\CurrentCompany::class)->id()))<form method="post" action="/admin/recurring-journals/{{ $template->id }}/run">@csrf<button class="rounded-lg bg-slate-800 px-2.5 py-1.5 text-xs font-bold text-white">Posting Sekarang</button></form>@endif</div></td></tr>
@empty<tr><td colspan="7" class="p-8 text-center">Belum ada template jurnal berulang.</td></tr>@endforelse
</tbody></table></div>
</div>

@if(auth()->user()->hasPermission('finance.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<x-ui.drawer id="recurring-drawer" title="Buat Template Jurnal Berulang" description="Baris harus seimbang (total debit = kredit). Tanggal 1-28 agar aman semua bulan.">
<form method="post" action="/admin/recurring-journals" class="grid gap-4">@csrf
<x-ui.field label="Nama template" name="name" required><input name="name" required placeholder="mis. Sewa Kantor Bulanan" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Deskripsi jurnal" name="description" required><input name="description" required placeholder="muncul sebagai deskripsi jurnal" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Tanggal posting tiap bulan" name="day_of_month"><input type="number" min="1" max="28" name="day_of_month" value="1" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div id="rj-lines" class="grid gap-2">
<div class="grid grid-cols-[1fr_100px_100px_120px] gap-2 rj-row"><select name="account_id[]" class="rounded-xl border p-2 text-sm"><option value="">Pilih akun</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>@endforeach</select><input type="number" step=".01" min="0" name="debit[]" placeholder="Debit" class="rounded-xl border p-2 text-sm"><input type="number" step=".01" min="0" name="credit[]" placeholder="Kredit" class="rounded-xl border p-2 text-sm"><select name="project_id_row[]" class="rounded-xl border p-2 text-sm"><option value="">Tanpa proyek</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->code }}</option>@endforeach</select></div>
</div>
<button type="button" onclick="const c=document.getElementById('rj-lines');const r=c.firstElementChild.cloneNode(true);r.querySelectorAll('input').forEach(i=>i.value='');c.appendChild(r)" class="min-h-[38px] rounded-xl border px-3 text-sm font-bold">+ Baris</button>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan Template</button></div>
</form>
</x-ui.drawer>
@endif
</x-layouts.app>
