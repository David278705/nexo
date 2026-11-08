<?php

use App\Models\Ciudad;
use App\Models\Eps;
use App\Models\Festivo;
use App\Models\Pais;
use App\Models\Plan;
use Illuminate\Support\Carbon;

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

it('carga los tres planes de la propuesta la primera vez, y no los vuelve a cargar (PROD-24)', function () {
    $this->artisan('catalogos:inicializar');

    expect(Plan::query()->orderBy('precio_mensual')->pluck('precio_mensual', 'nombre')->all())->toBe([
        'Micro' => '250000.00',
        'Pequeña' => '450000.00',
        'Mediana' => '750000.00',
    ]);

    Plan::query()->where('nombre', 'Micro')->firstOrFail()->update(['precio_mensual' => '280000.00']);

    $this->artisan('catalogos:inicializar');

    expect(Plan::query()->count())->toBe(3)
        ->and(Plan::query()->where('nombre', 'Micro')->value('precio_mensual'))->toBe('280000.00');
});

it('trae el festivo de la Virgen de Chiquinquirá de la Ley 2578 de 2026, trasladado al lunes (PROD-28)', function () {
    $this->artisan('catalogos:inicializar');

    foreach (['2026-07-13', '2027-07-12', '2028-07-10'] as $fecha) {
        expect(Festivo::esFestivo(Carbon::parse($fecha)))->toBeTrue("falta el festivo del {$fecha}");
    }

    expect(Festivo::esFestivo(Carbon::parse('2026-07-09')))->toBeFalse();
});

it('tiene cargado todo 2028, con el año verificado fecha por fecha', function () {
    $this->artisan('catalogos:inicializar');

    expect(Festivo::fechasDelAnio(2028))->toHaveCount(19);
});
