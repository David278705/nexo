<?php

use App\Autorizacion\SeccionDelPanel;
use App\Models\EjecucionDeAlertas;
use App\Models\ErrorDeAplicacion;
use App\Proveedor\ColaDeTrabajos;
use App\Proveedor\RegistroDeErrores;
use App\Proveedor\SaludDelSistema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * La salud del sistema del panel del proveedor (F5-06, documento 4,
 * capítulo 9): el proceso diario, la cola, los errores de aplicación y las
 * copias de seguridad.
 *
 * `ownerEnElPanel()` vive en `CrearEmpresaTest.php`. Pest comparte las
 * funciones de todos los archivos de prueba.
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-08-24 09:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('el historial de ejecuciones del proceso diario', function () {
    it('deja constancia de cada corrida, con cuánto tardó', function () {
        test()->artisan('alertas:diarias', ['--fecha' => '2026-08-24'])->assertSuccessful();

        $ejecucion = EjecucionDeAlertas::query()->firstOrFail();

        expect($ejecucion->fecha->toDateString())->toBe('2026-08-24')
            ->and($ejecucion->exitosa)->toBeTrue()
            ->and($ejecucion->duracion_ms)->toBeGreaterThanOrEqual(0);
    });

    it('no duplica una fila por corrida: cada `alertas:diarias` deja su propia constancia', function () {
        test()->artisan('alertas:diarias', ['--fecha' => '2026-08-24'])->assertSuccessful();
        test()->artisan('alertas:diarias', ['--fecha' => '2026-08-24'])->assertSuccessful();

        expect(EjecucionDeAlertas::query()->count())->toBe(2);
    });
});

describe('el registro de errores de aplicación', function () {
    it('guarda la clase, el mensaje y el contexto del error', function () {
        RegistroDeErrores::registrar(new RuntimeException('Algo se rompió'));

        $error = ErrorDeAplicacion::query()->firstOrFail();

        expect($error->clase)->toBe(RuntimeException::class)
            ->and($error->mensaje)->toBe('Algo se rompió')
            ->and($error->contexto)->toBe('consola');
    });
});

describe('la cola de trabajos', function () {
    it('cuenta los pendientes y los fallidos', function () {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => 'x',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => Carbon::now()->timestamp,
            'created_at' => Carbon::now()->timestamp,
        ]);

        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => 'x',
            'exception' => "Exception: algo falló\n#0 en otro lado",
            'failed_at' => Carbon::now(),
        ]);

        expect(ColaDeTrabajos::pendientes())->toBe(1)
            ->and(ColaDeTrabajos::fallidosTotal())->toBe(1);

        $fallidos = ColaDeTrabajos::fallidos();
        expect($fallidos[0]['uuid'])->toBe($uuid)
            ->and($fallidos[0]['excepcion'])->toBe('Exception: algo falló');
    });

    it('reintentar un trabajo fallido lo devuelve a la cola y lo saca de los fallidos', function () {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => 'x',
            'exception' => 'Exception: algo falló',
            'failed_at' => Carbon::now(),
        ]);

        ColaDeTrabajos::reintentar($uuid);

        expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse()
            ->and(DB::table('jobs')->count())->toBe(1);
    });
});

describe('las copias de seguridad', function () {
    it('no inventa una fecha: dice que dependen de una decisión pendiente', function () {
        $props = SaludDelSistema::paraLaPantalla();

        expect($props['copias']['disponible'])->toBeFalse()
            ->and($props['copias']['texto'])->toContain('A.5');
    });
});

describe('el espacio de la base de datos', function () {
    it('lo lee de Postgres, no lo inventa', function () {
        $props = SaludDelSistema::paraLaPantalla();

        expect($props['espacioBd'])->toMatch('/^[\d,.]+ (MB|GB)$/');
    });
});

describe('el acceso a la pantalla', function () {
    it('ya no está entre las secciones pendientes del panel', function () {
        expect(SeccionDelPanel::pendientes())->not->toContain(SeccionDelPanel::Salud);
    });

    it('renderiza la salud del sistema con sus cinco piezas', function () {
        $owner = ownerEnElPanel();

        $respuesta = test()->actingAs($owner)->get('/proveedor/salud');

        $respuesta->assertOk();

        $props = $respuesta->viewData('page')['props'];

        expect($props)->toHaveKeys(['proceso', 'cola', 'errores', 'copias', 'espacioBd', 'correosDelMes']);
    });

    it('reintenta un trabajo fallido desde la pantalla', function () {
        $owner = ownerEnElPanel();

        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => 'x',
            'exception' => 'Exception: algo falló',
            'failed_at' => Carbon::now(),
        ]);

        $respuesta = test()->actingAs($owner)->post("/proveedor/salud/cola/{$uuid}/reintentar");

        $respuesta->assertRedirect();
        expect(DB::table('failed_jobs')->where('uuid', $uuid)->exists())->toBeFalse();
    });
});
