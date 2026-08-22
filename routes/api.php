<?php

use App\Http\Controllers\Api\V1\FieldApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::post('/auth/token', [FieldApiController::class, 'token'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/projects', [FieldApiController::class, 'projects']);
        Route::get('/bored-piles', [FieldApiController::class, 'boredPiles']);
        Route::post('/daily-reports', [FieldApiController::class, 'storeDailyReport']);
        Route::get('/material-requests', [FieldApiController::class, 'materialRequests']);
        Route::post('/material-requests', [FieldApiController::class, 'storeMaterialRequest']);
    });
});
