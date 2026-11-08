<?php

use App\Models\Ciudad;
use App\Models\Eps;
use App\Models\Festivo;
use App\Models\Pais;

/**
 * `catalogos:inicializar` (`PROD-20`): lo que corre el servicio web en cada
 * arranque para que una base nueva tenga sus catálogos.
 */
it('carga todos los catálogos en una base vacía', function () {
    expect(Pais::query()->count())->toBe(0);

    $this->artisan('catalogos:inicializar')
        ->expectsOutputToContain('por primera vez')
        ->assertSuccessful();

    expect(Pais::query()->count())->toBeGreaterThan(200)
        ->and(Ciudad::query()->where('nombre', 'Bogotá D.C.')->exists())->toBeTrue()
        ->and(Eps::query()->count())->toBeGreaterThan(0)
        ->and(Festivo::query()->count())->toBeGreaterThan(0);
});

it('no vuelve a cargar los catálogos si ya existen: no pisa lo editado en el panel', function () {
    $this->artisan('catalogos:inicializar');

    $eps = Eps::query()->firstOrFail();
    $nombreOriginal = $eps->nombre;
    $eps->update(['nombre' => 'EPS renombrada desde el panel']);
    $cuantas = Eps::query()->count();

    $this->artisan('catalogos:inicializar')
        ->expectsOutputToContain('solo se actualizaron los festivos')
        ->assertSuccessful();

    expect(Eps::query()->count())->toBe($cuantas)
        ->and(Eps::query()->where('nombre', $nombreOriginal)->exists())->toBeFalse();
});

it('vuelve a cargar los festivos en cada arranque, para que un año nuevo entre solo', function () {
    $this->artisan('catalogos:inicializar');
    $festivo = Festivo::query()->firstOrFail();
    $festivo->forceDelete();

    $this->artisan('catalogos:inicializar');

    expect(Festivo::query()->whereDate('fecha', $festivo->fecha)->exists())->toBeTrue();
});
