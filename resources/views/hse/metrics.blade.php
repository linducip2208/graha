<x-layouts.app title="KPI Keselamatan (FR/SR)">
<div class="page-container">
<x-ui.page-header title="KPI Keselamatan — FR / SR / TRIR" subtitle="Dihitung dari data nyata: incident lost-time & hari hilang dibagi jam kerja terinput. Tanpa exposure log periode ini, KPI tidak ditampilkan — bukan dikarang." />
@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<button type="button" class="btn-brand mt-4 inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="exposure-drawer" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />Input Jam Kerja Bulanan</button>

@if($error)
<div class="mt-6 rounded-2xl border border-dashed bg-white p-8 text-center"><h3 class="font-bold">Belum ada data exposure</h3><p class="mt-1 text-sm text-slate-500">{{ $error }}. Input man-hours bulanan dari payroll/timesheet agar KPI dapat dihitung.</p></div>
@else
<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
<x-ui.stat-card label="Jam Kerja (YTD)" value="{{ number_format((float)$summary['man_hours'],0,',','.') }}" icon="clock" tone="brand" :value-class="'text-[18px] leading-tight'" />
<x-ui.stat-card label="Lost-Time Incident" value="{{ number_format($summary['lost_time_incidents']) }} ({{ number_format($summary['lost_days']) }} hari hilang)" icon="triangle-alert" tone="{{ $summary['lost_time_incidents'] > 0 ? 'danger' : 'success' }}" :value-class="'text-[16px] leading-tight'" />
<x-ui.stat-card label="FR — per juta jam" value="{{ $summary['fr'] }}" icon="chart" tone="{{ bccomp($summary['fr'],'0',2) === 1 ? 'warning' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="SR — per juta jam" value="{{ $summary['sr'] }}" icon="chart" tone="{{ bccomp($summary['sr'],'0',2) === 1 ? 'warning' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
</div>
<p class="mt-3 text-xs text-slate-500">TRIR (high+fatal sebagai recordable): {{ $summary['trir'] }} per juta jam · Periode {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }}–{{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</p>

<div class="mt-8 overflow-x-auto rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]"><table class="w-full min-w-[560px] text-sm"><thead><tr><th>Periode</th><th>Jam Kerja</th><th>Rata-rata Personil</th><th>Catatan</th></tr></thead><tbody>
@foreach($summary['months'] as $month)
<tr class="h-[52px]"><td class="font-bold">{{ $month->period_month->translatedFormat('F Y') }}</td><td>{{ number_format((float)$month->man_hours,2,',','.') }}</td><td>{{ $month->avg_headcount ?? '—' }}</td><td>{{ $month->notes ?? '—' }}</td></tr>
@endforeach
</tbody></table></div>
@endif

<h2 class="mt-10 text-lg font-black">Incident dengan Hari Hilang</h2>
<div class="mt-3 overflow-x-auto rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]"><table class="w-full min-w-[560px] text-sm"><thead><tr><th>Nomor</th><th>Jenis/Severity</th><th>Waktu</th><th>Lost Time</th><th>Status</th></tr></thead><tbody>
@forelse($incidents as $incident)
<tr class="h-[52px]"><td class="font-mono font-bold">{{ $incident->number }}</td><td>{{ strtoupper($incident->type) }} / {{ strtoupper($incident->severity) }}</td><td>{{ $incident->occurred_at->format('d/m/Y H:i') }}</td><td>@if($incident->is_lost_time)<span class="font-bold text-red-700">YA · {{ $incident->lost_days }} hari</span>@else Tidak @endif</td><td>{{ strtoupper($incident->status) }}</td></tr>
@empty
<tr><td colspan="5" class="p-2"><x-ui.empty icon="check" title="Belum ada incident" description="Data incident muncul setelah pelaporan di tab Incident." /></td></tr>
@endforelse
</tbody></table></div>
</div>

@if(auth()->user()->hasPermission('hse.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<x-ui.drawer id="exposure-drawer" title="Input Jam Kerja Bulanan" description="Sumber man-hours untuk KPI FR/SR. Ambil dari payroll/timesheet, bukan estimasi. Input ulang bulan sama = update data.">
<form method="post" action="/admin/hse/exposure-logs" class="grid gap-4">@csrf
<x-ui.field label="Periode (bulan)" name="period_month" required><input type="date" name="period_month" required value="{{ now()->startOfMonth()->toDateString() }}" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div class="grid grid-cols-2 gap-3">
<x-ui.field label="Total jam kerja" name="man_hours" required><input type="number" step=".01" min=".01" name="man_hours" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Rata-rata personil" name="avg_headcount"><input type="number" min="0" name="avg_headcount" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
</div>
<x-ui.field label="Catatan" name="notes"><textarea name="notes" rows="2" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan Exposure</button></div>
</form>
</x-ui.drawer>
@endif
</x-layouts.app>
