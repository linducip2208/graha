<x-layouts.app title="Organisasi">
<div class="page-container">
<x-ui.page-header title="Organisasi" subtitle="Kelola struktur perusahaan, cabang, departemen, role, dan akses.">
<x-slot:actions>
@if(auth()->user()->hasPermission('organization.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<button type="button" class="inline-flex min-h-[42px] items-center gap-2 rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-open="branch-create-drawer" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />Cabang</button>
<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="department-create-drawer" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />Departemen</button>
@endif
<a href="/admin/organization/roles" class="inline-flex min-h-[42px] items-center gap-2 rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]"><x-ui.icon name="user" class="h-4 w-4" />Kelola Role</a>
</x-slot:actions>
</x-ui.page-header>

@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

{{-- KPI real --}}
<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-4">
<x-ui.stat-card label="Total Cabang" value="{{ number_format($branches->total()) }}" icon="building" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Total Departemen" value="{{ number_format($departments->total()) }}" icon="grid" tone="info" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Total Role" value="{{ number_format($roleCount) }}" icon="user" tone="violet" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Member Aktif" value="{{ number_format($memberCount) }}" icon="check" tone="success" :value-class="'text-[24px] leading-tight'" />
</div>

<div class="mt-8 grid gap-8 xl:grid-cols-2">
{{-- ===== CABANG ===== --}}
<article class="overflow-hidden rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]">
<header class="flex items-center justify-between border-b px-5 py-3.5" style="border-color:var(--border-subtle)"><h2 class="text-sm font-black tracking-tight">Cabang</h2><span class="text-xs text-slate-400">{{ number_format($branches->total()) }} cabang</span></header>
<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th>Kode</th><th>Nama</th><th>Status</th></tr></thead><tbody>
@forelse($branches as $branch)
<tr class="h-[52px]"><td class="font-mono font-bold">{{ $branch->code }}</td><td>{{ $branch->name }}</td><td><span class="chip {{ $branch->is_active ? 'chip-approved' : 'chip-default' }}">{{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}</span></td></tr>
@empty
<tr><td colspan="3" class="p-2"><x-ui.empty icon="building" title="Belum ada cabang" description="Tambahkan cabang pertama untuk memulai struktur organisasi." /></td></tr>
@endforelse
</tbody></table></div>
@if($branches->hasPages())<div class="border-t px-5 py-3 text-sm" style="border-color:var(--border-subtle)">{{ $branches->links() }}</div>@endif
</article>

{{-- ===== DEPARTEMEN ===== --}}
<article class="overflow-hidden rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]">
<header class="flex items-center justify-between border-b px-5 py-3.5" style="border-color:var(--border-subtle)"><h2 class="text-sm font-black tracking-tight">Departemen</h2><span class="text-xs text-slate-400">{{ number_format($departments->total()) }} departemen</span></header>
<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th>Kode</th><th>Nama</th><th>Cabang</th></tr></thead><tbody>
@forelse($departments as $department)
<tr class="h-[52px]"><td class="font-mono font-bold">{{ $department->code }}</td><td>{{ $department->name }}</td><td>{{ $department->branch?->name ?? 'Semua cabang' }}</td></tr>
@empty
<tr><td colspan="3" class="p-2"><x-ui.empty icon="grid" title="Belum ada departemen" description="Departemen membantu menugaskan pemilik risiko dan penanggung jawab QMS." /></td></tr>
@endforelse
</tbody></table></div>
@if($departments->hasPages())<div class="border-t px-5 py-3 text-sm" style="border-color:var(--border-subtle)">{{ $departments->links() }}</div>@endif
</article>
</div>
</div>

@if(auth()->user()->hasPermission('organization.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<x-ui.drawer id="branch-create-drawer" title="Tambah Cabang" description="Cabang/unit kerja regional perusahaan.">
<form method="post" action="/admin/branches" class="grid gap-4">@csrf
<x-ui.field label="Kode cabang" name="code" required><input name="code" required maxlength="30" placeholder="mis. KLT-01" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Nama cabang" name="name" required><input name="name" required maxlength="150" placeholder="mis. Kantor Cabang Surabaya" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div class="flex justify-end gap-2 pt-2">
<button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button>
<button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan Cabang</button>
</div>
</form>
</x-ui.drawer>

<x-ui.drawer id="department-create-drawer" title="Tambah Departemen" description="Departemen dapat terikat pada satu cabang atau berlaku lintas cabang.">
<form method="post" action="/admin/departments" class="grid gap-4">@csrf
<x-ui.field label="Kode departemen" name="code" required><input name="code" required maxlength="30" placeholder="mis. ENG" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Nama departemen" name="name" required><input name="name" required maxlength="150" placeholder="mis. Engineering" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Cabang" name="branch_id" hint="Kosongkan untuk berlaku lintas cabang">
<select name="branch_id" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Lintas cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->code }} — {{ $branch->name }}</option>@endforeach</select>
</x-ui.field>
<div class="flex justify-end gap-2 pt-2">
<button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button>
<button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan Departemen</button>
</div>
</form>
</x-ui.drawer>
@endif
</x-layouts.app>
