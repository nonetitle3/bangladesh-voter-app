<?php
use Illuminate\\Support\\Facades\\Route;
use App\\Http\\Controllers\\Api\\HealthController;
use App\\Http\\Controllers\\Api\\AdminAuthController;

Route::get('/health', HealthController::class);
Route::prefix('admin')->group(function () {
    Route::get('/config-status', [AdminAuthController::class, 'configStatus']);
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('legacy.admin')->get('/me', [AdminAuthController::class, 'me']);
});
