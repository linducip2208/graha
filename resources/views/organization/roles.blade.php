<x-layouts.app title="Role & Permission">
<div class="page-container">
<x-ui.page-header title="Role & Permission" subtitle="Akses menu & aksi ditentukan role per perusahaan. Role sistem tidak dapat diubah. Setiap perubahan tercatat di audit trail.">
<x-slot:actions>
@if(auth()->user()->hasPermission('organization.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<button type="button" class="btn-brand inline-flex min-h-[42px] items-center gap-2 rounded-xl px-4 text-sm font-bold shadow-sm" data-drawer-open="role-create-drawer" aria-haspopup="dialog"><x-ui.icon name="plus" class="h-4 w-4" />Role Baru</button>
@endif
</x-slot:actions>
</x-ui.page-header>

@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid gap-5 lg:grid-cols-[300px_1fr]">
{{-- ===== LEFT: ROLE LIST ===== --}}
<div class="space-y-4 no-print lg:sticky lg:top-24 lg:self-start">
<div class="overflow-hidden rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]">
<header class="border-b px-4 py-3 text-xs font-extrabold uppercase tracking-widest text-slate-400" style="border-color:var(--border-subtle)">{{ $roles->count() }} Role</header>
@if($roles->isEmpty())
<p class="p-5 text-sm text-slate-500">Belum ada role.</p>
@endif
@foreach($roles as $r)
<a href="/admin/organization/roles?role={{ $r->id }}" class="flex items-center justify-between px-4 py-3 text-sm {{ $role?->id === $r->id ? 'border-l-2 border-[var(--brand-primary)] bg-[color-mix(in_srgb,var(--brand-primary)_8%,transparent)] font-bold' : 'border-b border-[var(--border-subtle)] hover:bg-[var(--surface-muted)]' }} last:border-0">
<span class="min-w-0">{{ $r->name }}@if($r->is_system)<span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-500">sistem</span>@endif</span>
<span class="shrink-0 text-xs text-slate-400">{{ $r->permissions_count }} perm</span>
</a>
@endforeach
</div>
</div>

{{-- ===== RIGHT: SELECTED ROLE WORKSPACE ===== --}}
@if($role)
@php($tab = request('tab', 'overview'))
@php($roleTabs = ['overview' => 'Overview', 'permissions' => 'Permissions', 'members' => 'Members'])
<div class="min-w-0 space-y-5">
<header class="flex flex-wrap items-center justify-between gap-3">
<div>
<h2 class="text-xl font-black tracking-tight">{{ $role->name }}@if($role->is_system)<span class="ml-2 inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-slate-500"><x-ui.icon name="search" class="h-3 w-3" />Terkunci — role sistem</span>@endif</h2>
<p class="mt-0.5 text-sm text-slate-500">Kode: <span class="font-mono">{{ $role->code }}</span> · {{ $role->permissions_count }} permission · {{ $members->count() }} member</p>
</div>
</header>

<x-ui.tabs :tabs="$roleTabs" :active="$tab" />

@if($tab === 'overview')
<article class="rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-6 shadow-[var(--shadow-xs)]">
<dl class="grid gap-x-8 gap-y-4 sm:grid-cols-3">
<div><dt class="text-[11px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Kode Role</dt><dd class="mt-1 font-mono text-sm font-bold">{{ $role->code }}</dd></div>
<div><dt class="text-[11px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Tipe</dt><dd class="mt-1 text-sm font-semibold">{{ $role->is_system ? 'Role Sistem (read-only)' : 'Role Kustom' }}</dd></div>
<div><dt class="text-[11px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Jumlah Permission</dt><dd class="mt-1 text-sm font-bold tabular-nums">{{ $role->permissions_count }}</dd></div>
<div><dt class="text-[11px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Member</dt><dd class="mt-1 text-sm font-bold tabular-nums">{{ $members->count() }}</dd></div>
<div><dt class="text-[11px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Modul Tercakup</dt><dd class="mt-1 text-sm font-bold tabular-nums">{{ $role->is_system ? 'Semua' : collect($permissionsByModule)->filter(fn ($perms) => collect($perms)->contains(fn ($p) => in_array($p->id, $selectedPermissionIds)))->count() }}</dd></div>
<div><dt class="text-[11px] font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Aksi Cepat</dt><dd class="mt-1"><a href="/admin/organization/roles?role={{ $role->id }}&tab=permissions" class="text-sm font-bold text-[var(--brand-primary)] hover:underline">Kelola Permission →</a></dd></div>
</dl>
@if($role->is_system)<p class="mt-5 rounded-xl bg-slate-50 p-3 text-xs text-slate-500 dark:bg-[#16233c]">Role sistem mempunyai seluruh permission secara inheren dan tidak dapat diubah demi keamanan.</p>@endif
@if($members->isNotEmpty())
<div class="mt-5">
<h3 class="text-xs font-extrabold uppercase tracking-widest text-[var(--text-muted)]">Member Role Ini</h3>
<ul class="mt-3 grid gap-2 sm:grid-cols-2">
@foreach($members as $member)
<li class="flex items-center justify-between gap-2 rounded-xl border border-[var(--border-subtle)] px-3.5 py-2.5 text-sm"><span class="min-w-0"><strong class="block truncate">{{ $member->name }}</strong><span class="block truncate text-xs text-slate-400">{{ $member->email }}</span></span></li>
@endforeach
</ul>
</div>
@endif
</article>

@elseif($tab === 'permissions')
<form method="post" action="/admin/organization/roles/{{ $role->id }}/permissions" class="rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] p-5 shadow-[var(--shadow-xs)]">@csrf
<div class="flex flex-wrap items-center justify-between gap-3">
<h2 class="font-black">Permission Matrix</h2>
<div class="flex flex-wrap items-center gap-2 no-print">
<input type="search" placeholder="Cari permission…" data-perm-search class="w-56 rounded-xl border border-[var(--border-default)] bg-[var(--surface-input)] px-3.5 text-sm" aria-label="Cari permission">
<span class="text-xs text-slate-400"><span data-perm-count>{{ $role->permissions_count }}</span> terpilih</span>
@if(! $role->is_system && auth()->user()->hasPermission('organization.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<button class="btn-brand min-h-[40px] rounded-xl px-5 text-sm font-bold">Simpan Permission</button>
@endif
</div>
</div>
<div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3" data-perm-groups>
@foreach($permissionsByModule as $module => $perms)
<fieldset class="rounded-xl border border-[var(--border-subtle)] p-4" data-perm-module="{{ $module }}">
<legend class="flex items-center gap-2 px-1 text-xs font-bold uppercase tracking-wide text-slate-500">{{ str($module)->replace('_',' ') }}
@if(! $role->is_system && auth()->user()->hasPermission('organization.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<button type="button" class="rounded border px-1.5 py-0.5 text-[9px] font-bold normal-case text-[var(--brand-primary)] hover:bg-[var(--surface-muted)]" data-select-module="{{ $module }}">pilih semua</button>
@endif
</legend>
@foreach($perms as $perm)
<label class="mb-0.5 flex items-center gap-2 text-xs" data-perm-label>{{ $role->is_system ? '' : '' }}<input type="checkbox" name="permissions[]" value="{{ $perm->id }}" @checked(in_array($perm->id, $selectedPermissionIds)) @disabled($role->is_system) data-perm-checkbox> <span data-perm-text>{{ $perm->code }}</span></label>
@endforeach
</fieldset>
@endforeach
</div>
@if($role->is_system)<p class="mt-3 text-xs text-slate-400">Role sistem mempunyai seluruh permission secara inheren.</p>@endif
</form>

@elseif($tab === 'members')
<article class="overflow-hidden rounded-[var(--radius-card)] border border-[var(--border-subtle)] bg-[var(--surface-card)] shadow-[var(--shadow-xs)]">
<header class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3.5" style="border-color:var(--border-subtle)">
<h2 class="font-black">Member ({{ $members->count() }})</h2>
@if(auth()->user()->hasPermission('organization.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()) && $candidates->isNotEmpty())
<button type="button" class="inline-flex min-h-[38px] items-center gap-2 rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-open="member-add-drawer"><x-ui.icon name="plus" class="h-4 w-4" />Tambah Member</button>
@endif
</header>
<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr><th>Nama</th><th>Email</th><th class="text-right">Aksi</th></tr></thead><tbody>
@forelse($members as $member)
<tr class="h-[52px]"><td class="font-semibold">{{ $member->name }}</td><td class="text-slate-500">{{ $member->email }}</td>
<td class="text-right">@if(auth()->user()->hasPermission('organization.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()) && ! $role->is_system)<form method="post" action="/admin/organization/roles/{{ $role->id }}/members/{{ $member->id }}/detach" data-confirm="Keluarkan {{ $member->name }} dari role {{ $role->name }}?">@csrf<button class="text-xs font-bold text-red-600 hover:underline">Keluarkan</button></form>@else<span class="text-xs text-slate-400">—</span>@endif</td></tr>
@empty
<tr><td colspan="3" class="p-2"><x-ui.empty icon="user" title="Belum ada member" description="Tambahkan member perusahaan aktif ke role ini." /></td></tr>
@endforelse
</tbody></table></div>
</article>
@endif
</div>
@endif
</div>
</div>

<x-ui.drawer id="role-create-drawer" title="Role Baru" description="Role menentukan akses menu & aksi untuk member perusahaan ini.">
<form method="post" action="/admin/organization/roles" class="grid gap-4">@csrf
<x-ui.field label="Kode role" name="code" required hint="Huruf kecil, tanpa spasi — mis. qc-officer"><input name="code" required maxlength="50" pattern="[a-z0-9\-]+" placeholder="qc-officer" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<x-ui.field label="Nama tampilan" name="name" required><input name="name" required maxlength="100" placeholder="mis. QC Officer" class="w-full rounded-xl border border-[var(--border-default)] px-3.5"></x-ui.field>
<div class="flex justify-end gap-2 pt-2">
<button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button>
<button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Simpan Role</button>
</div>
</form>
</x-ui.drawer>

@if($role && auth()->user()->hasPermission('organization.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<x-ui.drawer id="member-add-drawer" title="Tambah Member" description="Member aktif perusahaan yang akan mendapat role {{ $role->name }}.">
<form method="post" action="/admin/organization/roles/{{ $role->id }}/members" class="grid gap-4">@csrf
<x-ui.field label="Member perusahaan" name="user_id" required>
<select name="user_id" required class="w-full rounded-xl border border-[var(--border-default)] px-3.5"><option value="">Pilih member</option>@foreach($candidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>@endforeach</select>
</x-ui.field>
<div class="flex justify-end gap-2 pt-2">
<button type="button" class="min-h-[42px] rounded-xl border border-[var(--border-default)] px-4 text-sm font-bold hover:bg-[var(--surface-muted)]" data-drawer-close>Batal</button>
<button class="btn-brand min-h-[42px] rounded-xl px-5 text-sm font-bold">Tambahkan ke Role</button>
</div>
</form>
</x-ui.drawer>
@endif
</x-layouts.app>
