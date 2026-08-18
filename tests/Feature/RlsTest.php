<?php

use App\Models\Tenant;
use App\Models\User;
use App\Multiempresa\ContextoTenant;
use App\Multiempresa\PoliticaRls;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Capa 2: lo que hace PostgreSQL cuando el código de la aplicación no ayuda.
 *
 * Todas las consultas de aquí van por el query builder, nunca por un modelo:
 * el objetivo es exactamente saltarse el global scope y comprobar que aun así
 * no sale ni una fila ajena.
 */
const ROL_DE_APLICACION = 'nexo_rol_de_prueba';

/**
 * Ejecuta algo con un rol de base de datos corriente.
 *
 * Hace falta porque `postgres` es superusuario y **PostgreSQL nunca aplica RLS
 * a un superusuario**. Sin cambiar de rol, estas pruebas pasarían en verde sin
 * haber comprobado nada. En producción la aplicación se conecta con un rol como
 * este, no con el superusuario.
 *
 * Va dentro de una transacción anidada, es decir, de un savepoint: si la
 * consulta la rechaza la política, PostgreSQL aborta la transacción y solo un
 * rollback la deja utilizable para el resto de la prueba. El savepoint devuelve
 * además el rol anterior, porque `set role` también se deshace.
 *
 * @template TRetorno
 *
 * @param  Closure(): TRetorno  $callback
 * @return TRetorno
 */
function conRolDeAplicacion(Closure $callback): mixed
{
    return DB::transaction(function () use ($callback) {
        DB::statement('set role '.ROL_DE_APLICACION);

        $resultado = $callback();

        DB::statement('reset role');

        return $resultado;
    });
}

beforeEach(function () {
    Schema::create('registros_de_prueba', function (Blueprint $tabla) {
        $tabla->id();
        $tabla->foreignId('tenant_id')->constrained('tenants');
        $tabla->string('titulo');
        $tabla->timestamps();
    });

    PoliticaRls::activarEn('registros_de_prueba');

    DB::statement(<<<'SQL'
        do $$ begin
            if not exists (select from pg_roles where rolname = 'nexo_rol_de_prueba') then
                create role nexo_rol_de_prueba nologin;
            end if;
        end $$
        SQL);

    DB::statement('grant select, insert, update, delete on registros_de_prueba to '.ROL_DE_APLICACION);
    DB::statement('grant usage, select on sequence registros_de_prueba_id_seq to '.ROL_DE_APLICACION);

    $this->contexto = app(ContextoTenant::class);
    $this->empresaA = Tenant::factory()->create(['nombre' => 'Empresa A']);
    $this->empresaB = Tenant::factory()->create(['nombre' => 'Empresa B']);

    $this->registroDeLaA = $this->contexto->paraTenant($this->empresaA, fn () => DB::table('registros_de_prueba')->insertGetId([
        'tenant_id' => $this->empresaA->id,
        'titulo' => 'Nómina de la A',
        'created_at' => now(),
        'updated_at' => now(),
    ]));
});

it('fija la empresa del contexto en la sesión de la base de datos', function () {
    $this->contexto->paraTenant($this->empresaB, function () {
        expect(PoliticaRls::empresaFijada())->toBe($this->empresaB->id);
    });
});

it('deja la empresa de la sesión vacía cuando no hay contexto', function () {
    $this->contexto->sinTenant(function () {
        expect(PoliticaRls::empresaFijada())->toBeNull();
    });
});

it('devuelve la empresa anterior a la sesión cuando el trabajo falla', function () {
    $this->contexto->fijar($this->empresaA);

    try {
        $this->contexto->paraTenant($this->empresaB, function () {
            throw new RuntimeException('el trabajo reventó');
        });
    } catch (RuntimeException) {
        // Lo que importa es cómo quedó la sesión después.
    }

    expect(PoliticaRls::empresaFijada())->toBe($this->empresaA->id);
});

