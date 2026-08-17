<?php

use Illuminate\Support\Facades\DB;

it('sirve la página de inicio como componente de Inertia', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Inicio')
            ->where('mensaje', 'Sistema de Gestión de Talento Humano')
        );
});

it('corre las pruebas contra PostgreSQL', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

it('responde el chequeo de salud', function () {
    $this->get('/up')->assertOk();
});
