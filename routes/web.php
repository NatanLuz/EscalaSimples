<?php

use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeBulkController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\SchedulingController;
use App\Http\Controllers\WorkPatternController;
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
    Route::resource('setores', DepartmentController::class)->parameters(['setores' => 'department'])->except(['show', 'destroy'])->names('departments');
    Route::get('/colaboradores/importar', [EmployeeImportController::class, 'create'])->name('employees.import');
    Route::get('/colaboradores/modelo-csv', [EmployeeImportController::class, 'template'])->name('employees.template');
    Route::post('/colaboradores/importar/previa', [EmployeeImportController::class, 'preview'])->name('employees.import.preview');
    Route::post('/colaboradores/importar/confirmar', [EmployeeImportController::class, 'confirm'])->name('employees.import.confirm');
    Route::post('/colaboradores/lote', EmployeeBulkController::class)->name('employees.bulk');
    Route::resource('colaboradores', EmployeeController::class)->parameters(['colaboradores' => 'employee'])->except(['show', 'destroy'])->names('employees');
    Route::resource('jornadas', WorkPatternController::class)->parameters(['jornadas' => 'pattern'])->except(['show', 'destroy'])->names('patterns');
    Route::get('/atribuicoes', [SchedulingController::class, 'index'])->name('assignments.index');
    Route::post('/atribuicoes', [SchedulingController::class, 'store'])->name('assignments.store');
    Route::post('/atribuicoes/{assignment}/encerrar', [SchedulingController::class, 'end'])->name('assignments.end');
    Route::post('/excecoes', [SchedulingController::class, 'override'])->name('overrides.store');
});
