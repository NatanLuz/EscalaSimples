<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home', ['mensagem' => 'Laravel e React estão conectados pelo Inertia.']);
});

Route::get('/sobre', fn () => Inertia::render('Sobre'));
