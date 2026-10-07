<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyBulkController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyImportController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\PipelineController;
use App\Http\Controllers\Api\StageController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Autenticazione (sessione con cookie httpOnly via Sanctum SPA + CSRF).
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('password', [AuthController::class, 'updatePassword'])->middleware('throttle:sensitive');
        Route::post('two-factor', [AuthController::class, 'enableTwoFactor'])->middleware('throttle:sensitive');
        Route::post('two-factor/confirm', [AuthController::class, 'confirmTwoFactor'])->middleware('throttle:sensitive');
        Route::delete('two-factor', [AuthController::class, 'disableTwoFactor'])->middleware('throttle:sensitive');
    });
});

// Dati del CRM: richiedono utente attivo e, per i ruoli previsti, 2FA attiva.
Route::middleware(['auth:sanctum', 'active', 'two-factor', 'throttle:api'])->group(function () {
    Route::get('dashboard', DashboardController::class);
    Route::get('pipeline', PipelineController::class);

    Route::get('stages', [StageController::class, 'index']);
    Route::put('stages', [StageController::class, 'sync']);

    Route::post('companies/import', CompanyImportController::class)->middleware('throttle:import');
    Route::post('companies/bulk', CompanyBulkController::class)->middleware('throttle:import');
    Route::apiResource('companies', CompanyController::class);
    Route::apiResource('contacts', ContactController::class);
    Route::apiResource('deals', DealController::class);
    Route::patch('deals/{deal}/move', [DealController::class, 'move']);

    Route::apiResource('activities', ActivityController::class)->except('show');
    Route::post('activities/{activity}/toggle-complete', [ActivityController::class, 'toggleComplete']);

    Route::get('users/options', [UserController::class, 'options']);
    Route::apiResource('users', UserController::class)->only(['index', 'store', 'update']);

    Route::get('audit-logs', AuditLogController::class);
});
