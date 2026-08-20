<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QmsController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TenderController;
use App\Models\BoredPile;
use App\Models\Project;
use App\Models\StockBalance;
use App\Models\Tender;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('/docs', 'docs')->name('docs');
Route::view('/login', 'auth.login')->middleware('guest')->name('login');
Route::post('/login', function (Request $r) {
    $c = $r->validate(['email' => ['required', 'email'], 'password' => ['required']]);
    if (! Auth::attempt([...$c, 'is_active' => true])) {
        return back()->withErrors(['email' => 'Login tidak valid.']);
    }$r->session()->regenerate();

    return redirect('/dashboard');
})->middleware(['guest', 'throttle:5,1']);
Route::post('/logout', function (Request $r) {
    Auth::logout();
    $r->session()->invalidate();
    $r->session()->regenerateToken();

    return redirect('/');
})->middleware('auth');
Route::get('/dashboard', function (Request $request, CurrentCompany $current) {
    $companyId = $current->id();
    $user = $request->user();
    $stats = [];
    if ($user->hasPermission('tender.view', $companyId)) {
        $stats['Tender Aktif'] = Tender::where('company_id', $companyId)->whereNotIn('status', ['won', 'lost', 'cancelled'])->count();
    }
    if ($user->hasPermission('project.view', $companyId)) {
        $stats['Proyek'] = Project::where('company_id', $companyId)->count();
        $stats['Titik Bored Pile'] = BoredPile::whereHas('project', fn ($query) => $query->where('company_id', $companyId))->count();
    }
    if ($user->hasPermission('inventory.view', $companyId)) {
        $stats['Item Stok'] = StockBalance::where('company_id', $companyId)->where('quantity', '>', 0)->count();
    }

    return view('dashboard', ['company' => $current->get(), 'stats' => $stats]);
})->middleware(['auth', 'company']);
Route::middleware(['auth', 'company', 'permission:organization.view'])->prefix('admin')->group(function () {
    Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
    Route::post('/branches', [OrganizationController::class, 'storeBranch'])->middleware('permission:organization.manage')->name('branches.store');
    Route::post('/departments', [OrganizationController::class, 'storeDepartment'])->middleware('permission:organization.manage')->name('departments.store');
});
Route::middleware(['auth', 'company', 'permission:finance.view'])->prefix('admin')->group(function () {
    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/accounting-mappings', [FinanceController::class, 'mappingIndex'])->name('finance.mappings');
    Route::post('/finance/accounts', [FinanceController::class, 'account'])->middleware('permission:finance.manage');
    Route::post('/finance/periods', [FinanceController::class, 'period'])->middleware('permission:finance.manage');
    Route::post('/finance/mappings', [FinanceController::class, 'mapping'])->middleware('permission:finance.manage');
    Route::post('/finance/journals', [FinanceController::class, 'journal'])->middleware('permission:accounting.post');
});
Route::middleware(['auth', 'company', 'permission:inventory.view'])->prefix('admin')->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/setup', [InventoryController::class, 'setup'])->middleware('permission:inventory.manage');
    Route::post('/inventory/movements', [InventoryController::class, 'movement'])->middleware('permission:inventory.manage');
});
Route::middleware(['auth', 'company', 'permission:project.view'])->prefix('admin')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/project-zones', [ProjectController::class, 'zone'])->middleware('permission:project.manage');
    Route::post('/bored-piles', [ProjectController::class, 'pile'])->middleware('permission:project.manage');
    Route::post('/bored-piles/{pile}/transition', [ProjectController::class, 'transition'])->middleware('permission:project.manage');
    Route::post('/bored-piles/{pile}/concrete', [ProjectController::class, 'concrete'])->middleware('permission:project.manage');
});
Route::middleware(['auth', 'company', 'permission:tender.view'])->prefix('admin')->group(function () {
    Route::get('/tenders', [TenderController::class, 'index'])->name('tenders.index');
    Route::post('/customers', [TenderController::class, 'storeCustomer'])->middleware('permission:tender.manage');
    Route::post('/tenders', [TenderController::class, 'store'])->middleware('permission:tender.manage');
    Route::post('/tenders/{tender}/outcome', [TenderController::class, 'outcome'])->middleware('permission:tender.manage');
    Route::post('/tenders/{tender}/convert', [TenderController::class, 'convert'])->middleware('permission:tender.manage');
});
Route::middleware(['auth', 'company', 'permission:document.view'])->prefix('admin')->group(function () {
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->middleware('permission:document.manage')->name('documents.store');
    Route::get('/document-versions/{version}/download', [DocumentController::class, 'download'])->name('documents.download');
});
Route::middleware(['auth', 'company', 'permission:qms.view'])->prefix('admin')->group(function () {
    Route::get('/qms', [QmsController::class, 'index'])->name('qms.index');
    Route::post('/qms/risks', [QmsController::class, 'risk'])->middleware('permission:qms.manage');
    Route::post('/qms/ncrs', [QmsController::class, 'ncr'])->middleware('permission:qms.manage');
    Route::post('/qms/ncrs/{ncr}/actions', [QmsController::class, 'capa'])->middleware('permission:qms.manage');
    Route::post('/qms/actions/{action}/verify', [QmsController::class, 'verify'])->middleware('permission:qms.verify');
    Route::post('/qms/audits', [QmsController::class, 'audit'])->middleware('permission:qms.audit');
});
Route::middleware(['auth', 'company', 'permission:report.view'])->prefix('admin/reports')->group(function () {
    Route::get('/executive', [ReportController::class, 'executive'])->name('reports.executive');
    Route::get('/finance', [ReportController::class, 'finance'])->name('reports.finance');
    Route::get('/operations', [ReportController::class, 'operations'])->name('reports.operations');
    Route::get('/{type}/export', [ReportController::class, 'export'])->middleware('permission:report.export')->name('reports.export');
});
Route::middleware(['auth', 'company', 'permission:manufacturing.view'])->prefix('admin')->group(function () {
    Route::get('/operations', [OperationsController::class, 'index'])->name('operations.index');
    Route::post('/manufacturing/boms', [OperationsController::class, 'bom'])->middleware('permission:manufacturing.manage');
    Route::post('/manufacturing/orders', [OperationsController::class, 'productionOrder'])->middleware('permission:manufacturing.manage');
    Route::post('/manufacturing/orders/{order}/complete', [OperationsController::class, 'complete'])->middleware('permission:manufacturing.manage');
    Route::post('/equipment', [OperationsController::class, 'equipment'])->middleware('permission:equipment.manage');
    Route::post('/equipment/{equipment}/meter', [OperationsController::class, 'meter'])->middleware('permission:equipment.manage');
    Route::post('/equipment/{equipment}/fuel', [OperationsController::class, 'fuel'])->middleware('permission:equipment.manage');
    Route::post('/equipment/{equipment}/maintenance', [OperationsController::class, 'maintenance'])->middleware('permission:equipment.manage');
});
Route::middleware(['auth', 'company', 'permission:procurement.view'])->prefix('admin/procurement')->group(function () {
    Route::get('/', [ProcurementController::class, 'index'])->name('procurement.index');
    Route::post('/vendors', [ProcurementController::class, 'vendor'])->middleware('permission:procurement.manage');
    Route::post('/orders', [ProcurementController::class, 'order'])->middleware('permission:procurement.manage');
    Route::post('/orders/{order}/submit', [ProcurementController::class, 'submit'])->middleware('permission:procurement.manage');
    Route::post('/orders/{order}/activate', [ProcurementController::class, 'activate'])->middleware('permission:procurement.manage');
    Route::post('/orders/{order}/receive', [ProcurementController::class, 'receive'])->middleware('permission:inventory.manage');
    Route::post('/orders/{order}/invoice', [ProcurementController::class, 'invoice'])->middleware('permission:finance.manage');
});
Route::get('/admin/procurement-accounting', [ProcurementController::class, 'accountingIndex'])->middleware(['auth', 'company', 'permission:accounting.post'])->name('procurement.accounting');
Route::post('/admin/procurement/receipts/{receipt}/post-accounting', [ProcurementController::class, 'postReceipt'])->middleware(['auth', 'company', 'permission:accounting.post']);
Route::post('/admin/procurement/invoices/{invoice}/post-accounting', [ProcurementController::class, 'postInvoice'])->middleware(['auth', 'company', 'permission:accounting.post']);
Route::middleware(['auth', 'company', 'permission:approval.view'])->prefix('admin/approvals')->group(function () {
    Route::get('/', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/workflows', [ApprovalController::class, 'workflow'])->middleware('permission:approval.manage');
    Route::post('/workflows/{workflow}/steps', [ApprovalController::class, 'step'])->middleware('permission:approval.manage');
    Route::post('/{approval}/decide', [ApprovalController::class, 'decide'])->middleware('permission:approval.decide');
    Route::post('/delegations', [ApprovalController::class, 'delegation'])->middleware('permission:approval.manage');
});
