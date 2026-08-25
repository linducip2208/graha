<x-layouts.app title="Beban Dibayar Dimuka">
<div class="page-container">
<x-ui.page-header title="Beban Dibayar Dimuka (Prepaid Expense)" subtitle="Pembayaran di muka diamortisasi otomatis per bulan: Dr Beban / Kr Prepaid via mapping prepaid_amortization. Idempotent per periode; bulan terakhir menyerap sisa pembulatan; status completed saat seluruh periode terposting." />
@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-4 flex flex-wrap gap-3">
<div class="rounded-2xl border bg-white px-5 py-3"><div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Total Register</div><div class="text-2xl font-black tabular-nums">{{ $stats['total'] }}</div></div>
<div class="rounded-2xl border bg-white px-5 py-3"><div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Aktif</div><div class="text-2xl font-black tabular-nums text-emerald-700">{{ $stats['active'] }}</div></div>
<div class="rounded-2xl border bg-white px-5 py-3"><div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Ada Periode Jatuh Tempo</div><div class="text-2xl font-black tabular-nums {{ $stats['due'] > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ $stats['due'] }}</div></div>
</div>

<div class="mt-4 flex flex-wrap items-center gap-2">
<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="prepaid-drawer" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />Daftarkan Prepaid</button>
@if(auth()->user()->hasPermission('accounting.post', app(\App\Support\Tenancy\CurrentCompany::class)->id()) && $stats['due'] > 0)
<form method="post" action="/admin/prepaid-expenses/post-due">@csrf
<button type="submit" class="inline-flex min-h-[42px] items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 text-sm font-bold text-amber-800 hover:bg-amber-100"><x-ui.icon name="check" class="h-4 w-4" />Posting Semua Periode Jatuh Tempo</button>
</form>
@endif
</div>

@forelse($prepaids as $p)
@php($posted = $p->status === 'completed' ? $p->period_count : ($p->last_posted_period === null ? 0 : $p->first_period_date->startOfMonth()->diffInMonths($p->last_posted_period->startOfMonth()) + 1))
@php($dueCount = count($p->duePeriods()))
@php($remaining = $p->status === 'completed' ? 0 : (float)$p->total_amount - ((float)$p->monthlyAmount() * $posted))
<section class="mt-6 rounded-2xl border bg-white p-5">
<div class="flex flex-wrap items-start justify-between gap-3">
<div>
<h3 class="font-black">{{ $p->name }} @if($p->vendor_ref)<span class="text-sm font-normal text-slate-500">· {{ $p->vendor_ref }}</span>@endif</h3>
<p class="mt-0.5 text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($p->first_period_date)->isoFormat('MMM Y') }} → {{ \Illuminate\Support\Carbon::parse($p->finalPeriodDate())->isoFormat('MMM Y') }} · {{ number_format((float)$p->monthlyAmount(), 2, ',', '.') }}/bulan @if($p->notes)— {{ $p->notes }}@endif</p>
</div>
<div class="flex items-center gap-2">
@if($dueCount > 0)<span class="rounded-md bg-amber-50 px-2 py-1 text-[11px] font-black text-amber-700">{{ $dueCount }} periode belum diposting</span>@endif
<span class="rounded-md px-2 py-1 text-[11px] font-black {{ $p->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $p->status === 'active' ? 'AKTIF' : 'SELESAI' }}</span>
</div>
</div>
<div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
<div><div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Nilai Total</div><div class="tabular-nums font-bold">Rp {{ number_format((float)$p->total_amount, 2, ',', '.') }}</div></div>
<div><div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Progres Amortisasi</div><div class="font-bold">{{ $posted }} / {{ $p->period_count }} bulan <span class="text-xs font-normal text-slate-500">({{ round($posted / max($p->period_count, 1) * 100) }}%)</span></div></div>
<div><div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Sisa Belum Diamortisasi</div><div class="tabular-nums font-bold">Rp {{ number_format(max(0, $remaining), 2, ',', '.') }}</div></div>
<div><div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Terakhir Diposting</div><div class="font-bold">{{ $p->last_posted_period?->isoFormat('MMM Y') ?? '—' }}</div></div>
</div>
<div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, round($posted / max($p->period_count, 1) * 100)) }}%"></div></div>
</section>
@empty
<div class="mt-8 rounded-2xl border border-dashed bg-white p-8 text-center"><h3 class="font-bold">Belum ada prepaid expense</h3><p class="mt-1 text-sm text-slate-500">Daftarkan pembayaran di muka (sewa, asuransi, langganan) untuk diamortisasi otomatis per bulan.</p></div>
@endforelse
</div>

@if(auth()->user()->hasPermission('finance.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<x-ui.drawer id="prepaid-drawer" title="Daftarkan Prepaid Expense" description="Amortisasi mulai bulan pertama terpilih. Nominal per bulan dibulatkan ke bawah; sisa diserap bulan terakhir. Pastikan mapping prepaid_amortization (expense_debit & prepaid_credit) sudah diisi.">
<form method="post" action="/admin/prepaid-expenses" class="grid gap-4">@csrf
<x-ui.field label="Nama" name="name" required><input type="text" name="name" required maxlength="150" placeholder="Sewa kantor tahunan" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Referensi vendor" name="vendor_ref"><input type="text" name="vendor_ref" maxlength="120" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Nilai total (Rp)" name="total_amount" required><input type="number" step=".01" min="0.01" name="total_amount" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Jumlah periode (bulan amortisasi)" name="period_count" required helper="1–120 bulan"><input type="number" name="period_count" required min="1" max="120" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Bulan pertama" name="first_period_date" required><input type="date" name="first_period_date" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Catatan" name="notes"><textarea name="notes" rows="2" maxlength="500" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan Prepaid</button></div>
</form>
</x-ui.drawer>
@endif
</x-layouts.app>
