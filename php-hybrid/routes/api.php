<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Api\DocumentController;

Route::get('/health', HealthController::class);
Route::prefix('admin')->group(function () {
    Route::get('/config-status', [AdminAuthController::class, 'configStatus']);
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('legacy.admin')->group(function () {
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::apiResource('/users', UserManagementController::class);
        Route::post('/upload', [DocumentController::class, 'upload'])->middleware('throttle:10,1');
        Route::get('/upload/{id}', [DocumentController::class, 'batch']);
        Route::get('/documents', [DocumentController::class, 'index']);
        Route::get('/documents/{id}', [DocumentController::class, 'show']);
        Route::get('/documents/{id}/pages', [DocumentController::class, 'pages']);
        Route::get('/documents/{id}/download', [DocumentController::class, 'download']);
        Route::post('/documents/{id}/retry', [DocumentController::class, 'retry'])->middleware('throttle:10,1');
    });
});
