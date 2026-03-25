<?php

//use App\Http\Controllers\Auth\LoginController;
//use App\Http\Controllers\Auth\LogoutController;
//use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\UserRegisterController;
use Illuminate\Support\Facades\Route;

// Гостевые маршруты
Route::middleware('guest')->group(function () {
    Route::inertia('/login', 'Auth/Login')->name('login');
    Route::post('/login', [LoginController::class]);

    Route::inertia('/register', 'Auth/Register')->name('register');
    Route::post('/register', [UserRegisterController::class]);
});

// Защищенные маршруты
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
    Route::post('/logout', [LogoutController::class])
        ->name('logout');
});
