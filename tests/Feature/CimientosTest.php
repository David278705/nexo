<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('sirve la página de inicio como componente de Inertia', function () {
    // El inicio pide sesión desde F0-07; sin usuario redirige al acceso.
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($pagina) => $pagina
            ->component('Inicio')
            ->has('fecha')
            ->etc()
        );
});

it('corre las pruebas contra PostgreSQL', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

it('responde el chequeo de salud', function () {
    $this->get('/up')->assertOk();
});
