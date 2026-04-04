<?php

use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Route;

// SPA shell: все не-API маршруты отдаём в Vue приложение.
// Это позволяет использовать /login, /dashboard, /wallets/1 как нормальные URL.
Route::view('/{any}', 'app')
    ->where('any', '^(?!api|sanctum).*$');

// Web auth (cookie/session flow для Vue SPA).

Route::post('/login', [AuthController::class, 'login'])->name('web.login');
Route::post('/register', [AuthController::class, 'register'])->name('web.register');
Route::post('/logout', [AuthController::class, 'logout'])->name('web.logout');
