<?php

use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', ['mensagem' => 'Laravel e React estão conectados pelo Inertia.']);
});

Route::get('/sobre', fn () => Inertia::render('Sobre'));

Route::middleware('guest')->group(function () {
    Route::get('/cadastro', [RegistrationController::class, 'create'])->name('register');
    Route::post('/cadastro', [RegistrationController::class, 'store'])->name('register.store');
    Route::get('/login', [AuthenticationController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticationController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthenticationController::class, 'destroy'])->name('logout');
});
