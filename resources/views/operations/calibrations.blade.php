<x-layouts.app title="Register Kalibrasi">
<div class="page-container">
<x-ui.page-header title="Register Kalibrasi Alat Ukur" subtitle="ISO 9001 clause 7.1.5 — alat ukur yang dipakai verifikasi mutu wajib terkalibrasi dan terlacak. Status dihitung otomatis dari tanggal jatuh tempo." />
@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
<x-ui.stat-card label="Total Record" value="{{ number_format($total) }}" icon="scale" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Overdue" value="{{ number_format($grouped['overdue']->count()) }}" icon="triangle-alert" tone="{{ $grouped['overdue']->isNotEmpty() ? 'danger' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Jatuh Tempo ≤ 30 hari" value="{{ number_format($grouped['due_soon']->count()) }}" icon="clock" tone="{{ $grouped['due_soon']->isNotEmpty() ? 'warning' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="OK" value="{{ number_format($grouped['ok']->count()) }}" icon="check" tone="success" :value-class="'text-[24px] leading-tight'" />
</div>

<button type="button" class="btn-brand mt-4 inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="calibration-drawer" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />Catat Kalibrasi</button>

<div class="mt-8 space-y-8">
@foreach(['overdue' => 'Overdue — Segera Tarik dari Penggunaan', 'due_soon' => 'Jatuh Tempo ≤ 30 Hari', 'ok' => 'Dalam Masa Sah'] as $status => $label)
<section>
<h2 class="text-lg font-black">{{ $label }}</h2>
<div class="mt-3 overflow-x-auto rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]"><table class="w-full min-w-[720px] text-sm"><thead><tr><th>Alat</th><th>Equipment</th><th>Terkalibrasi</th><th>Jatuh Tempo</th><th>Sertifikat</th><th>Lembaga</th><th>Hasil</th></tr></thead><tbody>
@forelse($grouped[$status] as $record)
<tr class="h-[52px]"><td class="font-bold">{{ $record->instrument_name }} @if($record->serial_number)<span class="font-mono text-xs text-slate-500">SN {{ $record->serial_number }}</span>@endif</td><td>{{ $record->equipment?->code }}</td><td>{{ $record->calibrated_at->format('d/m/Y') }}</td><td>{{ $record->next_due_at->format('d/m/Y') }}</td><td class="font-mono">{{ $record->certificate_no ?? '—' }}</td><td>{{ $record->provider ?? '—' }}</td><td><span class="chip chip-default">{{ strtoupper($record->result) }}</span></td></tr>
@empty
<tr><td colspan="7" class="p-2"><x-ui.empty icon="check" title="Tidak ada record" description="{{ $status === 'overdue' ? 'Semua alat dalam masa kalibrasi berlaku.' : 'Belum ada data pada kelompok ini.' }}" /></td></tr>
@endforelse
</tbody></table></div>
</section>
@endforeach
</div>
</div>

<x-ui.drawer id="calibration-drawer" title="Catat Hasil Kalibrasi" description="Rekam hasil kalibrasi alat ukur oleh laboratorium/penyedia jasa. Jatuh tempo wajib setelah tanggal kalibrasi.">
<form method="post" action="/admin/calibrations" class="grid gap-4">@csrf
<x-ui.field label="Equipment" name="equipment_id" required><select name="equipment_id" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih equipment</option>@foreach($equipments as $equipment)<option value="{{ $equipment->id }}">{{ $equipment->code }} — {{ $equipment->name }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Nama alat ukur" name="instrument_name" required><input name="instrument_name" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div class="grid grid-cols-2 gap-3">
<x-ui.field label="Serial number" name="serial_number"><input name="serial_number" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="No. sertifikat" name="certificate_no"><input name="certificate_no" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
</div>
<div class="grid grid-cols-2 gap-3">
<x-ui.field label="Tanggal kalibrasi" name="calibrated_at" required><input type="date" name="calibrated_at" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Jatuh tempo berikutnya" name="next_due_at" required><input type="date" name="next_due_at" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
</div>
<div class="grid grid-cols-2 gap-3">
<x-ui.field label="Lembaga kalibrasi" name="provider"><input name="provider" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Hasil" name="result"><select name="result" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="pass">Pass</option><option value="adjust">Adjust</option><option value="fail">Fail</option></select></x-ui.field>
</div>
<x-ui.field label="Catatan" name="notes"><textarea name="notes" rows="2" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan</button></div>
</form>
</x-ui.drawer>
</x-layouts.app>
