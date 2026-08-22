<x-layouts.app title="Role & Permission"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-3xl font-black">Role & Permission</h1>
<p class="mt-2 text-slate-500">Akses menu & aksi ditentukan role per perusahaan. Role sistem tidak dapat diubah. Setiap perubahan tercatat di audit trail.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid gap-5 lg:grid-cols-[320px_1fr]">

<div class="space-y-4 no-print">
<form method="post" action="/admin/organization/roles" class="grid gap-2 rounded-2xl border bg-white p-5">@csrf
<h2 class="font-bold">Buat Role Baru</h2>
<input name="code" required placeholder="Kode (mis. qc-officer)" class="rounded-xl border p-3"><input name="name" required placeholder="Nama tampilan" class="rounded-xl border p-3">
<button class="rounded-xl bg-slate-900 p-3 font-bold text-white">Simpan role</button>
</form>
<div class="overflow-hidden rounded-2xl border bg-white">
@if($roles->isEmpty())
<p class="p-5 text-sm text-slate-500">Belum ada role.</p>
@endif
@foreach($roles as $r)
<a href="/admin/organization/roles?role={{ $r->id }}" class="flex items-center justify-between px-4 py-3 text-sm {{ $role?->id === $r->id ? 'bg-sky-50 font-bold' : 'hover:bg-slate-50' }} border-b last:border-0">
<span>{{ $r->name }}@if($r->is_system)<span class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] uppercase">sistem</span>@endif</span>
<span class="text-xs text-slate-400">{{ $r->permissions_count }} perm</span>
</a>
@endforeach
</div>
</div>

@if($role)
<div class="space-y-5">
<form method="post" action="/admin/organization/roles/{{ $role->id }}/permissions" class="rounded-2xl border bg-white p-5">@csrf
<h2 class="font-black">Permission — {{ $role->name }}</h2>
@if(! $role->is_system && auth()->user()->hasPermission('organization.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))<button class="mt-2 rounded-xl bg-sky-700 px-5 py-2.5 font-bold text-white">Simpan permission</button>@endif
<div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
@foreach($permissionsByModule as $module => $perms)
<fieldset class="rounded-xl border p-4"><legend class="px-1 text-xs font-bold uppercase tracking-wide text-slate-500">{{ str($module)->replace('_',' ') }}</legend>
@foreach($perms as $perm)
<label class="mb-0.5 flex items-center gap-2 text-xs"><input type="checkbox" name="permissions[]" value="{{ $perm->id }}" @checked(in_array($perm->id, $selectedPermissionIds)) @disabled($role->is_system)> {{ $perm->code }}</label>
@endforeach
</fieldset>
@endforeach
</div>
@if($role->is_system)<p class="mt-3 text-xs text-slate-400">Role sistem mempunyai seluruh permission secara inheren.</p>@endif
</form>

<div class="grid gap-5 lg:grid-cols-2">
<div class="rounded-2xl border bg-white p-5">
<h2 class="font-bold mb-2">Member ({{ $members->count() }})</h2>
<table class="w-full text-sm"><tbody>
@if($members->isEmpty())
<tr><td class="p-4 text-center text-slate-500">Belum ada member.</td></tr>
@endif
@foreach($members as $member)
<tr class="border-b last:border-0"><td class="py-2">{{ $member->name }}<span class="block text-xs text-slate-400">{{ $member->email }}</span></td>
<td class="py-2 text-right">@if(auth()->user()->hasPermission('organization.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()) && ! $role->is_system)<form method="post" action="/admin/organization/roles/{{ $role->id }}/members/{{ $member->id }}/detach">@csrf<button class="text-xs font-bold text-red-600">Keluarkan</button></form>@else—@endif</td></tr>
@endforeach
</tbody></table>
</div>
@if(auth()->user()->hasPermission('organization.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<form method="post" action="/admin/organization/roles/{{ $role->id }}/members" class="self-start rounded-2xl border bg-white p-5 no-print">@csrf
<h2 class="font-bold">Tambah Member</h2>
<select name="user_id" required class="mt-2 w-full rounded-xl border p-3"><option value="">Pilih member perusahaan</option>@foreach($candidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>@endforeach</select>
<button class="mt-2 w-full rounded-xl bg-emerald-700 p-3 font-bold text-white">Tambahkan ke role</button>
</form>
@endif
</div>
</div>
@endif
</section></x-layouts.app>
