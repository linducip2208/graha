<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\TenderController;
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
Route::get('/dashboard', fn (CurrentCompany $current) => view('dashboard', ['company' => $current->get()]))->middleware(['auth', 'company']);
Route::middleware(['auth', 'company', 'permission:organization.view'])->prefix('admin')->group(function () {
    Route::get('/organization', [OrganizationController::class, 'index'])->name('organization.index');
    Route::post('/branches', [OrganizationController::class, 'storeBranch'])->middleware('permission:organization.manage')->name('branches.store');
    Route::post('/departments', [OrganizationController::class, 'storeDepartment'])->middleware('permission:organization.manage')->name('departments.store');
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
