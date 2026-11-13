<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\UserManagementController;

Route::get('/health', HealthController::class);
Route::prefix('admin')->group(function () {
    Route::get('/config-status', [AdminAuthController::class, 'configStatus']);
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('legacy.admin')->group(function () {
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::apiResource('/users', UserManagementController::class);
    });
});
