<x-layouts.app title="Chart of Accounts">
<div class="page-container">
<x-ui.page-header title="Chart of Accounts" subtitle="Struktur akun perusahaan untuk seluruh posting ERP."><x-slot:actions>
@if(auth()->user()->hasPermission('finance.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="finance-account-drawer" aria-haspopup="dialog" aria-expanded="false"><x-ui.icon name="plus" class="h-4 w-4" />Akun Baru</button>
@endif
</x-slot:actions>
</x-ui.page-header>

@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

{{-- KPI dari data riil koleksi akun --}}
@php($all = \App\Models\Account::where('company_id', app(\App\Support\Tenancy\CurrentCompany::class)->id())->get())
<div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
<x-ui.stat-card label="Total Akun" value="{{ number_format($all->count()) }}" icon="banknote" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Aktif" value="{{ number_format($all->where('is_active', true)->count()) }}" icon="check" tone="success" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Asset" value="{{ number_format($all->where('type', 'asset')->count()) }}" icon="briefcase" tone="info" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Liability" value="{{ number_format($all->where('type', 'liability')->count()) }}" icon="percent" tone="warning" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Equity" value="{{ number_format($all->where('type', 'equity')->count()) }}" icon="building" tone="violet" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Revenue / Expense" value="{{ number_format($all->where('type', 'revenue')->count()).' / '.number_format($all->where('type', 'expense')->count()) }}" icon="chart" tone="info" :value-class="'text-[18px] leading-tight'" />
</div>

<x-ui.filter-bar class="mt-6">
<input type="search" name="q" value="{{ request('q') }}" placeholder="Cari kode / nama akun…" class="min-w-[220px] flex-1 rounded-xl border border-[var(--border-default)] bg-[var(--surface-input)] px-3.5 text-sm sm:max-w-xs">
<select name="type" class="rounded-xl border border-[var(--border-default)] bg-[var(--surface-input)] px-3 text-sm">
<option value="">Semua Tipe</option>
@foreach(['asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'revenue' => 'Revenue', 'expense' => 'Expense'] as $v => $label)
<option value="{{ $v }}" @selected(request('type') === $v)>{{ $label }}</option>
@endforeach
</select>
<select name="status" class="rounded-xl border border-[var(--border-default)] bg-[var(--surface-input)] px-3 text-sm">
<option value="">Semua Status</option>
<option value="active" @selected(request('status') === 'active')>Aktif</option>
<option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
</select>
<button class="inline-flex min-h-[42px] items-center rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]">Terapkan</button>
<a href="/admin/finance/accounts" class="inline-flex min-h-[42px] items-center rounded-xl px-3 text-sm font-bold text-[var(--text-muted)] hover:text-[var(--brand-primary)]">Reset</a>
<span class="ml-auto self-center text-xs text-slate-400">{{ number_format($accounts->total()) }} akun</span>
</x-ui.filter-bar>

<article class="mt-4 overflow-hidden rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]">
<div class="overflow-x-auto">
<table class="w-full min-w-[650px] text-sm">
<thead><tr><th>Kode</th><th>Nama Akun</th><th>Tipe</th><th>Saldo Normal</th><th>Status</th></tr></thead>
<tbody>
@forelse($accounts as $account)
<tr class="h-[52px]">
<td class="font-mono font-bold">{{ $account->code }}</td>
<td>{{ $account->name }}</td>
<td><span class="chip chip-default">{{ strtoupper($account->type) }}</span></td>
<td>{{ strtoupper($account->normal_balance) }}</td>
<td><span class="chip {{ $account->is_active ? 'chip-approved' : 'chip-default' }}">{{ $account->is_active ? 'AKTIF' : 'NONAKTIF' }}</span></td>
</tr>
@empty
<tr><td colspan="5" class="p-2"><x-ui.empty icon="banknote" title="Belum ada akun" description="Tambahkan akun pertama untuk memulai struktur buku besar perusahaan." /></td></tr>
@endforelse
</tbody>
</table>
</div>
@if($accounts->hasPages())<div class="border-t px-5 py-3 text-sm" style="border-color:var(--border-subtle)">{{ $accounts->links() }}</div>@endif
</article>
</div>

@if(auth()->user()->hasPermission('finance.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<x-ui.drawer id="finance-account-drawer" title="Akun Baru" description="Akun masuk ke struktur buku besar dan dipakai seluruh mapping posting.">
<form method="post" action="/admin/finance/accounts" class="grid gap-4">@csrf
<x-ui.form-section title="Identitas Akun">
<div class="grid gap-4 sm:grid-cols-2">
<x-ui.field label="Kode akun" name="code" required><input name="code" required maxlength="20" placeholder="mis. 1-1000" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Nama akun" name="name" required><input name="name" required maxlength="150" placeholder="mis. Kas & Bank" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Tipe" name="type" required>
<select name="type" class="w-full rounded-xl border border-[var(--border-default)] px-3.5">@foreach(['asset', 'liability', 'equity', 'revenue', 'expense'] as $v)<option value="{{ $v }}">{{ ucfirst($v) }}</option>@endforeach</select>
</x-ui.field>
<x-ui.field label="Saldo normal" name="normal_balance" required>
<select name="normal_balance" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="debit">Debit</option><option value="credit">Kredit</option></select>
</x-ui.field>
</div>
</x-ui.form-section>
<div class="flex justify-end gap-2 pt-2">
<button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button>
<button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Tambah Akun</button>
</div>
</form>
</x-ui.drawer>
@endif
</x-layouts.app>
