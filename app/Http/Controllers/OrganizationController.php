<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditTrail;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(CurrentCompany $current): View
    {
        $roleCount = Role::where('company_id', $current->id())->count();
        $memberCount = (int) DB::table('company_user')->where('company_id', $current->id())->where('is_active', true)->count();

        return view('organization.index', [
            'company' => $current->get(),
            'branches' => Branch::where('company_id', $current->id())->orderBy('code')->paginate(15, ['*'], 'branches'),
            'departments' => Department::where('company_id', $current->id())->with('branch')->orderBy('code')->paginate(15, ['*'], 'departments'),
            'roleCount' => $roleCount,
            'memberCount' => $memberCount,
        ]);
    }

    public function storeBranch(Request $request, CurrentCompany $current, AuditTrail $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30', 'unique:branches,code,NULL,id,company_id,'.$current->id()], 'name' => ['required', 'string', 'max:150']]);
        $branch = Branch::create([...$data, 'company_id' => $current->id(), 'is_active' => true]);
        $audit->record($current->id(), $request->user()->id, 'organization.branch_created', $branch);

        return back()->with('status', 'Cabang berhasil ditambahkan.');
    }

    public function storeDepartment(Request $request, CurrentCompany $current, AuditTrail $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:30', 'unique:departments,code,NULL,id,company_id,'.$current->id()], 'name' => ['required', 'string', 'max:150'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id']]);
        if (! empty($data['branch_id'])) {
            abort_unless(Branch::where('company_id', $current->id())->whereKey($data['branch_id'])->exists(), 422);
        }
        $department = Department::create([...$data, 'company_id' => $current->id(), 'is_active' => true]);
        $audit->record($current->id(), $request->user()->id, 'organization.department_created', $department);

        return back()->with('status', 'Departemen berhasil ditambahkan.');
    }

    public function roles(Request $request, CurrentCompany $current): View
    {
        $companyId = $current->id();
        $roles = Role::where('company_id', $companyId)->withCount('permissions')->orderByDesc('is_system')->orderBy('name')->get();
        $selected = $roles->firstWhere('id', (int) $request->query('role')) ?? $roles->first();
        $members = collect();
        if ($selected) {
            $members = User::select('users.*')->distinct()
                ->join('company_user', 'company_user.user_id', '=', 'users.id')
                ->join('company_user_role', function ($join) use ($selected) {
                    $join->on('company_user_role.company_user_id', '=', 'company_user.id')->where('company_user_role.role_id', $selected->id);
                })
                ->where('company_user.company_id', $companyId)
                ->get(['users.id', 'users.name', 'users.email']);
        }

        return view('organization.roles', [
            'roles' => $roles,
            'role' => $selected,
            'members' => $members,
            'permissionsByModule' => Permission::orderBy('module')->orderBy('code')->get()->groupBy('module'),
            'selectedPermissionIds' => $selected ? $selected->permissions()->pluck('permissions.id')->all() : [],
            'candidates' => User::whereIn('id', DB::table('company_user')->where('company_id', $companyId)->where('is_active', true)->select('user_id'))
                ->whereNotIn('id', $members->pluck('id'))->orderBy('name')->get(),
        ]);
    }

    public function storeRole(Request $request, CurrentCompany $current, AuditTrail $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'max:80'], 'name' => ['required', 'max:120']]);
        abort_unless(Role::where('company_id', $current->id())->where('code', $data['code'])->doesntExist(), 422);
        $role = Role::create([...$data, 'company_id' => $current->id()]);
        $audit->record($current->id(), $request->user()->id, 'organization.role_created', $role);

        return redirect('/admin/organization/roles?role='.$role->id)->with('status', "Role {$role->name} dibuat. Centang permission lalu simpan.");
    }

    public function updatePermissions(Request $request, Role $role, CurrentCompany $current, AuditTrail $audit): RedirectResponse
    {
        abort_unless($role->company_id === $current->id(), 404);
        abort_if($role->is_system, 403, 'Role sistem tidak dapat diubah.');
        $data = $request->validate(['permissions' => ['nullable', 'array'], 'permissions.*' => ['integer']]);
        $validIds = Permission::whereIn('id', $data['permissions'] ?? [])->pluck('id')->all();
        $role->permissions()->sync($validIds);
        $audit->record($current->id(), $request->user()->id, 'organization.role_permissions_updated', $role);

        return back()->with('status', "Permission role {$role->name} diperbarui (".count($validIds).' permission).');
    }

    public function attachMember(Request $request, Role $role, CurrentCompany $current, AuditTrail $audit): RedirectResponse
    {
        abort_unless($role->company_id === $current->id(), 404);
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $pivotId = DB::table('company_user')->where(['company_id' => $role->company_id, 'user_id' => $data['user_id'], 'is_active' => true])->value('id');
        abort_unless($pivotId, 422, 'User bukan member aktif perusahaan ini.');
        DB::table('company_user_role')->insertOrIgnore(['company_user_id' => $pivotId, 'role_id' => $role->id]);
        $audit->record($role->company_id, $request->user()->id, 'organization.role_member_added', $role);

        return back()->with('status', 'Member ditambahkan ke role.');
    }

    public function detachMember(Request $request, Role $role, int $userId, CurrentCompany $current, AuditTrail $audit): RedirectResponse
    {
        abort_unless($role->company_id === $current->id(), 404);
        DB::table('company_user_role')->where('role_id', $role->id)
            ->whereIn('company_user_id', DB::table('company_user')->where('company_id', $role->company_id)->where('user_id', $userId)->select('id'))
            ->delete();
        $audit->record($role->company_id, $request->user()->id, 'organization.role_member_removed', $role);

        return back()->with('status', 'Member dikeluarkan dari role.');
    }
}
