<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Services\AuditTrail;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(CurrentCompany $current): View
    {
        return view('organization.index', ['company' => $current->get(), 'branches' => Branch::where('company_id', $current->id())->orderBy('code')->paginate(15, ['*'], 'branches'), 'departments' => Department::where('company_id', $current->id())->with('branch')->orderBy('code')->paginate(15, ['*'], 'departments')]);
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
}
