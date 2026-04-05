<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

// Mobile auth endpoints (token flow).
Route::prefix('mobile')->group(function () {
    Route::post('/login', [MobileAuthController::class, 'login'])->name('mobile.login');
    Route::post('/register', [MobileAuthController::class, 'register'])->name('mobile.register');
    Route::post('/logout', [MobileAuthController::class, 'logout'])
        ->middleware('auth:sanctum')
        ->name('mobile.logout');
});

// SPA API endpoints (cookie/session via Sanctum or token if mobile uses these routes).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', MeController::class)->name('api.me');
    Route::get('/dashboard', DashboardController::class)->name('api.dashboard');
    Route::get('/wallets', [WalletController::class, 'index'])->name('api.wallets.index');
    Route::get('/wallets/{wallet}', [WalletController::class, 'show'])->name('api.wallets.show');
});
