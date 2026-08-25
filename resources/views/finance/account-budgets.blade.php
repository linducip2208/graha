<x-layouts.app title="Budget vs Aktual">
<div class="page-container">
<x-ui.page-header title="Budget vs Aktual per Akun" subtitle="Anggaran per akun per periode fiskal dibandingkan mutasi riil jurnal posted. Aktual dinormalkan ke saldo natural (revenue tampil positif). Melebihi budget ditandai." />
@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif @if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<button type="button" class="btn-brand mt-4 inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="budget-drawer" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />Set / Ubah Budget</button>

@forelse($report as $group)
<section class="mt-8">
<h2 class="text-lg font-black">{{ $group['period']->name }} <span class="text-sm font-normal text-slate-500">({{ $group['period']->starts_at->format('d/m/Y') }}–{{ $group['period']->ends_at->format('d/m/Y') }})</span></h2>
<div class="mt-3 overflow-x-auto rounded-2xl border bg-white"><table class="w-full min-w-[680px] text-sm"><thead><tr><th>Akun</th><th>Tipe</th><th class="text-right">Budget</th><th class="text-right">Aktual</th><th class="text-right">Variance</th><th class="text-right">Terpakai</th></tr></thead><tbody>
@foreach($group['rows'] as $row)
<tr class="border-t h-[48px]"><td><strong>{{ $row['budget']->account?->code }}</strong> {{ $row['budget']->account?->name }}</td><td>{{ ucfirst($row['budget']->account->type) }}</td><td class="text-right tabular-nums">{{ number_format((float)$row['budget']->amount, 2, ',', '.') }}</td><td class="text-right tabular-nums">{{ number_format((float)$row['actual'], 2, ',', '.') }}</td><td class="text-right tabular-nums font-bold {{ $row['over'] ? 'text-red-600' : 'text-emerald-700' }}">{{ number_format((float)$row['variance'], 2, ',', '.') }}</td><td class="text-right tabular-nums">@if($row['usage'] !== null){{ round((float)$row['usage'] * 100, 1) }}%@else—@endif @if($row['over'])<span class="ml-1 rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-black text-red-700">OVER</span>@endif</td></tr>
@endforeach
<tr class="font-black border-t"><td colspan="2">TOTAL PERIODE</td><td class="text-right tabular-nums">{{ number_format($group['total_budget'], 2, ',', '.') }}</td><td class="text-right tabular-nums">{{ number_format((float)$group['total_actual'], 2, ',', '.') }}</td><td colspan="2"></td></tr>
</tbody></table></div>
</section>
@empty
<div class="mt-8 rounded-2xl border border-dashed bg-white p-8 text-center"><h3 class="font-bold">Belum ada budget</h3><p class="mt-1 text-sm text-slate-500">Set anggaran per akun untuk memantau realisasi terhadap rencana.</p></div>
@endforelse
</div>

@if(auth()->user()->hasPermission('finance.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<x-ui.drawer id="budget-drawer" title="Set Budget Akun" description="Satu anggaran per akun per periode fiskal. Set ulang pada kombinasi sama = update nilai.">
<form method="post" action="/admin/account-budgets" class="grid gap-4">@csrf
<x-ui.field label="Akun" name="account_id" required><select name="account_id" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih akun</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }} ({{ $a->type }})</option>@endforeach</select></x-ui.field>
<x-ui.field label="Periode fiskal" name="fiscal_period_id" required><select name="fiscal_period_id" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih periode</option>@foreach($periods as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Nilai anggaran (Rp)" name="amount"><input type="number" step=".01" name="amount" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Catatan" name="notes"><textarea name="notes" rows="2" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></textarea></x-ui.field>
<div class="flex justify-end gap-2 pt-2"><button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button><button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan Budget</button></div>
</form>
</x-ui.drawer>
@endif
</x-layouts.app>
