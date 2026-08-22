<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CageController;
use App\Http\Controllers\CashBankController;
use App\Http\Controllers\CasingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FieldOpsController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\FuelTankController;
use App\Http\Controllers\HseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ManufacturingController;
use App\Http\Controllers\MaterialRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectCostingController;
use App\Http\Controllers\QmsController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RfqController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TenderController;
use App\Http\Controllers\ToolController;
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
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'company'])->name('dashboard');
Route::middleware(['auth', 'company', 'permission:organization.view'])->prefix('admin')->group(function () {
    Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
    Route::post('/branches', [OrganizationController::class, 'storeBranch'])->middleware('permission:organization.manage')->name('branches.store');
    Route::post('/departments', [OrganizationController::class, 'storeDepartment'])->middleware('permission:organization.manage')->name('departments.store');
});
Route::middleware(['auth', 'company', 'permission:finance.view'])->prefix('admin')->group(function () {
    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/accounts', [FinanceController::class, 'accounts'])->name('finance.accounts');
    Route::get('/finance/periods', [FinanceController::class, 'periods'])->name('finance.periods');
    Route::get('/finance/journals', [FinanceController::class, 'journals'])->name('finance.journals');
    Route::get('/finance/accounting-mappings', [FinanceController::class, 'mappingIndex'])->name('finance.mappings');
    Route::post('/finance/accounts', [FinanceController::class, 'account'])->middleware('permission:finance.manage');
    Route::post('/finance/periods', [FinanceController::class, 'period'])->middleware('permission:finance.manage');
    Route::post('/finance/mappings', [FinanceController::class, 'mapping'])->middleware('permission:finance.manage');
    Route::post('/finance/journals', [FinanceController::class, 'journal'])->middleware('permission:accounting.post');
});
Route::middleware(['auth', 'company', 'permission:inventory.view'])->prefix('admin')->group(function () {
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/opname', [StockOpnameController::class, 'index'])->name('opname.index');
    Route::post('/inventory/opname', [StockOpnameController::class, 'store'])->middleware('permission:inventory.manage');
    Route::post('/inventory/opname/{count}/approve', [StockOpnameController::class, 'approve'])->middleware('permission:inventory.manage');
    Route::get('/inventory/material-requests', [MaterialRequestController::class, 'index'])->name('material-requests.index');
    Route::post('/inventory/material-requests', [MaterialRequestController::class, 'store'])->middleware('permission:inventory.manage');
    Route::post('/inventory/material-requests/{material_request}/approve', [MaterialRequestController::class, 'approve'])->middleware('permission:inventory.manage');
    Route::post('/inventory/material-requests/{material_request}/issue', [MaterialRequestController::class, 'issue'])->middleware('permission:inventory.manage');
    Route::post('/inventory/material-requests/{material_request}/lines/{line}/return', [MaterialRequestController::class, 'returnLine'])->middleware('permission:inventory.manage');
    Route::post('/inventory/setup', [InventoryController::class, 'setup'])->middleware('permission:inventory.manage');
    Route::post('/inventory/movements', [InventoryController::class, 'movement'])->middleware('permission:inventory.manage');
});
Route::middleware(['auth', 'company', 'permission:project.view'])->prefix('admin')->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/field-ops', [FieldOpsController::class, 'index'])->name('field-ops.index');
    Route::post('/projects/field-ops/drillings', [FieldOpsController::class, 'storeDrilling'])->middleware('permission:project.manage');
    Route::post('/projects/field-ops/drillings/{drilling}/verify', [FieldOpsController::class, 'verifyDrilling'])->middleware('permission:project.manage');
    Route::post('/projects/field-ops/deliveries', [FieldOpsController::class, 'storeDelivery'])->middleware('permission:project.manage');
    Route::post('/projects/field-ops/deliveries/{delivery}/approve', [FieldOpsController::class, 'approveDelivery'])->middleware('permission:project.manage');
    Route::post('/projects/field-ops/deliveries/{delivery}/reject', [FieldOpsController::class, 'rejectDelivery'])->middleware('permission:project.manage');
    Route::post('/projects/field-ops/tests', [FieldOpsController::class, 'storeTest'])->middleware('permission:project.manage');
    Route::post('/projects/field-ops/tests/{test}/result', [FieldOpsController::class, 'recordTestResult'])->middleware('permission:project.manage');
    Route::post('/projects/field-ops/tests/{test}/approve', [FieldOpsController::class, 'approveTest'])->middleware('permission:project.manage');
    Route::post('/project-zones', [ProjectController::class, 'zone'])->middleware('permission:project.manage');
    Route::post('/bored-piles', [ProjectController::class, 'pile'])->middleware('permission:project.manage');
    Route::post('/bored-piles/{pile}/transition', [ProjectController::class, 'transition'])->middleware('permission:project.manage');
    Route::post('/bored-piles/{pile}/concrete', [ProjectController::class, 'concrete'])->middleware('permission:project.manage');
});
Route::middleware(['auth', 'company', 'permission:tender.view'])->prefix('admin')->group(function () {
    Route::get('/tenders', [TenderController::class, 'index'])->name('tenders.index');
    Route::post('/customers', [TenderController::class, 'storeCustomer'])->middleware('permission:tender.manage');
    Route::post('/competitors', [TenderController::class, 'storeCompetitor'])->middleware('permission:tender.manage');
    Route::post('/participants', [TenderController::class, 'storeParticipant'])->middleware('permission:tender.manage');
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
    Route::post('/qms/objectives', [QmsController::class, 'storeObjective'])->middleware('permission:qms.manage');
    Route::post('/qms/objectives/{objective}/actual', [QmsController::class, 'updateObjectiveActual'])->middleware('permission:qms.manage');
    Route::post('/qms/surveys', [QmsController::class, 'storeSurvey'])->middleware('permission:qms.manage');
});
Route::middleware(['auth', 'company', 'permission:report.view'])->prefix('admin/reports')->group(function () {
    Route::get('/executive', [ReportController::class, 'executive'])->name('reports.executive');
    Route::get('/finance', [ReportController::class, 'finance'])->name('reports.finance');
    Route::get('/operations', [ReportController::class, 'operations'])->name('reports.operations');
    Route::get('/manufacturing', [ReportController::class, 'manufacturing'])->name('reports.manufacturing');
    Route::get('/financial-statements', [ReportController::class, 'financialStatements'])->name('reports.financial-statements');
    Route::get('/aging', [ReportController::class, 'aging'])->name('reports.aging');
    Route::get('/{type}/export', [ReportController::class, 'export'])->middleware('permission:report.export')->name('reports.export');
});
Route::middleware(['auth', 'company', 'permission:manufacturing.view'])->prefix('admin')->group(function () {
    Route::get('/manufacturing', [ManufacturingController::class, 'index'])->name('manufacturing.index');
    Route::get('/manufacturing/quality', [ManufacturingController::class, 'quality'])->name('manufacturing.quality');
    Route::get('/manufacturing/nonconforming', [ManufacturingController::class, 'nonconforming'])->name('manufacturing.nonconforming');
    Route::get('/manufacturing/costing', [ManufacturingController::class, 'costing'])->name('manufacturing.costing');
    Route::get('/manufacturing/cages', [CageController::class, 'index'])->name('cages.index');
    Route::post('/manufacturing/cages', [CageController::class, 'store'])->middleware('permission:manufacturing.manage');
    Route::post('/manufacturing/cages/{cage}/qc', [CageController::class, 'qc'])->middleware('permission:manufacturing.manage');
    Route::post('/manufacturing/cages/{cage}/deliver', [CageController::class, 'deliver'])->middleware('permission:manufacturing.manage');
    Route::post('/manufacturing/work-centers', [ManufacturingController::class, 'workCenter'])->middleware('permission:manufacturing.manage');
    Route::post('/manufacturing/boms/{bom}/routing-operations', [ManufacturingController::class, 'routingOperation'])->middleware('permission:manufacturing.manage');
    Route::post('/manufacturing/orders/{order}/operations/{operation}', [ManufacturingController::class, 'recordOperation'])->middleware('permission:manufacturing.manage');
    Route::post('/manufacturing/boms/{bom}/items', [ManufacturingController::class, 'addBomItem'])->middleware('permission:manufacturing.manage');
    Route::post('/manufacturing/orders/{order}/issue', [ManufacturingController::class, 'issue'])->middleware('permission:manufacturing.manage');
    Route::post('/manufacturing/orders/{order}/inspect', [ManufacturingController::class, 'inspect'])->middleware('permission:manufacturing.manage');
    Route::post('/manufacturing/inspections/{inspection}/dispose', [ManufacturingController::class, 'dispose'])->middleware('permission:manufacturing.manage');
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
    Route::get('/rfq', [RfqController::class, 'index'])->name('rfq.index');
    Route::post('/rfq', [RfqController::class, 'store'])->middleware('permission:procurement.manage');
    Route::post('/rfq/{rfq}/invite', [RfqController::class, 'invite'])->middleware('permission:procurement.manage');
    Route::post('/rfq/{rfq}/quotations', [RfqController::class, 'submitQuotation'])->middleware('permission:procurement.manage');
    Route::post('/rfq/quotations/{quotation}/select', [RfqController::class, 'select'])->middleware('permission:procurement.manage');
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
Route::middleware(['auth', 'company', 'permission:signature.view'])->prefix('admin/signatures')->group(function () {
    Route::get('/', [SignatureController::class, 'index'])->name('signatures.index');
    Route::post('/providers', [SignatureController::class, 'provider'])->middleware('permission:signature.manage');
    Route::post('/versions/{version}/internal', [SignatureController::class, 'internal'])->middleware('permission:signature.sign');
    Route::post('/versions/{version}/external', [SignatureController::class, 'external'])->middleware('permission:signature.manage');
});
Route::post('/webhooks/signatures/{provider}', [SignatureController::class, 'webhook'])->middleware('throttle:60,1')->name('signatures.webhook');
Route::middleware(['auth', 'company', 'permission:finance.view'])->prefix('admin/billing')->group(function () {
    Route::get('/', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/', [BillingController::class, 'store'])->middleware('permission:finance.manage');
    Route::post('/{billing}/submit', [BillingController::class, 'submit'])->middleware('permission:finance.manage');
    Route::post('/{billing}/activate', [BillingController::class, 'activate'])->middleware('permission:finance.manage');
    Route::post('/{billing}/post', [BillingController::class, 'post'])->middleware('permission:accounting.post');
    Route::get('/{billing}/pdf', [BillingController::class, 'pdf'])->name('billing.pdf');
    Route::post('/retention-releases', [BillingController::class, 'storeRelease'])->middleware('permission:finance.manage');
    Route::post('/retention-releases/{release}/submit', [BillingController::class, 'submitRelease'])->middleware('permission:finance.manage');
    Route::post('/retention-releases/{release}/activate', [BillingController::class, 'activateRelease'])->middleware('permission:finance.manage');
    Route::post('/retention-releases/{release}/post', [BillingController::class, 'postRelease'])->middleware('permission:accounting.post');
});
Route::middleware(['auth', 'company', 'permission:finance.view'])->prefix('admin/cash-bank')->group(function () {
    Route::get('/', [CashBankController::class, 'index'])->name('cash-bank.index');
    Route::post('/accounts', [CashBankController::class, 'bank'])->middleware('permission:finance.manage');
    Route::post('/receipts', [CashBankController::class, 'receipt'])->middleware('permission:accounting.post');
    Route::post('/payments', [CashBankController::class, 'payment'])->middleware('permission:accounting.post');
    Route::post('/statements', [CashBankController::class, 'statement'])->middleware('permission:finance.manage');
    Route::post('/statements/{line}/reconcile', [CashBankController::class, 'reconcile'])->middleware('permission:finance.manage');
    Route::post('/periods/{period}/close', [CashBankController::class, 'close'])->middleware('permission:accounting.post');
});
Route::middleware(['auth', 'company', 'permission:finance.view'])->prefix('admin/taxes')->group(function () {
    Route::get('/', [TaxController::class, 'index'])->name('taxes.index');
    Route::post('/rates', [TaxController::class, 'storeRate'])->middleware('permission:finance.manage')->name('taxes.rates.store');
    Route::post('/rates/{rate}/toggle', [TaxController::class, 'toggleRate'])->middleware('permission:finance.manage')->name('taxes.rates.toggle');
});
Route::middleware(['auth', 'company', 'permission:finance.view'])->prefix('admin/project-costing')->group(function () {
    Route::get('/', [ProjectCostingController::class, 'index'])->name('project-costing.index');
    Route::post('/forecasts', [ProjectCostingController::class, 'forecast'])->middleware('permission:finance.manage');
});
Route::middleware(['auth', 'company', 'permission:finance.view'])->prefix('admin/fixed-assets')->group(function () {
    Route::get('/', [FixedAssetController::class, 'index'])->name('fixed-assets.index');
    Route::post('/categories', [FixedAssetController::class, 'category'])->middleware('permission:finance.manage');
    Route::post('/', [FixedAssetController::class, 'asset'])->middleware('permission:finance.manage');
    Route::post('/{asset}/depreciate', [FixedAssetController::class, 'depreciate'])->middleware('permission:accounting.post');
});
Route::middleware(['auth', 'company', 'permission:hse.view'])->prefix('admin/hse')->group(function () {
    Route::get('/', [HseController::class, 'index'])->name('hse.index');
    Route::post('/jsa', [HseController::class, 'jsa'])->middleware('permission:hse.manage');
    Route::post('/jsa/{jsa}/submit', [HseController::class, 'submitJsa'])->middleware('permission:hse.manage');
    Route::post('/jsa/{jsa}/activate', [HseController::class, 'activateJsa'])->middleware('permission:hse.manage');
    Route::post('/jsa/{jsa}/permits', [HseController::class, 'permit'])->middleware('permission:hse.manage');
    Route::post('/incidents', [HseController::class, 'incident'])->middleware('permission:hse.manage');
    Route::post('/incidents/{incident}/actions', [HseController::class, 'action'])->middleware('permission:hse.manage');
    Route::post('/actions/{action}/verify', [HseController::class, 'verify'])->middleware('permission:hse.verify');
    Route::post('/incidents/{incident}/close', [HseController::class, 'close'])->middleware('permission:hse.verify');
    Route::post('/management-reviews', [HseController::class, 'review'])->middleware('permission:hse.manage');
});
Route::middleware(['auth', 'company'])->prefix('admin/notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
});
Route::get('/admin/audit', [AuditController::class, 'index'])->middleware(['auth', 'company', 'permission:audit.view'])->name('audit.index');
Route::get('/admin/fuel-tanks', [FuelTankController::class, 'index'])->middleware(['auth', 'company', 'permission:equipment.view'])->name('fuel-tanks.index');
Route::post('/admin/fuel-tanks', [FuelTankController::class, 'store'])->middleware(['auth', 'company', 'permission:equipment.manage']);
Route::post('/admin/fuel-tanks/{tank}/record', [FuelTankController::class, 'record'])->middleware(['auth', 'company', 'permission:equipment.manage']);
Route::post('/admin/fuel-tanks/{tank}/reconcile', [FuelTankController::class, 'reconcile'])->middleware(['auth', 'company', 'permission:equipment.manage']);
Route::get('/admin/casings', [CasingController::class, 'index'])->middleware(['auth', 'company', 'permission:equipment.view'])->name('casings.index');
Route::post('/admin/casings', [CasingController::class, 'store'])->middleware(['auth', 'company', 'permission:equipment.manage']);
Route::post('/admin/casings/{casing}/move', [CasingController::class, 'move'])->middleware(['auth', 'company', 'permission:equipment.manage']);
Route::get('/admin/tools', [ToolController::class, 'index'])->middleware(['auth', 'company', 'permission:inventory.view'])->name('tools.index');
Route::post('/admin/tools', [ToolController::class, 'store'])->middleware(['auth', 'company', 'permission:inventory.manage']);
Route::post('/admin/tools/{tool}/checkout', [ToolController::class, 'checkOut'])->middleware(['auth', 'company', 'permission:inventory.manage']);
Route::post('/admin/tools/{tool}/checkin', [ToolController::class, 'checkIn'])->middleware(['auth', 'company', 'permission:inventory.manage']);
Route::post('/admin/tools/{tool}/lost', [ToolController::class, 'markLost'])->middleware(['auth', 'company', 'permission:inventory.manage']);
Route::get('/admin/settings', [SettingsController::class, 'index'])->middleware(['auth', 'company'])->name('settings.index');
Route::post('/admin/settings', [SettingsController::class, 'save'])->middleware(['auth', 'company', 'permission:finance.manage'])->name('settings.save');