it('no devuelve filas de otra empresa a una consulta SQL directa', function () {
    $filas = $this->contexto->paraTenant(
        $this->empresaB,
        fn () => conRolDeAplicacion(fn () => DB::table('registros_de_prueba')->get())
    );

    expect($filas)->toHaveCount(0);
});

it('sí devuelve las filas propias a una consulta SQL directa', function () {
    // El contrapeso de la prueba anterior: sin esto, una política que lo
    // bloquease todo —o un rol sin permisos— también daría cero filas y la
    // prueba de arriba pasaría sin comprobar nada.
    $filas = $this->contexto->paraTenant(
        $this->empresaA,
        fn () => conRolDeAplicacion(fn () => DB::table('registros_de_prueba')->get())
    );

    expect($filas)->toHaveCount(1)
        ->and($filas->first()->titulo)->toBe('Nómina de la A');
});

it('no devuelve nada a una consulta SQL directa sin empresa en la sesión', function () {
    $filas = $this->contexto->sinTenant(
        fn () => conRolDeAplicacion(fn () => DB::table('registros_de_prueba')->get())
    );

    expect($filas)->toHaveCount(0);
});

it('no encuentra por clave primaria una fila de otra empresa', function () {
    $fila = $this->contexto->paraTenant(
        $this->empresaB,
        fn () => conRolDeAplicacion(fn () => DB::table('registros_de_prueba')->find($this->registroDeLaA))
    );

    expect($fila)->toBeNull();
});

it('no deja sembrar una fila en otra empresa aunque la consulta lo pida', function () {
    $sembrar = fn () => $this->contexto->paraTenant(
        $this->empresaB,
        fn () => conRolDeAplicacion(fn () => DB::table('registros_de_prueba')->insert([
            'tenant_id' => $this->empresaA->id,
            'titulo' => 'Colada desde la B',
            'created_at' => now(),
            'updated_at' => now(),
        ]))
    );

    expect($sembrar)->toThrow(QueryException::class);

    expect(DB::table('registros_de_prueba')->where('titulo', 'Colada desde la B')->count())->toBe(0);
});

it('no deja modificar una fila de otra empresa', function () {
    $afectadas = $this->contexto->paraTenant(
        $this->empresaB,
        fn () => conRolDeAplicacion(fn () => DB::table('registros_de_prueba')
            ->where('id', $this->registroDeLaA)
            ->update(['titulo' => 'Pisada desde la B'])
        )
    );

    expect($afectadas)->toBe(0)
        ->and(DB::table('registros_de_prueba')->find($this->registroDeLaA)->titulo)->toBe('Nómina de la A');
});

it('no deja borrar una fila de otra empresa', function () {
    $borradas = $this->contexto->paraTenant(
        $this->empresaB,
        fn () => conRolDeAplicacion(fn () => DB::table('registros_de_prueba')
            ->where('id', $this->registroDeLaA)
            ->delete()
        )
    );

    expect($borradas)->toBe(0)
        ->and(DB::table('registros_de_prueba')->count())->toBe(1);
});

it('deja fijada la empresa en la sesión al resolver la petición', function () {
    Route::middleware('web')->get('/prueba-de-rls', fn () => response()->json([
        'empresa' => PoliticaRls::empresaFijada(),
    ]));

    $usuario = User::factory()->create(['tenant_id' => $this->empresaB->id]);

    $this->actingAs($usuario)
        ->get('/prueba-de-rls')
        ->assertOk()
        ->assertJson(['empresa' => $this->empresaB->id]);
});

it('limpia la empresa de la sesión para una visita sin autenticar', function () {
    Route::middleware('web')->get('/prueba-de-rls', fn () => response()->json([
        'empresa' => PoliticaRls::empresaFijada(),
    ]));

    // Se ensucia la sesión a propósito, como la dejaría una conexión
    // reutilizada de la petición anterior.
    $this->contexto->fijar($this->empresaA);

    $this->get('/prueba-de-rls')
        ->assertOk()
        ->assertJson(['empresa' => null]);
});
