<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Inicio', [
    'mensaje' => 'Sistema de Gestión de Talento Humano',
]))->name('inicio');
