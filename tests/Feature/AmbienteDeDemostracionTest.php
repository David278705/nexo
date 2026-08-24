<?php

use App\Models\Arl;
use App\Models\Banco;
use App\Models\CajaCompensacion;
use App\Models\Ciudad;
use App\Models\Eps;
use App\Models\FondoPension;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\Tenant;
use App\Models\Vinculacion;
use App\Multiempresa\ContextoTenant;
use App\Proveedor\AmbienteDeDemostracion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * El ambiente de demostración (F5-03, documento 4, capítulo 10).
 *
 * El «hecho cuando» de la tarea es literal: «el ambiente se restaura solo y
 * siempre luce igual de poblado». Las pruebas de abajo comprueban justo eso
 * —restaurar dos veces deja la misma planta, no una planta creciendo ni una
 * distinta cada vez— y que la única empresa marcada como ambiente de
 * demostración no se pueda duplicar.
 */
beforeEach(function () {
    Pais::query()->firstOrCreate(['codigo' => Pais::COLOMBIA], ['nombre' => 'Colombia', 'activo' => true]);
    Ciudad::factory()->create(['nombre' => 'Bogotá D.C.']);

    Eps::query()->create(['nombre' => 'Sura EPS', 'activo' => true]);
    FondoPension::query()->create(['nombre' => 'Protección', 'activo' => true]);
    Arl::query()->create(['nombre' => 'ARL Sura', 'activo' => true]);
    CajaCompensacion::query()->create(['nombre' => 'Compensar', 'activo' => true]);
    Banco::query()->create(['nombre' => 'Bancolombia', 'activo' => true]);
});

it('crea una sola empresa de demostración y la reutiliza si ya existe', function () {
    $ambiente = app(AmbienteDeDemostracion::class);

    $primera = $ambiente->tenant();
    $segunda = $ambiente->tenant();

    expect($segunda->getKey())->toBe($primera->getKey())
        ->and(Tenant::query()->where('es_ambiente_demo', true)->count())->toBe(1);
});

it('el índice único no permite una segunda empresa marcada como ambiente de demostración', function () {
    Tenant::factory()->ambienteDeDemo()->create();

    expect(fn () => Tenant::factory()->ambienteDeDemo()->create())
        ->toThrow(QueryException::class);
});

it('restaura el ambiente con la misma planta, sin importar cuántas veces se restaure', function () {
    $ambiente = app(AmbienteDeDemostracion::class);

    $ambiente->restaurar();
    $primeraVez = documentosDeLaPlanta($ambiente);

    $ambiente->restaurar();
    $segundaVez = documentosDeLaPlanta($ambiente);

    expect($primeraVez)->toHaveCount(8)
        ->and($segundaVez)->toEqual($primeraVez);
});

it('deja constancia de cuándo quedó restaurado por última vez', function () {
    $ambiente = app(AmbienteDeDemostracion::class);

    $ambiente->restaurar();

    expect($ambiente->estado()['restauradoEn'])->not->toBeNull()
        ->and($ambiente->estado()['personas'])->toBe(8);
});

it('vacía con borrado lógico: la restauración anterior queda en el histórico, no desaparece', function () {
    $ambiente = app(AmbienteDeDemostracion::class);

    $ambiente->restaurar();
    $ambiente->restaurar();

    $tenant = $ambiente->tenant();

    $vivas = DB::table('personas')->where('tenant_id', $tenant->getKey())->whereNull('deleted_at')->count();
    $conLasBorradas = DB::table('personas')->where('tenant_id', $tenant->getKey())->count();

    expect($vivas)->toBe(8)
        ->and($conLasBorradas)->toBe(16);
});

it('deja un contrato a término fijo por vencer, para que la demostración siempre tenga algo próximo a mostrar', function () {
    $ambiente = app(AmbienteDeDemostracion::class);
    $tenant = $ambiente->restaurar();

    $vinculacion = app(ContextoTenant::class)->paraTenant(
        $tenant,
        fn () => Vinculacion::query()->where('cargo', 'Desarrollador Backend')->firstOrFail(),
    );

    expect($vinculacion->fecha_fin->isFuture())->toBeTrue();
});

it('el comando de consola corre la misma restauración que el botón del panel', function () {
    $this->artisan('demostracion:restaurar')->assertSuccessful();

    $ambiente = app(AmbienteDeDemostracion::class);

    expect($ambiente->estado()['personas'])->toBe(8);
});

/**
 * @return list<string>
 */
function documentosDeLaPlanta(AmbienteDeDemostracion $ambiente): array
{
    $tenant = $ambiente->tenant();

    return app(ContextoTenant::class)->paraTenant(
        $tenant,
        fn () => Persona::query()->orderBy('numero_documento')->pluck('numero_documento')->all(),
    );
}
